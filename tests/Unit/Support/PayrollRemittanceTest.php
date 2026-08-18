<?php

namespace Tests\Unit\Support;

use App\Support\PayrollRemittance;
use PHPUnit\Framework\TestCase;

class PayrollRemittanceTest extends TestCase
{
    public function test_credit_balance_returns_the_net_payable(): void
    {
        $this->assertSame(2300.0, PayrollRemittance::creditBalance(100, 2400));
    }

    public function test_credit_balance_hides_a_cleared_payable_as_zero(): void
    {
        $this->assertSame(0.0, PayrollRemittance::creditBalance(2400, 2400));
    }
}
