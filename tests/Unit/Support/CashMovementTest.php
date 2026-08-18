<?php

namespace Tests\Unit\Support;

use App\Support\CashMovement;
use PHPUnit\Framework\TestCase;

class CashMovementTest extends TestCase
{
    public function test_chart_by_month_reduces_debits_and_credits_into_cash_movement(): void
    {
        $chart = CashMovement::chartByMonth([
            ['month' => '2026-07', 'debit' => 1250.50, 'credit' => 200.25],
            ['month' => '2026-08', 'debit' => 900.00, 'credit' => 300.00],
            ['month' => '2026-07', 'debit' => 49.50, 'credit' => 99.75],
        ]);

        $this->assertSame([
            [
                'month' => '2026-07',
                'month_label' => 'Jul 2026',
                'money_in' => 1300.00,
                'money_out' => 300.00,
                'net' => 1000.00,
            ],
            [
                'month' => '2026-08',
                'month_label' => 'Aug 2026',
                'money_in' => 900.00,
                'money_out' => 300.00,
                'net' => 600.00,
            ],
        ], $chart);
    }

    public function test_totals_sum_money_in_money_out_and_net(): void
    {
        $totals = CashMovement::totals([
            ['month' => '2026-07', 'month_label' => 'Jul 2026', 'money_in' => 1300.00, 'money_out' => 300.00, 'net' => 1000.00],
            ['month' => '2026-08', 'month_label' => 'Aug 2026', 'money_in' => 900.00, 'money_out' => 300.00, 'net' => 600.00],
        ]);

        $this->assertSame([
            'money_in' => 2200.00,
            'money_out' => 600.00,
            'net' => 1600.00,
        ], $totals);
    }
}
