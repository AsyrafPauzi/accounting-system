<?php

namespace Tests\Unit\Support;

use App\Support\SubscriptionPeriod;
use PHPUnit\Framework\TestCase;

class SubscriptionPeriodTest extends TestCase
{
    public function test_monthly_extends_from_prior_end(): void
    {
        $w = SubscriptionPeriod::nextWindow('monthly', '2026-08-31');
        $this->assertSame('2026-08-31', $w['period_start']);
        $this->assertSame('2026-09-30', $w['period_end']);
    }

    public function test_yearly_extends_from_prior_end(): void
    {
        $w = SubscriptionPeriod::nextWindow('yearly', '2026-08-18');
        $this->assertSame('2026-08-18', $w['period_start']);
        $this->assertSame('2027-08-18', $w['period_end']);
    }

    public function test_grace_deadline(): void
    {
        $this->assertSame('2026-09-07', SubscriptionPeriod::graceDeadline('2026-08-31', 7));
    }

    public function test_is_due_within_lead_window(): void
    {
        $this->assertTrue(SubscriptionPeriod::isDue('2026-08-31', 7, '2026-08-24'));
        $this->assertFalse(SubscriptionPeriod::isDue('2026-08-31', 7, '2026-08-23'));
        $this->assertTrue(SubscriptionPeriod::isDue('2026-08-31', 7, '2026-09-01'));
    }

    public function test_expire_action_active_to_past_due(): void
    {
        $this->assertSame('past_due', SubscriptionPeriod::expireAction('active', '2026-08-31', '2026-09-01', 7));
        $this->assertSame('none', SubscriptionPeriod::expireAction('active', '2026-08-31', '2026-08-31', 7));
    }

    public function test_expire_action_past_due_to_expired(): void
    {
        $this->assertSame('none', SubscriptionPeriod::expireAction('past_due', '2026-08-31', '2026-09-07', 7));
        $this->assertSame('expired', SubscriptionPeriod::expireAction('past_due', '2026-08-31', '2026-09-08', 7));
    }
}
