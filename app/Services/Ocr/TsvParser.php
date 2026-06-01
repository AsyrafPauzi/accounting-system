<?php

namespace App\Services\Ocr;

/**
 * Spatial parser for Tesseract TSV output.
 *
 * Tesseract's `tesseract image stdout tsv` mode emits one line per WORD
 * with bounding-box coordinates and per-word confidence. This parser uses
 * those coordinates to RECONSTRUCT TABLE STRUCTURE that the plain-text
 * output flattens away.
 *
 * Algorithm:
 *   1. Cluster words into ROWS by Y-coordinate (within ~half-height tolerance).
 *   2. Find a HEADER row containing 2+ table-header keywords (`description`,
 *      `qty`, `total`, `jumlah`, etc.).
 *   3. For the header, identify COLUMNS by horizontal gaps between words.
 *   4. For each subsequent row, bucket words into the column whose anchor
 *      point is closest. STOP at the first row where any word matches a
 *      subtotal/total keyword AND the row has a money amount.
 *   5. Map columns to fields based on header text and emit `items[]`.
 *
 * For non-tabular receipts, the algorithm gracefully returns an empty
 * `items` array — caller (TesseractProvider) falls back to ReceiptParser.
 *
 * Output shape matches ReceiptParser::parse() so the two are interchangeable.
 */
class TsvParser
{
    /** Words within ±this fraction of each other's height belong to the same row. */
    private const ROW_TOLERANCE_FRACTION = 0.5;

    /** Horizontal gap (in px) that suggests a column boundary. */
    private const COLUMN_GAP_THRESHOLD = 30;

    private const HEADER_KEYWORDS = [
        'description', 'item', 'perihal', 'barang', 'perkhidmatan',
        'qty', 'quantity', 'kuantiti', 'bil',
        'unit', 'price', 'harga', 'rate',
        'amount', 'total', 'jumlah', 'value',
    ];

    private const STOP_KEYWORDS = [
        // SUBTOTAL_KEYWORDS + TOTAL_KEYWORDS from ReceiptParser, scoped to TsvParser context.
        'subtotal', 'sub total', 'sub-total', 'jumlah kecil', 'jumlah',
        'grand total', 'jumlah besar', 'jumlah bayaran',
        'total', 'amount due', 'amount payable', 'total amount',
        'sst', 'gst', 'tax', 'cukai',
    ];

    /**
     * Parse Tesseract TSV output. Returns same structure as ReceiptParser::parse().
     */
    public function parse(string $tsv): array
    {
        $words = $this->parseTsv($tsv);
        $rows = $this->clusterRows($words);
        $items = [];
        if (! empty($rows)) {
            $items = $this->extractItems($rows);
        }

        // For non-item fields we don't bother re-implementing all of
        // ReceiptParser's heuristics — we let the caller merge our items
        // with ReceiptParser's text-based fields. We DO pull a raw text
        // representation so callers can use it if needed.
        $rawText = $this->reconstructText($rows);

        return [
            'vendor_name' => null,
            'bill_date' => null,
            'subtotal' => null,
            'tax_amount' => null,
            'total_amount' => null,
            'currency' => null,
            'reference' => null,
            'items' => $items,
            'raw_text' => $rawText,
            'confidence' => null,
        ];
    }

    /**
     * EXPOSED FOR TESTING. Cluster TSV words into rows; returns array of rows,
     * each row being an array of word records.
     *
     * @return array<int, array<int, array{text:string, left:int, top:int, width:int, height:int, conf:int}>>
     */
    public function debugClusterRows(string $tsv): array
    {
        return $this->clusterRows($this->parseTsv($tsv));
    }

    /**
     * Convert raw TSV string into an array of word records (level 5 only).
     *
     * @return array<int, array{text:string, left:int, top:int, width:int, height:int, conf:int}>
     */
    private function parseTsv(string $tsv): array
    {
        if ($tsv === '') return [];
        $lines = explode("\n", $tsv);
        if (count($lines) < 2) return [];

        $headerLine = array_shift($lines);
        $columns = explode("\t", $headerLine);
        $colIndex = array_flip($columns);

        $required = ['level', 'left', 'top', 'width', 'height', 'conf', 'text'];
        foreach ($required as $needle) {
            if (! isset($colIndex[$needle])) return []; // malformed TSV
        }

        $words = [];
        foreach ($lines as $line) {
            $line = rtrim($line, "\r");
            if ($line === '') continue;
            $parts = explode("\t", $line);
            if (count($parts) < count($columns)) continue;

            $level = (int) $parts[$colIndex['level']];
            if ($level !== 5) continue; // we only want word-level records

            $text = $parts[$colIndex['text']] ?? '';
            $text = trim($text);
            if ($text === '') continue;

            $words[] = [
                'text' => $text,
                'left' => (int) $parts[$colIndex['left']],
                'top' => (int) $parts[$colIndex['top']],
                'width' => (int) $parts[$colIndex['width']],
                'height' => (int) $parts[$colIndex['height']],
                'conf' => (int) $parts[$colIndex['conf']],
            ];
        }
        return $words;
    }

