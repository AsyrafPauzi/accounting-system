<?php

namespace App\Services\Ocr;

/**
 * Heuristic parser that extracts structured fields from raw OCR text.
 *
 * Built for Malaysian receipts (RM/MYR, English + Bahasa Malaysia keywords)
 * but tries to handle Singapore (SGD), and generic English receipts as well.
 *
 * Accuracy is intentionally "good enough for auto-fill" — the user
 * always reviews extracted fields before saving the bill.
 */
class ReceiptParser
{
    /**
     * Keywords (English + BM) that indicate the GRAND total / amount payable.
     * Order matters: `grand total` matches before `total` so we don't pick
     * the subtotal line by mistake.
     */
    private const TOTAL_KEYWORDS = [
        'grand total',
        'jumlah besar',
        'amount due',
        'amount payable',
        'total amount',
        'total payable',
        'jumlah',
        'total',
    ];

    private const SUBTOTAL_KEYWORDS = [
        'sub total',
        'sub-total',
        'subtotal',
        'jumlah kecil',
    ];

    private const TAX_KEYWORDS = [
        'sst',
        'gst',
        'tax',
        'cukai',
        'vat',
    ];

    /**
     * Lines that look like a total but are NOT what we want to capture
     * (cash tendered, change, etc.).
     */
    private const SKIP_KEYWORDS = [
        'cash',
        'change',
        'tunai',
        'baki',
        'tendered',
        'card',
        'visa',
        'mastercard',
    ];

    private const CURRENCY_PATTERNS = [
        'MYR' => '/\b(?:rm|myr|ringgit)\b/i',
        'SGD' => '/\b(?:sgd|s\$)\b/i',
        'USD' => '/\b(?:usd|us\$)\b/i',
        'EUR' => '/\b(?:eur|€)\b/i',
        'GBP' => '/\b(?:gbp|£)\b/i',
        'IDR' => '/\b(?:idr|rp)\b/i',
        'THB' => '/\b(?:thb|฿)\b/i',
    ];

