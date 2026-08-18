<?php

namespace App\Support;

class PayrollRemittance
{
    public static function creditBalance(float $debit, float $credit): float
    {
        return max(0.0, round($credit - $debit, 2));
    }
}