    /**
     * Group words into rows by Y-coordinate proximity.
     *
     * @param array $words
     * @return array<int, array<int, array{text:string, left:int, top:int, width:int, height:int, conf:int}>>
     */
    private function clusterRows(array $words): array
    {
        if (empty($words)) return [];

        // Sort by top ascending so we can stream-cluster left-to-right.
        usort($words, fn ($a, $b) => $a['top'] <=> $b['top']);

        $medianHeight = $this->median(array_column($words, 'height'));
        $tolerance = max(8, (int) round($medianHeight * self::ROW_TOLERANCE_FRACTION));

        $rows = [];
        $currentRow = [];
        $currentRowTop = null;

        foreach ($words as $w) {
            if ($currentRowTop === null) {
                $currentRowTop = $w['top'];
                $currentRow[] = $w;
                continue;
            }
            if (abs($w['top'] - $currentRowTop) <= $tolerance) {
                $currentRow[] = $w;
            } else {
                // Flush current row, sorted by left so reading order is preserved.
                usort($currentRow, fn ($a, $b) => $a['left'] <=> $b['left']);
                $rows[] = $currentRow;
                $currentRow = [$w];
                $currentRowTop = $w['top'];
            }
        }
        if (! empty($currentRow)) {
            usort($currentRow, fn ($a, $b) => $a['left'] <=> $b['left']);
            $rows[] = $currentRow;
        }

        return $rows;
    }