    /**
     * @return array{
     *     vendor_name: ?string,
     *     bill_date: ?string,
     *     subtotal: ?float,
     *     tax_amount: ?float,
     *     total_amount: ?float,
     *     currency: ?string,
     *     reference: ?string,
     *     items: array,
     *     raw_text: string,
     *     confidence: ?float,
     * }
     */
    public function parse(string $rawText): array
    {
        $lines = preg_split('/\R+/', $rawText) ?: [];
        $cleaned = array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));

        return [
            'vendor_name' => $this->findVendor($cleaned),
            'bill_date' => $this->findDate($rawText),
            'subtotal' => $this->findAmountForKeywords($cleaned, self::SUBTOTAL_KEYWORDS),
            'tax_amount' => $this->findAmountForKeywords($cleaned, self::TAX_KEYWORDS),
            'total_amount' => $this->findTotal($cleaned),
            'currency' => $this->findCurrency($rawText),
            'reference' => $this->findReference($cleaned),
            'items' => [],
            'raw_text' => $rawText,
            'confidence' => $this->estimateConfidence($cleaned),
        ];
    }

    /**
     * Vendor heuristic: first line ≥ 4 characters that isn't an obvious
     * separator or short fragment. Real receipts almost always lead with the
     * business name as the largest, first text on the receipt.
     */
    private function findVendor(array $lines): ?string
    {
        foreach (array_slice($lines, 0, 8) as $line) {
            // Skip pure separators, very short lines, dates, or amount lines.
            if (mb_strlen($line) < 4) continue;
            if (preg_match('/^[\s\-=_*+.]+$/', $line)) continue;

            // Skip lines that are PRIMARILY a date or amount (but allow vendor names
            // that happen to start with digits, like "99 SPEEDMART" or "7-ELEVEN").
            if (preg_match('/^\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}$/', $line)) continue;
            if (preg_match('/\d+\.\d{2}/', $line)) continue;

            if ($this->lineMatchesAnyKeyword($line, [...self::TOTAL_KEYWORDS, ...self::SUBTOTAL_KEYWORDS, ...self::TAX_KEYWORDS, 'date', 'time', 'tarikh', 'receipt', 'invoice'])) continue;

            return $line;
        }
        return null;
    }

    /**
     * Tries multiple date layouts: dd/mm/yyyy, dd-mm-yyyy, dd.mm.yyyy,
     * dd/mm/yy. Returns the first match in Y-m-d format.
     */
    private function findDate(string $text): ?string
    {
        // Match D/M/Y, D-M-Y, D.M.Y with 2 or 4 digit year.
        if (preg_match_all('/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})\b/', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $day = (int) $m[1];
                $month = (int) $m[2];
                $year = (int) $m[3];

                if ($year < 100) {
                    // 2-digit year — assume 2000s
                    $year += 2000;
                }

                if ($day >= 1 && $day <= 31 && $month >= 1 && $month <= 12 && $year >= 2000 && $year <= 2100) {
                    return sprintf('%04d-%02d-%02d', $year, $month, $day);
                }
            }
        }
        return null;
    }

    /**
     * Total: scan TOTAL_KEYWORDS in priority order (grand total first).
     * Skip lines that contain SKIP_KEYWORDS even if they also have "total"
     * (e.g. "cash total tendered RM 50.00").
     */
    private function findTotal(array $lines): ?float
    {
        foreach (self::TOTAL_KEYWORDS as $keyword) {
            foreach ($lines as $line) {
                if (! $this->lineMatchesKeyword($line, $keyword)) continue;
                if ($this->lineMatchesAnyKeyword($line, self::SKIP_KEYWORDS)) continue;
                if ($this->lineMatchesAnyKeyword($line, self::SUBTOTAL_KEYWORDS) && $keyword !== 'grand total' && $keyword !== 'jumlah besar') continue;

                $amount = $this->extractAmountFromLine($line);
                if ($amount !== null) {
                    return $amount;
                }
            }
        }
        return null;
    }

    private function findAmountForKeywords(array $lines, array $keywords): ?float
    {
        foreach ($keywords as $keyword) {
            foreach ($lines as $line) {
                if (! $this->lineMatchesKeyword($line, $keyword)) continue;
                $amount = $this->extractAmountFromLine($line);
                if ($amount !== null) {
                    return $amount;
                }
            }
        }
        return null;
    }

    private function findCurrency(string $text): ?string
    {
        foreach (self::CURRENCY_PATTERNS as $code => $pattern) {
            if (preg_match($pattern, $text)) {
                return $code;
            }
        }
        return null;
    }

    /**
     * Reference / invoice / receipt number. Looks for keywords like
     * "Invoice No", "Receipt No", "Ref" followed by an alphanumeric token.
     */
    private function findReference(array $lines): ?string
    {
        $patterns = [
            '/(?:invoice|inv|receipt|ref|reference|no\.?)\s*(?:no\.?|#|:)?\s*[:\-]?\s*([A-Z0-9][A-Z0-9\-\/_]{2,30})/i',
        ];
        foreach ($lines as $line) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line, $m)) {
                    $candidate = trim($m[1]);
                    // Reject pure-numeric short tokens (likely amounts/dates)
                    if (preg_match('/^[A-Z0-9]+\-/i', $candidate) || mb_strlen($candidate) >= 6) {
                        return strtoupper($candidate);
                    }
                }
            }
        }
        return null;
    }

    /**
     * Pull the right-most numeric amount from the given line. Receipts
     * universally place the amount at the right edge, so the LAST number
     * in the line is the safest pick.
     */
    private function extractAmountFromLine(string $line): ?float
    {
        if (preg_match_all('/(\d{1,3}(?:,\d{3})*\.\d{2}|\d+\.\d{2})/', $line, $m)) {
            $last = end($m[1]);
            return (float) str_replace(',', '', $last);
        }
        return null;
    }

    private function lineMatchesKeyword(string $line, string $keyword): bool
    {
        return stripos($line, $keyword) !== false;
    }

    private function lineMatchesAnyKeyword(string $line, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if ($this->lineMatchesKeyword($line, $kw)) return true;
        }
        return false;
    }

    /**
     * Crude confidence score 0..1 based on how many of the key fields
     * we managed to extract. Used purely for UI hints.
     */
    private function estimateConfidence(array $lines): ?float
    {
        if ($lines === []) return 0.0;

        $hits = 0;
        $rawText = implode("\n", $lines);
        if ($this->findDate($rawText) !== null) $hits++;
        if ($this->findTotal($lines) !== null) $hits++;
        if ($this->findVendor($lines) !== null) $hits++;
        if ($this->findCurrency($rawText) !== null) $hits++;

        return round($hits / 4, 2);
    }
}
