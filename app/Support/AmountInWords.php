<?php

namespace App\Support;

final class AmountInWords
{
    /** @var list<string> */
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen',
    ];

    /** @var list<string> */
    private const TENS = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    public static function format(float $amount, string $currency = 'MYR'): string
    {
        $whole = (int) floor(abs($amount));
        $cents = (int) round((abs($amount) - $whole) * 100);

        $label = strtoupper($currency) === 'MYR' ? 'Ringgit' : strtoupper($currency);
        $result = self::convert($whole).' '.$label;

        if ($cents > 0) {
            $result .= ' and '.self::convert($cents).' Sen';
        }

        return trim($result).' Only';
    }

    private static function convert(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $parts = [];

        if ($number >= 1_000_000) {
            $parts[] = self::convert((int) floor($number / 1_000_000)).' Million';
            $number %= 1_000_000;
        }

        if ($number >= 1_000) {
            $parts[] = self::convert((int) floor($number / 1_000)).' Thousand';
            $number %= 1_000;
        }

        if ($number >= 100) {
            $parts[] = self::convert((int) floor($number / 100)).' Hundred';
            $number %= 100;
        }

        if ($number >= 20) {
            $parts[] = self::TENS[(int) floor($number / 10)];
            $number %= 10;
        }

        if ($number > 0) {
            $parts[] = self::ONES[$number];
        }

        return trim(implode(' ', array_filter($parts)));
    }
}
