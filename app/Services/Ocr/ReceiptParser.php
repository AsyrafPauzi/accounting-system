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
        'jumlah bayaran', // BM: "amount payable" — final total on Malaysian receipts
        'total pembayaran', // Indonesian: "total payment"
        'net payable',
        'total due',
        'total paid',
        'total amount paid',
        'total amount',
        'total to pay',
        'total payable balance',
        'total payable',
        'amount due',
        'amount payable',
        'total amount',
        'jumlah',
        'total',
    ];

    /**
     * Keywords that mark a SUBTOTAL line. `jumlah` is included as a fallback
     * because Malaysian receipts often label the pre-tax total as just "Jumlah"
     * with the final total separately as "JUMLAH BAYARAN".
     */
    private const SUBTOTAL_KEYWORDS = [
        'items total',
        'current charges total',
        'sub total',
        'sub-total',
        'subtotal',
        'net total', // pre-tax total on commercial invoices
        'net amount',
        'jumlah kecil',
        'jumlah',
    ];

    /**
     * TOTAL_KEYWORDS that mean "definitely the final total" — receipts labelled
     * with one of these are kept even when they also literally contain the
     * word "jumlah" (which would otherwise be skipped as a subtotal hit).
     */
    private const FINAL_TOTAL_KEYWORDS = [
        'grand total',
        'jumlah besar',
        'jumlah bayaran',
        'total pembayaran',
        'net payable',
        'total due',
        'total paid',
        'total amount paid',
        'total amount',
        'total to pay',
        'total payable balance',
        'total payable',
        'amount due',
        'amount payable',
    ];

    private const TAX_KEYWORDS = [
        'sst',
        'gst',
        'tax',
        'cukai',
        'vat',
        'ppn', // Indonesian VAT (Pajak Pertambahan Nilai)
        'pph', // Indonesian withholding tax
        'service charge',
        'service tax',
        'cukai perkhidmatan',
        'caj perkhidmatan', // "service charge" in BM
        'pajak', // BM/Indonesian generic "tax"
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
        'visa',
        'mastercard',
    ];

    /**
     * Currency detection. Each pattern uses a leading word boundary but a
     * lookahead trailer so it matches "RM 44.70" AND "RM44.70" (glued).
     */
    private const CURRENCY_PATTERNS = [
        'MYR' => '/\b(?:rm|myr|ringgit)(?=\b|\d)/i',
        'SGD' => '/\b(?:sgd|s\$)(?=\b|\d)/i',
        'USD' => '/\b(?:usd|us\$)(?=\b|\d)/i',
        'EUR' => '/\b(?:eur|€)(?=\b|\d)/i',
        'GBP' => '/\b(?:gbp|£)(?=\b|\d)/i',
        'IDR' => '/\b(?:idr|rp)(?=\b|\d)/i',
        'THB' => '/\b(?:thb|฿)(?=\b|\d)/i',
    ];

    /**
     * Month name → numeric month. Covers English + Bahasa Malaysia.
     * Keys are lowercased so we match case-insensitively.
     */
    private const MONTH_NAMES = [
        'jan' => 1, 'januari' => 1, 'january' => 1,
        'feb' => 2, 'februari' => 2, 'february' => 2,
        'mac' => 3, 'mar' => 3, 'march' => 3,
        'apr' => 4, 'april' => 4,
        'mei' => 5, 'may' => 5,
        'jun' => 6, 'june' => 6,
        'jul' => 7, 'julai' => 7, 'july' => 7,
        'ogos' => 8, 'aug' => 8, 'august' => 8,
        'sep' => 9, 'sept' => 9, 'september' => 9,
        'okt' => 10, 'oct' => 10, 'oktober' => 10, 'october' => 10,
        'nov' => 11, 'november' => 11,
        'dis' => 12, 'dec' => 12, 'disember' => 12, 'december' => 12,
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
            'items' => $this->findItems($cleaned),
            'raw_text' => $rawText,
            'confidence' => $this->estimateConfidence($cleaned),
        ];
    }

    /**
     * Single-word status stamps (exact match after lowercase). Must NOT be
     * picked as the vendor name when they appear alone on a line.
     */
    private const STATUS_STAMPS = ['paid', 'unpaid', 'void', 'cancelled', 'refunded', 'overdue', 'draft', 'pending'];

    /**
     * Multi-word status banners (substring match) that often appear above the
     * actual vendor name in templated invoices — like "PAYMENT SUCCESSFUL",
     * "OFFICIAL RECEIPT", "PAID INVOICE RECEIPT" — and would otherwise be
     * mistaken for the company name. Lowercased for case-insensitive match.
     */
    private const STATUS_BANNERS = [
        'payment successful', 'payment received', 'payment failed', 'payment confirmation',
        'official receipt', 'digital receipt', 'transaction receipt',
        'paid invoice receipt', 'acknowledgment receipt', 'acknowledgement receipt',
        'tax invoice', 'cash receipt', 'sales receipt',
        'kuitansi pembayaran', 'bukti pembayaran', // Indonesian: payment receipt / proof
    ];

    /**
     * Vendor heuristic: first line ≥ 4 characters that isn't an obvious
     * separator, status stamp, or short fragment. Real receipts/invoices almost
     * always lead with the business name as the largest, first text.
     */
    private function findVendor(array $lines): ?string
    {
        foreach (array_slice($lines, 0, 8) as $offset => $line) {
            // Letter-spacing collapse runs FIRST so the rest of the checks see
            // a clean candidate. ("T H E M E R I D I A N" → "THEMERIDIAN".)
            $candidate = $this->collapseLetterSpacing($line);

            if (mb_strlen($candidate) < 4) continue;
            if (preg_match('/^[\s\-=_*+.]+$/', $candidate)) continue;

            // Skip lines that are PRIMARILY a date or amount (but allow vendor names
            // that happen to start with digits, like "99 SPEEDMART" or "7-ELEVEN").
            if (preg_match('/^\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}$/', $candidate)) continue;
            if (preg_match('/\d+\.\d{2}/', $candidate)) continue;

            // Single-word status stamps (exact match), e.g. "PAID".
            if (in_array(strtolower(trim($candidate)), self::STATUS_STAMPS, true)) continue;

            // Multi-word status banners (substring match), e.g. "PAYMENT SUCCESSFUL".
            if ($this->lineMatchesAnyKeyword($candidate, self::STATUS_BANNERS)) continue;

            if ($this->lineMatchesAnyKeyword($candidate, [...self::TOTAL_KEYWORDS, ...self::SUBTOTAL_KEYWORDS, ...self::TAX_KEYWORDS, 'date', 'time', 'tarikh', 'receipt', 'invoice'])) continue;

            $next = trim($lines[$offset + 1] ?? '');
            if (
                $next !== ''
                && strtolower($candidate) === $candidate
                && preg_match('/\b(?:sdn|bhd|berhad|pte|ltd)\b/i', $next)
            ) {
                continue;
            }

            return $candidate;
        }
        return null;
    }

    /**
     * Collapse pdftotext-style letter-spaced text ("T H E M E R I D I A N")
     * into the same string with single-letter tokens joined.
     *
     * Heuristic: if ≥4 tokens are single-letter alpha AND they make up the
     * majority of the line, treat it as letter-spaced. Walk tokens, joining
     * adjacent single-letter ones into a word; treat any longer token (or
     * non-alpha like "&", ".", "PTE") as a word separator.
     */
    private function collapseLetterSpacing(string $line): string
    {
        $tokens = preg_split('/\s+/', trim($line)) ?: [];
        if (count($tokens) < 4) return $line;

        $singleCharCount = 0;
        foreach ($tokens as $t) {
            if (mb_strlen($t) === 1 && ctype_alpha($t)) $singleCharCount++;
        }
        if ($singleCharCount < count($tokens) / 2) return $line;

        $result = [];
        $buffer = '';
        foreach ($tokens as $t) {
            if (mb_strlen($t) === 1 && ctype_alpha($t)) {
                $buffer .= $t;
            } else {
                if ($buffer !== '') { $result[] = $buffer; $buffer = ''; }
                $result[] = $t;
            }
        }
        if ($buffer !== '') $result[] = $buffer;

        return implode(' ', $result);
    }

    /**
     * Tries numeric (dd/mm/yyyy) and named-month (21 Mei 2025, 21 May 2025) layouts.
     * Returns the first match in Y-m-d format.
     */
    private function findDate(string $text): ?string
    {
        if (preg_match('/\b(?:payment date|date paid)\s*:?\s*(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})/i', $text, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            if ($year < 100) $year += 2000;
            if ($day >= 1 && $day <= 31 && $month >= 1 && $month <= 12 && $year >= 2000 && $year <= 2100) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // Collect all date candidates with their byte-offset in the text.
        // When a document has multiple receipts (e.g. an OCR scan with two
        // stacked receipts), the receipt's OWN date is almost always the
        // first to appear — so we return the earliest valid candidate
        // rather than tying ourselves to a specific format.
        $candidates = []; // offset => Y-m-d

        // 1. Numeric: D/M/Y, D-M-Y, D.M.Y with 2 or 4 digit year.
        if (preg_match_all('/\b(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})\b/', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $m) {
                $day = (int) $m[1][0];
                $month = (int) $m[2][0];
                $year = (int) $m[3][0];
                if ($year < 100) $year += 2000;
                if ($day >= 1 && $day <= 31 && $month >= 1 && $month <= 12 && $year >= 2000 && $year <= 2100) {
                    $candidates[$m[0][1]] = sprintf('%04d-%02d-%02d', $year, $month, $day);
                }
            }
        }

        // 2. Named-month: "21 Mei 2025", "21 May 2025", "Mei 21, 2025", "21Mei2025"
        $monthAlternation = implode('|', array_keys(self::MONTH_NAMES));

        if (preg_match_all('/\b(\d{1,2})\s*('.$monthAlternation.')\.?\s*(\d{2,4})\b/i', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $m) {
                $day = (int) $m[1][0];
                $month = self::MONTH_NAMES[strtolower($m[2][0])] ?? null;
                $year = (int) $m[3][0];
                if ($year < 100) $year += 2000;
                if ($month && $day >= 1 && $day <= 31 && $year >= 2000 && $year <= 2100) {
                    $candidates[$m[0][1]] = sprintf('%04d-%02d-%02d', $year, $month, $day);
                }
            }
        }

        if (preg_match_all('/\b('.$monthAlternation.')\.?\s+(\d{1,2}),?\s+(\d{2,4})\b/i', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $m) {
                $month = self::MONTH_NAMES[strtolower($m[1][0])] ?? null;
                $day = (int) $m[2][0];
                $year = (int) $m[3][0];
                if ($year < 100) $year += 2000;
                if ($month && $day >= 1 && $day <= 31 && $year >= 2000 && $year <= 2100) {
                    $candidates[$m[0][1]] = sprintf('%04d-%02d-%02d', $year, $month, $day);
                }
            }
        }

        if (empty($candidates)) return null;

        ksort($candidates);
        return reset($candidates);
    }

    /**
     * Total: scan TOTAL_KEYWORDS in priority order (grand total first).
     * Skip lines that contain SKIP_KEYWORDS even if they also have "total"
     * (e.g. "cash total tendered RM 50.00").
     */
    private function findTotal(array $lines): ?float
    {
        $map = $this->buildLabelAmountMap($lines);

        foreach (self::TOTAL_KEYWORDS as $keyword) {
            foreach ($map as $label => $amount) {
                if (stripos($label, $keyword) === false) continue;
                if ($this->lineMatchesAnyKeyword($label, self::SKIP_KEYWORDS)) continue;

                // Subtotal / tax line guards (only relevant for generic keywords).
                if (
                    $this->lineMatchesAnyKeyword($label, self::SUBTOTAL_KEYWORDS)
                    && ! in_array($keyword, self::FINAL_TOTAL_KEYWORDS, true)
                ) continue;
                if (
                    $this->lineMatchesAnyKeyword($label, self::TAX_KEYWORDS)
                    && ! in_array($keyword, self::FINAL_TOTAL_KEYWORDS, true)
                ) continue;

                return $amount;
            }
        }
        return null;
    }

    private function findAmountForKeywords(array $lines, array $keywords): ?float
    {
        $map = $this->buildLabelAmountMap($lines);

        if ($keywords === self::TAX_KEYWORDS) {
            foreach (['service tax', 'sst @', 'sst ', 'total sst', 'cukai perkhidmatan', 'caj perkhidmatan'] as $preferred) {
                foreach ($map as $label => $amount) {
                    if (stripos($label, $preferred) === false) continue;
                    if (str_contains($label, 'tourism tax')) continue;
                    return $amount;
                }
            }
        }

        foreach ($keywords as $keyword) {
            foreach ($map as $label => $amount) {
                if (stripos($label, $keyword) === false) continue;
                if ($keywords === self::TAX_KEYWORDS && $this->lineMatchesAnyKeyword($label, self::SUBTOTAL_KEYWORDS)) {
                    continue;
                }
                return $amount;
            }
        }
        return null;
    }

    /**
     * Build a map of `label_lowercased => amount` covering both inline-style
     * lines ("Total: RM 50.00") AND stacked-label-then-stacked-value layouts:
     *
     *     Subtotal:                Subtotal: 3050.00
     *     SST 0%:           →     SST 0%: 0.00
     *     Total Paid:              Total Paid: 3050.00
     *     3050.00
     *     0.00
     *     3050.00
     *
     * The latter is what pdftotext emits for many WeasyPrint / generated PDFs.
     *
     * @return array<string, float>
     */
    private function buildLabelAmountMap(array $lines): array
    {
        $allKeywords = [...self::TOTAL_KEYWORDS, ...self::SUBTOTAL_KEYWORDS, ...self::TAX_KEYWORDS];
        $map = [];

        $n = count($lines);
        $i = 0;
        while ($i < $n) {
            $line = trim($lines[$i]);
            if ($line === '') { $i++; continue; }

            $hasKeyword = $this->lineMatchesAnyKeyword($line, $allKeywords);
            $hasMoney = $this->lineHasMoney($line);

            if (! $hasKeyword) { $i++; continue; }

            // Inline form: keyword + value on same line.
            if ($hasMoney) {
                $amount = $this->extractAmountFromLine($line);
                if ($amount !== null) {
                    $map[strtolower($line)] = $amount;
                }
                $i++;
                continue;
            }

            // Stacked form: collect consecutive label-only keyword lines.
            $labels = [$line];
            $j = $i + 1;
            while ($j < $n) {
                $l = trim($lines[$j]);
                if ($l === '') { $j++; continue; }
                $isLabelOnly = $this->lineMatchesAnyKeyword($l, $allKeywords)
                    && ! $this->lineHasMoney($l);
                if (! $isLabelOnly) break;
                $labels[] = $l;
                $j++;
            }

            // Then collect the SAME number of value lines below.
            $values = [];
            $k = $j;
            while ($k < $n && count($values) < count($labels)) {
                $l = trim($lines[$k]);
                if ($l === '') { $k++; continue; }
                $amt = $this->extractAmountFromLine($l);
                if ($amt === null) break;
                $values[] = $amt;
                $k++;
            }

            if (count($values) === count($labels)) {
                foreach ($labels as $idx => $label) {
                    $map[strtolower($label)] = $values[$idx];
                }
                $i = $k;
                continue;
            }

            // Stacking didn't pair cleanly. Try a wider per-label fallback:
            // walk forward looking for the FIRST money line, stopping at any
            // OTHER label-only keyword (which would belong to a different field).
            // This catches simple "Total: <blank> <stuff> <amount>" cases.
            foreach ($labels as $label) {
                if (isset($map[strtolower($label)])) continue;
                for ($p = $j; $p < $n; $p++) {
                    $l = trim($lines[$p]);
                    if ($l === '') continue;
                    $isOtherLabel = $this->lineMatchesAnyKeyword($l, $allKeywords)
                        && ! $this->lineHasMoney($l);
                    if ($isOtherLabel) break;
                    $amt = $this->extractAmountFromLine($l);
                    if ($amt !== null) {
                        $map[strtolower($label)] = $amt;
                        break;
                    }
                }
            }

            $i = $j;
        }

        return $map;
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
     * Reference / invoice / receipt number. Handles both single-keyword
     * ("Ref: ABC") and double-keyword ("No. Resit: ABC", "Invoice No: ABC")
     * forms. Skips lines where the value would just be another label word
     * (e.g. capturing "RESIT" when the real ID is on the line after).
     */
    private function findReference(array $lines): ?string
    {
        $labelWords = ['RUJUKAN', 'RESIT', 'REFERENCE', 'INVOICE', 'RECEIPT', 'INV', 'REF', 'NUMBER', 'NO', 'BILL', 'TICKET', 'FOLIO'];

        $patterns = [
            // Two-word forms: "No. Resit ABC", "No. Invoice ABC", "Invoice No: ABC", "Receipt No ABC", "Resit No ABC"
            '/(?:no\.?\s+(?:resit|invoice|inv|ref|reference|receipt|bill|ticket|folio)|(?:invoice|receipt|resit|inv|ref|reference|bill|ticket|folio)\s+no\.?)\s*[:#\-]?\s*([A-Z0-9][A-Z0-9\-\/_]{2,30})/i',
            // Single-keyword with an explicit separator: "Ref: ABC", "Invoice #INV-001", "Invoice-ABC123"
            '/\b(?:invoice|inv|reference|resit|receipt|ref|order id|ticket no|bill no|folio no|transaction ref|tax invoice)\s*[#:\-]?\s*([A-Z0-9][A-Z0-9\-\/_]{2,30})/i',
        ];
        foreach ($lines as $index => $line) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $line, $m)) {
                    $candidate = trim($m[1]);
                    if (in_array(strtoupper($candidate), $labelWords, true)) continue;
                    if (preg_match('/^[A-Z0-9]+\-/i', $candidate) || mb_strlen($candidate) >= 6) {
                        return strtoupper($candidate);
                    }
                }
            }

            if (preg_match('/^\s*(?:invoice no|receipt no|ticket no|bill no|folio no|tax invoice)\s*:?\s*$/i', $line)) {
                for ($i = $index + 1; $i < count($lines); $i++) {
                    $candidate = trim($lines[$i]);
                    if ($candidate === '') continue;
                    if (in_array(strtoupper($candidate), $labelWords, true)) continue;
                    if (preg_match('/^[A-Z0-9][A-Z0-9\-\/_]{2,30}$/i', $candidate)) {
                        return strtoupper($candidate);
                    }
                    break;
                }
            }
        }
        return null;
    }

    /**
     * Extract individual line items from the body of the receipt.
     *
     * Heuristic: a line item ends in a money-shaped number, contains some
     * descriptive text BEFORE the price, and is NOT a totals/tax/skip line.
     * If the line has 3 trailing numbers we treat them as `qty / unit / amount`.
     *
     * Output: [{description, amount, quantity?, unit_amount?}].
     * Tesseract accuracy for this is best-effort (~70-80% on cleanly printed
     * Malaysian POS receipts). Wonky photos and unusual layouts will under-extract.
     *
     * @return array<int, array{description: string, amount: float, quantity?: float, unit_amount?: float}>
     */
    private function findItems(array $lines): array
    {
        $monospaceItems = $this->findMonospaceReceiptItems($lines);
        if (! empty($monospaceItems)) {
            return $monospaceItems;
        }

        $generatedPdfItems = $this->findGeneratedPdfTableItems($lines);
        if (! empty($generatedPdfItems)) {
            return $generatedPdfItems;
        }

        $items = [];

        // Per-line skip keywords: header rows and known-non-item words.
        // (We don't include TOTAL/SUBTOTAL here because we use them as a HARD
        //  STOP — once we see one we leave the loop entirely, since on every
        //  layout we've seen the totals come AFTER the items.)
        $skipKeywords = [
            ...self::TAX_KEYWORDS,
            ...self::SKIP_KEYWORDS,
            'date', 'tarikh', 'time', 'masa', 'tel', 'phone',
            'kuantiti', 'harga seunit', // BM table headers
            'qty', 'price', 'amount', 'description', 'item no',
            'pelanggan', 'customer', 'kaedah', 'bank', 'status',
            'terima kasih', 'thank you',
            // Invoice-specific cruft AFTER the totals section
            'credit', 'balance', 'transactions', 'transaction', 'gateway',
            'discount', 'payment', 'paid amount', 'amount paid',
            'computer generated', 'powered by', 'no signature',
        ];

        // Optional currency prefix glued or spaced before the amount.
        $cur = '(?:RM|MYR|SGD|USD|EUR|GBP|S\$|US\$|\$|£|€)';

        // Item-number prefix: "1. ", "1) ", or bare "1 " (followed by a letter so we
        // don't accidentally eat a real qty). Optional.
        $prefix = '(?:\d+[\.\)]\s+|\d+\s+(?=[A-Za-z]))?';

        // Pattern A: "<desc> <qty> <unit> <amount>" with optional leading item-number.
        // qty is a bare integer; unit and amount are money-shaped (NN.NN).
        // Currency may be glued to amount ("RM44.70") or absent.
        $tableRowRegex = '/^' . $prefix . '(.+?)\s+(\d+)\s+' . $cur . '?\s*(\d+(?:,\d{3})*\.\d{2})\s+' . $cur . '?\s*(\d+(?:,\d{3})*\.\d{2})\s*$/i';

        // Pattern A2: stacked generated-PDF table row where description is on
        // previous line(s), followed by "<qty> <unit> <amount>".
        $stackedTableValueRegex = '/^(\d+)\s+' . $cur . '?\s*(\d+(?:,\d{3})*\.\d{2})\s+' . $cur . '?\s*(\d+(?:,\d{3})*\.\d{2})\s*$/i';

        // Pattern B: "<desc> [currency]<amount>" — simple list-style.
        $simpleRowRegex = '/^' . $prefix . '(.+?)\s+' . $cur . '?\s*(\d+(?:,\d{3})*\.\d{2})\s*$/i';

        // Buffer of accumulated description fragments. Many PDFs produce stacked
        // layouts where the description spans multiple lines and the amount is
        // on its own line below — we pair them up when we see a money-only line.
        $pendingDescription = '';

        foreach ($lines as $line) {
            // HARD STOP at the totals section — everything after is metadata
            // (subtotal, tax, total, transaction history, footer cruft).
            // We require a money amount on the line so we don't false-trip on
            // table HEADERS like "Description    Total" which carry the keyword
            // but no value.
            if ($this->lineHasMoney($line) && $this->lineMatchesAnyKeyword($line, [...self::SUBTOTAL_KEYWORDS, ...self::TOTAL_KEYWORDS])) {
                break;
            }

            // STACKED-TOTALS STOP: a label-style line ending with ":" that
            // carries a totals keyword (e.g. "Subtotal:", "Total Paid:").
            // The actual values come on subsequent lines — past this point
            // we shouldn't extract any "items".
            if (
                preg_match('/[A-Za-z][A-Za-z\s\(\)%\d\.]*:\s*$/u', $line)
                && $this->lineMatchesAnyKeyword($line, [...self::SUBTOTAL_KEYWORDS, ...self::TOTAL_KEYWORDS])
            ) {
                break;
            }

            if ($this->lineMatchesAnyKeyword($line, $skipKeywords)) {
                $pendingDescription = '';
                continue;
            }

            $trimmedLine = trim($line);
            if ($trimmedLine === '') continue;

            $description = null;
            $amount = null;
            $quantity = null;
            $unitAmount = null;

            // Pattern A: full table row with qty + unit + amount on one line.
            if (preg_match($tableRowRegex, $line, $m)) {
                $qty = (float) str_replace(',', '', $m[2]);
                $unit = (float) str_replace(',', '', $m[3]);
                $amt = (float) str_replace(',', '', $m[4]);
                if ($qty > 0 && $unit > 0 && abs(round($qty * $unit, 2) - $amt) < 0.02) {
                    $description = trim($m[1]);
                    $quantity = $qty;
                    $unitAmount = $unit;
                    $amount = $amt;
                }
            }

            // Pattern A2: description already buffered, current row is only
            // the table values. Common in selectable-text PDFs where a long
            // description wraps before the quantity/unit/amount columns.
            if ($description === null && $pendingDescription !== '' && preg_match($stackedTableValueRegex, $line, $m)) {
                $qty = (float) str_replace(',', '', $m[1]);
                $unit = (float) str_replace(',', '', $m[2]);
                $amt = (float) str_replace(',', '', $m[3]);
                if ($qty > 0 && $unit > 0 && abs(round($qty * $unit, 2) - $amt) < 0.02) {
                    $description = $pendingDescription;
                    $quantity = $qty;
                    $unitAmount = $unit;
                    $amount = $amt;
                }
            }

            // Pattern B: simple "<desc> <amount>" inline row.
            if ($description === null && preg_match($simpleRowRegex, $line, $m)) {
                $description = trim($m[1]);
                $amount = (float) str_replace(',', '', $m[2]);
            }

            // Pattern C: STACKED format. The current line is JUST a money amount
            // (with optional currency prefix), and we have a non-empty description
            // buffer accumulated from previous lines. Pair them up.
            //
            // Examples:
            //   2x Flat White (Oat Milk)
            //   30.00                    ← current line, fires this branch
            if ($description === null && $this->isMoneyOnlyLine($trimmedLine) && $pendingDescription !== '') {
                $amt = $this->extractAmountFromLine($trimmedLine);
                if ($amt !== null) {
                    $description = $pendingDescription;
                    $amount = $amt;
                }
            }

            // None of the patterns matched. Treat the line as a description fragment
            // and accumulate it into the buffer for the eventual stacked-money pairing.
            if ($description === null) {
                if (preg_match('/[A-Za-z]/', $trimmedLine) && mb_strlen($trimmedLine) >= 3) {
                    $pendingDescription = $pendingDescription === ''
                        ? $trimmedLine
                        : $pendingDescription.' '.$trimmedLine;
                }
                continue;
            }

            // Reset description buffer once we've consumed it (for any pattern).
            $pendingDescription = '';

            if (! $this->isUsefulItemDescription($description)) continue;

            $description = trim(preg_replace('/\s+/', ' ', $description));

            $item = [
                'description' => $description,
                'amount' => $amount,
            ];
            if ($quantity !== null && $unitAmount !== null) {
                $item['quantity'] = $quantity;
                $item['unit_amount'] = $unitAmount;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Handles common monospaced Malaysian receipt screenshots where the item
     * name wraps across lines and quantity detail appears on a later line:
     *
     *   WATSONS WET        17.80
     *   WIPES 3X10S
     *   2 x 8.90
     *
     * It also covers simple invoice tables and hotel folios with qty/unit/amount.
     *
     * @return array<int, array{description: string, amount: float, quantity?: float, unit_amount?: float}>
     */
    private function findMonospaceReceiptItems(array $lines): array
    {
        $items = [];
        $pending = [];
        $openIndex = null;
        $seenItem = false;
        $afterHeader = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^[\-=]{5,}$/', $line)) continue;

            if ($seenItem && $this->lineMatchesAnyKeyword($line, [...self::SUBTOTAL_KEYWORDS, ...self::TOTAL_KEYWORDS])) {
                break;
            }

            if ($this->isReceiptMetadataLine($line)) {
                $pending = [];
                $openIndex = null;
                $afterHeader = true;
                continue;
            }

            if (preg_match('/^\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}(?:\s+\d{1,2}:\d{2}(?::\d{2})?)?$/', $line)) {
                $pending = [];
                $openIndex = null;
                $afterHeader = true;
                continue;
            }

            if (preg_match('/^=\s*(?:RM|MYR)?\s*\d+(?:,\d{3})*\.\d{2}$/i', $line)) {
                if (! empty($pending)) {
                    $pending[] = $line;
                }
                continue;
            }

            if ($openIndex !== null && preg_match('/^@\s*(?:RM|MYR)?\s*(\d+(?:,\d{3})*\.\d{2})(.*)$/i', $line, $m)) {
                $items[$openIndex]['description'] .= ' '.$line;
                if (! isset($items[$openIndex]['unit_amount'])) {
                    $items[$openIndex]['unit_amount'] = (float) str_replace(',', '', $m[1]);
                }
                continue;
            }

            if ($openIndex !== null && preg_match('/^(\d+(?:\.\d+)?)\s*x\s*(?:RM\s*)?(\d+(?:,\d{3})*\.\d{2})/i', $line, $m)) {
                $qty = (float) $m[1];
                $unit = (float) str_replace(',', '', $m[2]);
                $items[$openIndex]['quantity'] = $qty;
                $items[$openIndex]['unit_amount'] = $unit;
                $items[$openIndex]['amount'] = round($qty * $unit, 2);
                $openIndex = null;
                continue;
            }

            if (preg_match('/^\[\+\]\s*(.+?)\s+(\d+(?:,\d{3})*\.\d{2})$/i', $line, $m) && $openIndex !== null) {
                $items[$openIndex]['description'] .= ' [+] '.trim($m[1]);
                $items[$openIndex]['amount'] += (float) str_replace(',', '', $m[2]);
                continue;
            }

            $parsed = $this->parseMonospaceItemLine($line, $pending);
            if ($parsed !== null) {
                if ($this->lineMatchesAnyKeyword($line, self::TAX_KEYWORDS) && ! isset($parsed['quantity'], $parsed['unit_amount'])) {
                    $pending = [];
                    $openIndex = null;
                    continue;
                }

                if (! $this->isUsefulItemDescription($parsed['description'])) {
                    $pending = [];
                    $openIndex = null;
                    continue;
                }

                $items[] = $parsed;
                $openIndex = array_key_last($items);
                $seenItem = true;
                $pending = [];
                continue;
            }

            if ($seenItem || ($afterHeader && $this->looksLikeItemDescriptionFragment($line))) {
                if ($openIndex !== null && ! $this->lineHasMoney($line)) {
                    $items[$openIndex]['description'] = trim($items[$openIndex]['description'].' '.$line);
                    continue;
                }

                if (! $this->lineMatchesAnyKeyword($line, [...self::TAX_KEYWORDS, ...self::SKIP_KEYWORDS])) {
                    $pending[] = $line;
                }
            }
        }

        return array_values(array_map(function (array $item) {
            $item['description'] = $this->cleanItemDescription($item['description']);
            return $item;
        }, $items));
    }

    private function parseMonospaceItemLine(string $line, array $pending): ?array
    {
        $cur = '(?:RM|MYR|SGD|USD|EUR|GBP|S\$|US\$|\$|£|€)';
        $amount = '(\d+(?:,\d{3})*\.\d{2})';
        $pendingDescription = trim(implode(' ', $pending));

        if (preg_match('/^\d{2}\/\d{2}\/\d{4}\s+(.+?)\s+(\d+(?:\.\d+)?)\s+'.$amount.'\s+'.$amount.'$/i', $line, $m)) {
            return [
                'description' => trim($m[1]),
                'amount' => (float) str_replace(',', '', $m[4]),
                'quantity' => (float) $m[2],
                'unit_amount' => (float) str_replace(',', '', $m[3]),
            ];
        }

        if (preg_match('/^(.+?)\s+(\d+(?:\.\d+)?)\s+'.$amount.'\s+'.$amount.'$/i', $line, $m)) {
            return [
                'description' => trim($m[1]),
                'amount' => (float) str_replace(',', '', $m[4]),
                'quantity' => (float) $m[2],
                'unit_amount' => (float) str_replace(',', '', $m[3]),
            ];
        }

        if (preg_match('/^(\d+)X?\s+(.+?)\s+'.$amount.'$/i', $line, $m)) {
            return [
                'description' => trim($m[2]),
                'amount' => (float) str_replace(',', '', $m[3]),
                'quantity' => (float) $m[1],
            ];
        }

        if (preg_match('/^(.+?)\s+(\d+(?:\.\d+)?)\s+'.$amount.'$/i', $line, $m)) {
            $qty = (float) $m[2];
            $amt = (float) str_replace(',', '', $m[3]);
            $description = trim($m[1]);

            if (abs($qty - $amt) < 0.001 && $qty >= 50) {
                return [
                    'description' => $this->combinePendingDescription($pendingDescription, $description),
                    'amount' => $amt,
                ];
            }

            return [
                'description' => $this->combinePendingDescription($pendingDescription, $description),
                'amount' => $amt,
                'quantity' => $qty,
                'unit_amount' => $qty > 0 ? round($amt / $qty, 2) : null,
            ];
        }

        if (preg_match('/^(.+?)\s+'.$cur.'?\s*'.$amount.'$/i', $line, $m)) {
            return [
                'description' => $this->combinePendingDescription($pendingDescription, trim($m[1])),
                'amount' => (float) str_replace(',', '', $m[2]),
            ];
        }

        return null;
    }

    private function combinePendingDescription(string $pending, string $description): string
    {
        return trim($pending === '' ? $description : $pending.' '.$description);
    }

    private function cleanItemDescription(string $description): string
    {
        $description = trim(preg_replace('/\s+/', ' ', $description));
        $description = preg_replace('/^\d+\.\s+/', '', $description);
        $description = preg_replace('/^\d+\s+(?=[A-Za-z])/', '', $description);
        $description = preg_replace('/\b([A-Z][A-Z\'-]+)(\d)(?!\.)\b/u', '$1', $description);
        $description = preg_replace('/\s+\(\s*$/', '', $description);
        return trim($description);
    }

    private function isReceiptMetadataLine(string $line): bool
    {
        $lower = strtolower($line);
        if (preg_match('/^(receipt|invoice|inv no|date|member id|cashier|salesperson|time|table no|bill no|ticket no|entry|exit|duration|payment|card no|approval code|account no|statement date|payment date|status|transaction ref|co\. reg|sst id|sst registration|guest name|nationality|check-in|check-out|folio no|item code|item\s+qty)\b/i', $line)) {
            return true;
        }

        if (str_contains($lower, 'description') && (str_contains($lower, 'qty') || str_contains($lower, 'amount') || str_contains($lower, 'price'))) {
            return true;
        }

        return false;
    }

    private function looksLikeItemDescriptionFragment(string $line): bool
    {
        if (preg_match('/^=\s*(?:RM|MYR)?\s*\d+(?:,\d{3})*\.\d{2}$/i', trim($line))) return false;
        if (! preg_match('/[A-Za-z]/', $line)) return false;
        if (mb_strlen($line) < 2) return false;
        if ($this->lineMatchesAnyKeyword($line, [...self::TOTAL_KEYWORDS, ...self::SUBTOTAL_KEYWORDS, ...self::TAX_KEYWORDS])) return false;
        return true;
    }

    /**
     * Poppler's pdftotext can emit generated invoice tables vertically:
     *
     * Description
     * Long product line
     * Wrapped product line
     * Qty
     * Unit price
     * Amount
     * 1
     * RM20.00
     * RM20.00
     *
     * The generic line parser sees the later totals values as separate rows.
     * This pre-pass reconstructs the item from the table header/value order.
     *
     * @return array<int, array{description: string, amount: float, quantity: float, unit_amount: float}>
     */
    private function findGeneratedPdfTableItems(array $lines): array
    {
        $descriptionIndex = null;
        foreach ($lines as $index => $line) {
            if (strtolower(trim($line)) === 'description') {
                $descriptionIndex = $index;
                break;
            }
        }
        if ($descriptionIndex === null) return [];

        $qtyIndex = $this->findNextExactLine($lines, 'qty', $descriptionIndex + 1);
        $unitIndex = $this->findNextExactLine($lines, 'unit price', ($qtyIndex ?? $descriptionIndex) + 1);
        $amountIndex = $this->findNextExactLine($lines, 'amount', ($unitIndex ?? $descriptionIndex) + 1);
        if ($qtyIndex === null || $unitIndex === null || $amountIndex === null) return [];

        $descriptionParts = [];
        for ($i = $descriptionIndex + 1; $i < $qtyIndex; $i++) {
            $part = trim($lines[$i]);
            if ($part === '') continue;
            if (! preg_match('/[A-Za-z]/', $part)) continue;
            $descriptionParts[] = $part;
        }
        $description = trim(preg_replace('/\s+/', ' ', implode(' ', $descriptionParts)));
        if (! $this->isUsefulItemDescription($description)) return [];

        $valueLines = array_slice($lines, $amountIndex + 1);
        $qty = null;
        $unit = null;
        $amount = null;

        foreach ($valueLines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') continue;
            if ($this->lineMatchesAnyKeyword($trimmed, [...self::SUBTOTAL_KEYWORDS, ...self::TOTAL_KEYWORDS, 'payment history'])) {
                break;
            }

            if ($qty === null && preg_match('/^\d+(?:\.\d+)?$/', $trimmed)) {
                $qty = (float) $trimmed;
                continue;
            }

            if ($qty !== null && $unit === null && $this->isMoneyOnlyLine($trimmed)) {
                $unit = $this->extractAmountFromLine($trimmed);
                continue;
            }

            if ($qty !== null && $unit !== null && $amount === null && $this->isMoneyOnlyLine($trimmed)) {
                $amount = $this->extractAmountFromLine($trimmed);
                break;
            }
        }

        if ($qty === null || $unit === null || $amount === null) return [];
        if ($qty <= 0 || $unit <= 0 || abs(round($qty * $unit, 2) - $amount) >= 0.02) return [];

        return [[
            'description' => $description,
            'amount' => $amount,
            'quantity' => $qty,
            'unit_amount' => $unit,
        ]];
    }

    private function findNextExactLine(array $lines, string $needle, int $start): ?int
    {
        for ($i = $start; $i < count($lines); $i++) {
            if (strtolower(trim($lines[$i])) === $needle) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Pull the right-most numeric amount from the given line. Receipts
     * universally place the amount at the right edge, so the LAST number
     * in the line is the safest pick.
     *
     * Handles three formats:
     *   - English: 5,350.00 / 1,234,567.89 / 78.88
     *   - European thousand-separated integers (IDR / EU receipts):
     *     10.600.000 / 1.375.000  → returned as 10600000 / 1375000
     *
     * European format is tried FIRST because "10.600.000" would otherwise be
     * mis-parsed as "10.60" or "0.00" by the English regex.
     */
    private function extractAmountFromLine(string $line): ?float
    {
        // 1. European thousand-separated integers (≥2 dot-thousand groups,
        //    e.g. Indonesian Rupiah "12.500.000"). Each post-dot group must
        //    be exactly 3 digits, so this won't false-fire on "10.50".
        if (preg_match_all('/\d{1,3}(?:\.\d{3}){2,}/', $line, $m)) {
            $last = end($m[0]);
            return (float) str_replace('.', '', $last);
        }

        // 2. English decimal format with optional comma thousand separators.
        if (preg_match_all('/(\d{1,3}(?:,\d{3})*\.\d{2}|\d+\.\d{2})/', $line, $m)) {
            $last = end($m[1]);
            return (float) str_replace(',', '', $last);
        }
        return null;
    }

    /**
     * Does the line look like it contains a money amount in any supported
     * format (English or European)? Cheaper than calling extractAmountFromLine
     * when we just need a yes/no.
     */
    private function lineHasMoney(string $line): bool
    {
        return (bool) preg_match('/\d+\.\d{2}|\d{1,3}(?:\.\d{3}){2,}/', $line);
    }

    /**
     * Is the line essentially just a money amount (with optional currency
     * prefix) and nothing else? Used to detect "stacked" item layouts where
     * the amount appears on its own line below the description.
     */
    private function isMoneyOnlyLine(string $line): bool
    {
        $trimmed = trim($line);
        if ($trimmed === '') return false;
        return (bool) preg_match(
            '/^(?:RM|MYR|SGD|USD|EUR|GBP|IDR|Rp|S\$|US\$|\$|£|€)?\s*(?:\d{1,3}(?:[,\.]\d{3})*(?:\.\d{2})?|\d+\.\d{2})\s*$/i',
            $trimmed
        );
    }

    /**
     * A line of text is a "useful" item description only if, after stripping
     * currency tokens, ≥3 characters of letters remain. Filters out
     * accidental matches like "MYR 5,350.00" (description = "MYR").
     */
    private function isUsefulItemDescription(string $description): bool
    {
        if ($description === '' || ! preg_match('/[A-Za-z]/', $description)) return false;
        $stripped = trim(preg_replace('/\b(RM|MYR|SGD|USD|EUR|GBP|IDR|Rp|S\$|US\$)\b|[\$£€]/i', '', $description));
        return mb_strlen($stripped) >= 3 && (bool) preg_match('/[A-Za-z]/', $stripped);
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
