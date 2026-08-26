<?php

namespace Tests\Unit\Services;

use App\Services\FxGainLossService;
use PHPUnit\Framework\TestCase;

class FxGainLossServiceUnrealizedTest extends TestCase
{
    public function test_unrealized_ar_gain_posts_debit_ar_credit_gain(): void
    {
        $lines = (new FxGainLossService)->unrealizedArLines(30.0);

        $this->assertSame([
            ['account_code' => '1100', 'debit' => 30.0, 'credit' => 0],
            ['account_code' => '4200', 'debit' => 0, 'credit' => 30.0],
        ], $lines);
    }

    public function test_unrealized_ap_loss_posts_debit_loss_credit_ap(): void
    {
        $lines = (new FxGainLossService)->unrealizedApLines(25.0);

        $this->assertSame([
            ['account_code' => '4300', 'debit' => 25.0, 'credit' => 0],
            ['account_code' => '2110', 'debit' => 0, 'credit' => 25.0],
        ], $lines);
    }

    public function test_negligible_adjustment_returns_no_lines(): void
    {
        $this->assertSame([], (new FxGainLossService)->unrealizedArLines(0.004));
        $this->assertSame([], (new FxGainLossService)->unrealizedApLines(-0.003));
    }
}
