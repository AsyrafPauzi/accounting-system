<?php

namespace Tests\Feature\Practice;

use App\Services\Practice\PracticeMetricsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Pins the pure-PHP aggregation contract of PracticeMetricsService —
 * `aggregates`, `clientsNeedingAttention`, `upcomingDeadlines`. The
 * per-tenant SQL paths (computeAgingBuckets, computeRevenueTrend) are
 * tested separately at the controller / smoke level where a real
 * tenant DB is available.
 */
class PracticeMetricsServiceTest extends TestCase
{
    private PracticeMetricsService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new PracticeMetricsService();
    }

    private function row(array $overrides = []): array
    {
        return array_merge([
            'tenant_id'        => 'tenant-'.uniqid('', true),
            'name'             => 'Demo Co',
            'plan'             => 'startup',
            'plan_status'      => 'active',
            'permission_level' => 'admin',
            'revenue_mtd'      => 0.0,
            'ar_outstanding'   => 0.0,
            'overdue_count'    => 0,
            'last_activity_at' => null,
            'ar_aging'         => [
                'current'      => 0.0,
                'days_1_30'    => 0.0,
                'days_31_60'   => 0.0,
                'days_61_90'   => 0.0,
                'days_90_plus' => 0.0,
            ],
            'revenue_trend'    => $this->emptyTrend(),
            'expenses_mtd'     => 0.0,
            'cash_balance'     => 0.0,
            'financial_year_start_month' => 1,
            'health'           => 'good',
        ], $overrides);
    }

    private function emptyTrend(): array
    {
        $out = [];
        for ($i = PracticeMetricsService::TREND_MONTHS - 1; $i >= 0; $i--) {
            $start = Carbon::now()->startOfMonth()->subMonths($i);
            $out[] = [
                'iso'     => $start->format('Y-m'),
                'month'   => $start->format('M Y'),
                'revenue' => 0.0,
            ];
        }
        return $out;
    }

    public function test_aggregates_sums_across_clients_and_aligns_trend_columns(): void
    {
        // Build two synthetic clients with non-trivial trend / aging.
        $trendA = $this->emptyTrend();
        $trendA[count($trendA) - 1]['revenue'] = 1000.0; // current month
        $trendA[count($trendA) - 2]['revenue'] = 2500.0;

        $trendB = $this->emptyTrend();
        $trendB[count($trendB) - 1]['revenue'] = 700.0;
        $trendB[count($trendB) - 3]['revenue'] = 400.0;

        $rows = collect([
            $this->row([
                'name'           => 'Alpha',
                'revenue_mtd'    => 1000,
                'ar_outstanding' => 5000,
                'overdue_count'  => 2,
                'cash_balance'   => 12345,
                'expenses_mtd'   => 200,
                'revenue_trend'  => $trendA,
                'ar_aging'       => [
                    'current'      => 1000,
                    'days_1_30'    => 500,
                    'days_31_60'   => 200,
                    'days_61_90'   => 0,
                    'days_90_plus' => 0,
                ],
                'health'         => 'watch',
            ]),
            $this->row([
                'name'           => 'Bravo',
                'revenue_mtd'    => 700,
                'ar_outstanding' => 1500,
                'overdue_count'  => 0,
                'cash_balance'   => 5000,
                'expenses_mtd'   => 100,
                'revenue_trend'  => $trendB,
                'ar_aging'       => [
                    'current'      => 1500,
                    'days_1_30'    => 0,
                    'days_31_60'   => 0,
                    'days_61_90'   => 0,
                    'days_90_plus' => 0,
                ],
                'health'         => 'good',
            ]),
        ]);

        $agg = $this->svc->aggregates($rows);

        $this->assertSame(2, $agg['total_clients']);
        $this->assertEquals(1700.0, $agg['total_revenue_mtd']);
        $this->assertEquals(6500.0, $agg['total_ar_outstanding']);
        $this->assertSame(2, $agg['total_overdue_count']);
        $this->assertEquals(17345.0, $agg['total_cash_balance']);
        $this->assertEquals(300.0, $agg['total_expenses_mtd']);

        // Aging totals — Alpha + Bravo, summed bucket-wise.
        $this->assertEquals(2500.0, $agg['ar_aging']['current']);
        $this->assertEquals(500.0,  $agg['ar_aging']['days_1_30']);
        $this->assertEquals(200.0,  $agg['ar_aging']['days_31_60']);
        $this->assertEquals(0.0,    $agg['ar_aging']['days_90_plus']);

        // Health distribution.
        $this->assertSame(['good' => 1, 'watch' => 1, 'risk' => 0], $agg['health_distribution']);

        // Trend columns line up by ISO month and stack-sum correctly.
        $this->assertCount(PracticeMetricsService::TREND_MONTHS, $agg['revenue_trend']);
        $current = end($agg['revenue_trend']);
        $this->assertEquals(1700.0, $current['revenue']); // 1000 + 700
        $previous = $agg['revenue_trend'][count($agg['revenue_trend']) - 2];
        $this->assertEquals(2500.0, $previous['revenue']);

        // Top clients by revenue ordered desc.
        $this->assertSame('Alpha', $agg['top_clients_by_revenue'][0]['name']);
        $this->assertSame('Bravo', $agg['top_clients_by_revenue'][1]['name']);
    }

    public function test_aggregates_handles_zero_clients_without_blowing_up(): void
    {
        $agg = $this->svc->aggregates(collect());

        $this->assertSame(0, $agg['total_clients']);
        $this->assertEquals(0.0, $agg['total_revenue_mtd']);
        $this->assertSame(['good' => 0, 'watch' => 0, 'risk' => 0], $agg['health_distribution']);
        $this->assertCount(PracticeMetricsService::TREND_MONTHS, $agg['revenue_trend']);
        foreach ($agg['revenue_trend'] as $point) {
            $this->assertEquals(0.0, $point['revenue']);
        }
        $this->assertSame([], $agg['top_clients_by_revenue']);
    }

    public function test_clients_needing_attention_filters_and_orders_by_urgency(): void
    {
        $rows = collect([
            $this->row(['name' => 'Healthy',  'overdue_count' => 0, 'health' => 'good']),
            $this->row(['name' => 'Mild',     'overdue_count' => 1, 'ar_outstanding' => 800, 'health' => 'watch']),
            $this->row([
                'name'           => 'Stuck',
                'overdue_count'  => 3,
                'ar_outstanding' => 4000,
                'ar_aging'       => ['current' => 0, 'days_1_30' => 0, 'days_31_60' => 0, 'days_61_90' => 0, 'days_90_plus' => 4000.0],
                'health'         => 'risk',
            ]),
            $this->row(['name' => 'Heavy',    'overdue_count' => 7, 'ar_outstanding' => 9000, 'health' => 'risk']),
        ]);

        $top = $this->svc->clientsNeedingAttention($rows, 5);

        // Healthy is filtered out; the remaining 3 are ordered by:
        // overdue desc, then 90+-day AR desc, then total AR desc.
        $this->assertCount(3, $top);
        $this->assertSame('Heavy', $top[0]['name']); // 7 overdue, biggest
        $this->assertSame('Stuck', $top[1]['name']); // 3 overdue + stuck
        $this->assertSame('Mild',  $top[2]['name']);
    }

    public function test_clients_needing_attention_returns_empty_when_all_healthy(): void
    {
        $rows = collect([
            $this->row(['name' => 'A', 'health' => 'good', 'overdue_count' => 0]),
            $this->row(['name' => 'B', 'health' => 'good', 'overdue_count' => 0]),
        ]);

        $this->assertSame([], $this->svc->clientsNeedingAttention($rows));
    }

    public function test_upcoming_deadlines_projects_year_end_for_january_starter_in_november(): void
    {
        // 15 Nov 2027 — financial year ends 31 Dec 2027 (46 days away).
        // CP204 due 31 Jan 2027 (already past). Form C due 31 Jul 2027
        // (also past). So we expect ONLY year_end in the projection.
        Carbon::setTestNow(Carbon::create(2027, 11, 15));

        try {
            $rows = collect([
                $this->row([
                    'name'                       => 'Jan Starter',
                    'financial_year_start_month' => 1,
                ]),
            ]);

            $items = $this->svc->upcomingDeadlines($rows, 90);
            $kinds = array_column($items, 'kind');

            $this->assertContains('year_end', $kinds);
            $this->assertNotContains('cp204', $kinds);
            $this->assertNotContains('form_c', $kinds);
            $this->assertSame('year_end', $items[0]['kind']);
            $this->assertSame('2027-12-31', $items[0]['due']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_upcoming_deadlines_projects_cp204_in_january_after_year_end(): void
    {
        // 10 Jan 2028 — financial year just rolled over (1 Jan 2028 start).
        // CP204 due 31 Jan 2028 (21 days away — within 30d window).
        // Form C for FY2027 was due 31 Jul 2027 (already past).
        // Next year-end is 31 Dec 2028 (357 days — outside 90d).
        Carbon::setTestNow(Carbon::create(2028, 1, 10));

        try {
            $rows = collect([
                $this->row([
                    'name'                       => 'Jan Starter',
                    'financial_year_start_month' => 1,
                ]),
            ]);

            $items = $this->svc->upcomingDeadlines($rows, 90);
            $kinds = array_column($items, 'kind');

            $this->assertContains('cp204', $kinds);
            $this->assertNotContains('year_end', $kinds);
            $this->assertNotContains('form_c', $kinds);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_upcoming_deadlines_projects_form_c_seven_months_after_year_end(): void
    {
        // 15 Jul 2028 — Form C due 31 Jul 2028 (16 days away). The
        // prior year-end was 31 Dec 2027.
        Carbon::setTestNow(Carbon::create(2028, 7, 15));

        try {
            $rows = collect([
                $this->row([
                    'name'                       => 'Jan Starter',
                    'financial_year_start_month' => 1,
                ]),
            ]);

            $items = $this->svc->upcomingDeadlines($rows, 90);
            $kinds = array_column($items, 'kind');

            $this->assertContains('form_c', $kinds);
            $this->assertNotContains('cp204', $kinds);
            // Year-end (31 Dec 2028) is 169 days — outside 90d window.
            $this->assertNotContains('year_end', $kinds);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_upcoming_deadlines_skips_clients_with_invalid_month(): void
    {
        $rows = collect([
            $this->row(['name' => 'Bad', 'financial_year_start_month' => 0]),
            $this->row(['name' => 'Worse', 'financial_year_start_month' => 13]),
        ]);

        $this->assertSame([], $this->svc->upcomingDeadlines($rows, 365));
    }
}
