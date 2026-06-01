<?php

namespace App\Services\Ocr;

/**
 * Fixes predictable Tesseract confusion errors in money/date contexts.
 *
 * Tesseract reliably gets some things wrong:
 *   - `O` confused with `0`     ("4O.50" → should be "40.50")
 *   - `l` (lowercase L) with `1` ("l4.50" → "14.50")
 *   - `I` (uppercase i) with `1` ("I0.50" → "10.50")
 *   - `S` with `5`              ("2S.50" → "25.50")
 *   - `B` with `8`              (more rare; gated)
 *
 * The fixes are CONTEXT-SCOPED — applied only inside money/date patterns where
 * the surrounding characters confirm the value should be numeric. We never
 * touch plain prose, vendor names, or descriptions, so "BOOKHIVE" stays
 * "BOOKHIVE" rather than becoming "B00KHIVE".
 *
 * Idempotent — running normalize() twice produces the same output as once.
 */
class OcrTextNormalizer
{
    public function normalize(string $text): string
    {
        if ($text === '') return '';

        // 1. Collapse spaced-out currency tokens BEFORE any other fixes.
        //    OCR sometimes reads "RM44.70" as "R M 4 4 . 7 0".
        $text = $this->collapseSpacedCurrency($text);

        // 2. Fix money amounts: numbers with letters O/l/I/S in them.
        $text = $this->fixMoneyAmounts($text);

        // 3. Fix date components.
        $text = $this->fixDateComponents($text);

        return $text;
    }

    /**
     * Two-pass collapse:
     *   Pass 1: "R M 4 4 . 7 0" → "RM44.70" (heavy mangling — drops every space)
     *   Pass 2: "RM 4 4.70"     → "RM 44.70" (only the digits had spaces;
     *                                          preserve the RM-to-amount space)
     *
     * Pass 2 must NOT fire on already-clean "RM 44.70" — we check there's
     * actually an internal space inside the digit run before collapsing.
     */
    private function collapseSpacedCurrency(string $text): string
    {
        // Pass 1: "R M ..." (literal space between R and M) — heavy mangling.
        $text = preg_replace_callback(
            '/\bR\s+M\s*((?:\s*\d){1,7}\s*\.\s*\d\s*\d)\b/',
            fn ($m) => 'RM' . preg_replace('/\s+/', '', $m[1]),
            $text
        ) ?? $text;

        // Pass 2: "RM <digits-with-internal-spaces>.NN" — only collapse the
        // digit portion if it actually has internal whitespace; leave the
        // single space between RM and the number alone.
        $text = preg_replace_callback(
            '/(\bRM)(\s+)((?:\d\s*){1,7}\.\s*\d\s*\d)\b/',
            function ($m) {
                $digits = $m[3];
                $cleanDigits = preg_replace('/\s+/', '', $digits);
                if ($cleanDigits === $digits) return $m[0];
                return $m[1] . ' ' . $cleanDigits;
            },
            $text
        ) ?? $text;

        return $text;
    }

    /**
     * Fix amounts that look like money but contain letters: "4O.50", "l4.50".
     *
     * Strategy: find tokens that have at least one digit AND one decimal point
     * AND a digit on either side of the decimal. Inside those tokens, swap
     * common-confusion letters for digits.
     */
    private function fixMoneyAmounts(string $text): string
    {
        // Pattern: optional currency, then a token combining digits and the
        // confusable letters, with a decimal point and at least 2 trailing digits.
        // Constraints: must contain at least one DIGIT (so we don't mangle "RMS")
        // and at least one DECIMAL POINT followed by 2 digits.
        return preg_replace_callback(
            '/(?<![A-Za-z])([0-9OlISB]*[OlISB][0-9OlISB]*\.[0-9OlISB]{2,})/',
            function ($m) {
                $token = $m[1];
                // Must contain at least one digit so we don't fire on "OO.OO".
                if (! preg_match('/\d/', $token)) return $token;
                return $this->swapLettersToDigits($token);
            },
            $text
        ) ?? $text;
    }

    /**
     * Fix dates that look like D[D]/M[M]/YYYY etc. but with O/l/I letters.
     * Only fires when the structure (separators + length) looks like a date.
     */
    private function fixDateComponents(string $text): string
    {
        return preg_replace_callback(
            '/(?<![A-Za-z])([0-9OlI]{1,2})([\/\-.])([0-9OlI]{1,2})([\/\-.])([0-9OlI]{2,4})(?![A-Za-z])/',
            function ($m) {
                $day = $this->swapLettersToDigits($m[1]);
                $month = $this->swapLettersToDigits($m[3]);
                $year = $this->swapLettersToDigits($m[5]);

                // Sanity check: post-fix values should be numeric AND in plausible
                // ranges. If not, leave the original alone — better unchanged
                // than corrupted.
                if (! ctype_digit($day) || ! ctype_digit($month) || ! ctype_digit($year)) return $m[0];
                if ((int) $day < 1 || (int) $day > 31) return $m[0];
                if ((int) $month < 1 || (int) $month > 12) return $m[0];
                $yearInt = (int) $year;
                if ($yearInt < 100) $yearInt += 2000;
                if ($yearInt < 2000 || $yearInt > 2100) return $m[0];

                return $day . $m[2] . $month . $m[4] . $year;
            },
            $text
        ) ?? $text;
    }

    /**
     * Swap O→0, l→1, I→1, S→5, B→8 in a token where we KNOW the token should be
     * numeric. Caller is responsible for context-gating this.
     */
    private function swapLettersToDigits(string $token): string
    {
        return strtr($token, [
            'O' => '0',
            'l' => '1',
            'I' => '1',
            'S' => '5',
            'B' => '8',
        ]);
    }
}
