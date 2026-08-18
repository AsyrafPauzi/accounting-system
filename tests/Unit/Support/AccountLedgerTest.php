<?php

namespace Tests\Unit\Support;

use App\Support\AccountLedger;
use PHPUnit\Framework\TestCase;

class AccountLedgerTest extends TestCase
{
    public function test_signed_movement_for_asset_accounts_is_debit_minus_credit(): void
    {
        $this->assertSame(100.0, AccountLedger::signedMovement('asset', 150, 50));
        $this->assertSame(-25.0, AccountLedger::signedMovement('asset', 0, 25));
    }

    public function test_signed_movement_for_liability_accounts_is_credit_minus_debit(): void
    {
        $this->assertSame(80.0, AccountLedger::signedMovement('liability', 20, 100));
        $this->assertSame(-40.0, AccountLedger::signedMovement('liability', 40, 0));
    }

    public function test_running_balances_continue_across_lines(): void
    {
        $opening = 1000.0;
        $lines = [
            ['id' => 1, 'debit' => 200, 'credit' => 0],
            ['id' => 2, 'debit' => 0, 'credit' => 50],
        ];

        $balances = AccountLedger::runningBalances('asset', $opening, $lines);

        $this->assertSame(1200.0, $balances[1]);
        $this->assertSame(1150.0, $balances[2]);
    }

    public function test_running_balances_paginated_page_two_continues_from_page_one(): void
    {
        $lines = [
            ['id' => 1, 'debit' => 100, 'credit' => 0],
            ['id' => 2, 'debit' => 0, 'credit' => 30],
        ];

        $all = AccountLedger::runningBalances('asset', 0.0, $lines);
        $pageSize = 1;
        $pageTwoOpening = $all[1];
        $pageTwoLines = [array_slice($lines, 1)[0]];
        $pageTwoBalances = AccountLedger::runningBalances('asset', $pageTwoOpening, $pageTwoLines);

        $this->assertSame(70.0, $pageTwoBalances[2]);
    }
}