    /**
     * Find the table header row (one with 2+ HEADER_KEYWORDS), determine
     * column anchors, then bucket subsequent rows into items.
     */
    private function extractItems(array $rows): array
    {
        $headerIndex = $this->findHeaderRow($rows);
        if ($headerIndex === null) return [];

        $headerRow = $rows[$headerIndex];
        $columnAnchors = $this->detectColumnAnchors($headerRow);
        if (count($columnAnchors) < 2) return []; // not really a table

        $columnRoles = $this->mapColumnsToFields($headerRow, $columnAnchors);
        if (! isset($columnRoles['amount'])) return []; // a table with no amount column is useless

        $items = [];
        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $rowText = $this->joinRowText($row);

            // STOP when the row text matches a subtotal/total/tax keyword AND has money.
            if ($this->isStopRow($rowText)) break;

            $bucketed = $this->bucketIntoColumns($row, $columnAnchors);
            $item = $this->rowToItem($bucketed, $columnRoles);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function findHeaderRow(array $rows): ?int
    {
        foreach ($rows as $idx => $row) {
            $text = strtolower($this->joinRowText($row));
            $hits = 0;
            foreach (self::HEADER_KEYWORDS as $kw) {
                if (str_contains($text, $kw)) {
                    $hits++;
                    if ($hits >= 2) return $idx;
                }
            }
            // Special-case: a 2-column "Description ... Total" layout has
            // exactly 2 keyword hits (description + total).
        }

        // Fallback: a single keyword IS enough if the row looks header-ish
        // (small number of words, no money amounts, occurs above lines that
        // do contain money).
        foreach ($rows as $idx => $row) {
            $text = strtolower($this->joinRowText($row));
            // No money on header rows.
            if (preg_match('/\d+\.\d{2}/', $text)) continue;
            foreach (self::HEADER_KEYWORDS as $kw) {
                if (str_contains($text, $kw)) {
                    // Make sure SOME row below it has a money amount.
                    for ($j = $idx + 1; $j < count($rows); $j++) {
                        if (preg_match('/\d+\.\d{2}/', $this->joinRowText($rows[$j]))) {
                            return $idx;
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * Identify column anchor X-positions from the header row by looking for
     * gaps between words. Each contiguous group of words = one column;
     * anchor = leftmost word's `left`.
     *
     * @return array<int, int> List of left-X anchors in ascending order.
     */
    private function detectColumnAnchors(array $headerRow): array
    {
        if (empty($headerRow)) return [];
        // Sort by left (already sorted by clusterRows but defensive).
        usort($headerRow, fn ($a, $b) => $a['left'] <=> $b['left']);

        $anchors = [$headerRow[0]['left']];
        for ($i = 1; $i < count($headerRow); $i++) {
            $prev = $headerRow[$i - 1];
            $prevRight = $prev['left'] + $prev['width'];
            $gap = $headerRow[$i]['left'] - $prevRight;
            if ($gap >= self::COLUMN_GAP_THRESHOLD) {
                $anchors[] = $headerRow[$i]['left'];
            }
        }
        return $anchors;
    }

    /**
     * Map each column anchor to a semantic field by looking at the header
     * word(s) sitting at that anchor.
     *
     * @return array<string, int> field => column index
     */
    private function mapColumnsToFields(array $headerRow, array $anchors): array
    {
        $bucketed = $this->bucketIntoColumns($headerRow, $anchors);
        $roles = [];
        foreach ($bucketed as $colIdx => $words) {
            $text = strtolower(implode(' ', array_column($words, 'text')));
            if ($text === '') continue;

            if (str_contains($text, 'description') || str_contains($text, 'perihal')
                || str_contains($text, 'item') || str_contains($text, 'barang')
                || str_contains($text, 'perkhidmatan')) {
                $roles['description'] ??= $colIdx;
            } elseif (str_contains($text, 'qty') || str_contains($text, 'quantity') || str_contains($text, 'kuantiti') || str_contains($text, 'bil')) {
                $roles['quantity'] ??= $colIdx;
            } elseif (str_contains($text, 'unit') || str_contains($text, 'harga')) {
                $roles['unit_amount'] ??= $colIdx;
            } elseif (str_contains($text, 'amount') || str_contains($text, 'total') || str_contains($text, 'jumlah') || str_contains($text, 'value')) {
                $roles['amount'] ??= $colIdx;
            }
        }

        // If no description column was named, default it to col 0 (leftmost).
        if (! isset($roles['description']) && count($anchors) >= 2) {
            $roles['description'] = 0;
        }
        // If no amount column was named but we have ≥2 columns, default it to last.
        if (! isset($roles['amount']) && count($anchors) >= 2) {
            $roles['amount'] = count($anchors) - 1;
        }
        return $roles;
    }

    /**
     * Bucket each word into the column whose anchor it's CLOSEST to (by left edge).
     *
     * @return array<int, array<int, array>> column index => list of words
     */
    private function bucketIntoColumns(array $words, array $anchors): array
    {
        $buckets = array_fill(0, count($anchors), []);
        foreach ($words as $w) {
            $bestCol = 0;
            $bestDist = PHP_INT_MAX;
            foreach ($anchors as $idx => $anchor) {
                $dist = abs($w['left'] - $anchor);
                if ($dist < $bestDist) {
                    $bestDist = $dist;
                    $bestCol = $idx;
                }
            }
            $buckets[$bestCol][] = $w;
        }
        return $buckets;
    }

    /**
     * Convert one bucketed row into a {description, quantity?, unit_amount?, amount} item.
     */
    private function rowToItem(array $buckets, array $roles): ?array
    {
        $descCol = $roles['description'] ?? null;
        $amtCol = $roles['amount'] ?? null;
        if ($descCol === null || $amtCol === null) return null;

        $descText = trim(implode(' ', array_column($buckets[$descCol] ?? [], 'text')));
        $amtText = $this->parseMoney(implode(' ', array_column($buckets[$amtCol] ?? [], 'text')));

        if ($descText === '' || $amtText === null) return null;
        if (! preg_match('/[A-Za-z]/', $descText)) return null;

        $item = [
            'description' => $descText,
            'amount' => $amtText,
        ];

        if (isset($roles['quantity'])) {
            $qtyText = trim(implode(' ', array_column($buckets[$roles['quantity']] ?? [], 'text')));
            if (is_numeric($qtyText)) {
                $item['quantity'] = (float) $qtyText;
            }
        }
        if (isset($roles['unit_amount'])) {
            $unitText = $this->parseMoney(implode(' ', array_column($buckets[$roles['unit_amount']] ?? [], 'text')));
            if ($unitText !== null) {
                $item['unit_amount'] = $unitText;
            }
        }

        // Validate qty * unit ≈ amount if all three present.
        if (isset($item['quantity'], $item['unit_amount'])) {
            if (abs(round($item['quantity'] * $item['unit_amount'], 2) - $item['amount']) > 0.05) {
                // Fields don't agree — drop the qty/unit; trust the bottom-line amount.
                unset($item['quantity'], $item['unit_amount']);
            }
        }

        return $item;
    }

    private function parseMoney(string $text): ?float
    {
        // Strip currency markers and commas, then look for `\d+\.\d{2}` or trailing integer.
        $clean = preg_replace('/[A-Za-z$£€]+/', '', $text);
        $clean = str_replace(',', '', $clean);
        if (preg_match('/(\d+\.\d{2})/', $clean, $m)) {
            return (float) $m[1];
        }
        return null;
    }

    private function isStopRow(string $rowText): bool
    {
        $low = strtolower($rowText);
        $hasMoney = (bool) preg_match('/\d+\.\d{2}/', $low);
        if (! $hasMoney) return false;
        foreach (self::STOP_KEYWORDS as $kw) {
            if (str_contains($low, $kw)) return true;
        }
        return false;
    }

    private function joinRowText(array $row): string
    {
        return implode(' ', array_column($row, 'text'));
    }

    private function reconstructText(array $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            $lines[] = $this->joinRowText($row);
        }
        return implode("\n", $lines);
    }

    private function median(array $values): float
    {
        if (empty($values)) return 0.0;
        sort($values);
        $count = count($values);
        $mid = (int) ($count / 2);
        if ($count % 2 === 0) {
            return ($values[$mid - 1] + $values[$mid]) / 2;
        }
        return (float) $values[$mid];
    }
}
