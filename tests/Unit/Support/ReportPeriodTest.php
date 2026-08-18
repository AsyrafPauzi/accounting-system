<?php

namespace Tests\Unit\Support;

use App\Support\ReportPeriod;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ReportPeriodTest extends TestCase
{
    private function freeze(): Carbon
    {
        return Carbon::parse('2026-08-18');
    }

    public function test_this_month_runs_from_first_to_today(): void
    {
        $r = ReportPeriod::range('this_month', null, null, $this->freeze());
        $this->assertSame('2026-08-01', $r['date_from']);
        $this->assertSame('2026-08-18', $r['date_to']);
        $this->assertSame('this_month', $r['preset']);
    }

    public function test_last_month_is_full_july_when_today_is_august(): void
    {
        $r = ReportPeriod::range('last_month', null, null, $this->freeze());
        $this->assertSame('2026-07-01', $r['date_from']);
        $this->assertSame('2026-07-31', $r['date_to']);
    }

    public function test_this_quarter_is_jul_to_today(): void
    {
        $r = ReportPeriod::range('this_quarter', null, null, $this->freeze());
        $this->assertSame('2026-07-01', $r['date_from']);
        $this->assertSame('2026-08-18', $r['date_to']);
    }

    public function test_ytd_is_jan_1_to_today(): void
    {
        $r = ReportPeriod::range('ytd', null, null, $this->freeze());
        $this->assertSame('2026-01-01', $r['date_from']);
        $this->assertSame('2026-08-18', $r['date_to']);
    }

    public function test_custom_dates_win_when_preset_missing(): void
    {
        $r = ReportPeriod::range(null, '2026-03-01', '2026-03-31', $this->freeze());
        $this->assertSame('custom', $r['preset']);
        $this->assertSame('2026-03-01', $r['date_from']);
        $this->assertSame('2026-03-31', $r['date_to']);
    }

    public function test_sst_period_in_august_is_jul_aug(): void
    {
        $r = ReportPeriod::sstPeriod($this->freeze());
        $this->assertSame('2026-07-01', $r['date_from']);
        $this->assertSame('2026-08-31', $r['date_to']);
    }

    public function test_previous_of_same_length(): void
    {
        $r = ReportPeriod::previousOfSameLength('2026-08-01', '2026-08-18');
        $this->assertSame('2026-07-14', $r['date_from']);
        $this->assertSame('2026-07-31', $r['date_to']);
    }

    public function test_last_year_same_dates(): void
    {
        $r = ReportPeriod::lastYearSameDates('2026-08-01', '2026-08-18');
        $this->assertSame('2025-08-01', $r['date_from']);
        $this->assertSame('2025-08-18', $r['date_to']);
    }

    public function test_as_of_last_month_is_july_31(): void
    {
        $r = ReportPeriod::asOf('last_month', null, $this->freeze());
        $this->assertSame('2026-07-31', $r['as_of']);
    }
}
