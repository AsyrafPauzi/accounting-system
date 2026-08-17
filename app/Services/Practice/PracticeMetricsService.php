<?php

namespace App\Services\Practice;

use App\Models\Firm;
use App\Models\FirmClient;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Pulls firm-level metrics across every client tenant in one place.
 *
 * Design notes:
 *
 * - Each client lives in its own database (Stancl tenancy). So
 *   computing "total AR across all of this firm's clients" means
 *   iterating, switching tenancy in/out per client, summing locally.
 *   Slow if you have 100 clients but fine for the 5-30 client range
 *   that covers ~all small Malaysian accounting firms.
 *
 * - We deliberately do *one* tenancy switch per client and pull
 *   everything we need in that one call: headline stats, AR aging
 *   buckets, 6-month revenue history. That keeps the per-client cost
 *   close to constant regardless of how many charts the dashboard
 *   wants to render.
 *
 * - Every per-tenant query is wrapped in try/catch so one broken
 *   client (missing table, busted migrations) doesn't take the
 *   console offline.
 *
 * - We deliberately don't cache — a client's books can change on
 *   every login, and "live numbers" is a key promise of the console.
 *   If this becomes hot, swap to a per-firm short-TTL cache here
 *   without touching the controller.
 */
class PracticeMetricsService
{
    /** Number of months of revenue history we expose to the trend chart. */
    public const TREND_MONTHS = 6;

    /** AR aging bucket boundaries, in days past due. */
    public const AGING_BUCKETS = [
        'current'    => [0, 0],     // not yet overdue
        'days_1_30'  => [1, 30],
        'days_31_60' => [31, 60],
        'days_61_90' => [61, 90],
        'days_90_plus' => [91, null],
    ];

    /**
     * Build the per-client row used by the Practice dashboard list.
     *
     * Each row carries:
     *   - tenant id, display name, plan slug, plan_status
     *   - permission level the firm has on this client
     *   - revenue_mtd, ar_outstanding, overdue_count
     *   - last_activity_at
     *   - ar_aging — keyed bucket totals, RM
     *   - revenue_trend — 6 most recent months, oldest first
     *   - financial_year_start_month — used for deadline projections
     *   - health — derived 'good' | 'watch' | 'risk' tag
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function clientRows(Firm $firm): Collection
    {
        // We pull tenant + subscription + plan up-front so the row
        // mapper doesn't trigger lazy-loading violations (the app
        // disables lazy loading globally for safety).
        $clients = $firm->clients()
            ->with(['tenant.subscription.plan'])
            ->where('status', 'active')
            ->get();

        $monthStart = Carbon::now()->startOfMonth();

        return $clients->map(function (FirmClient $link) use ($monthStart) {
            $tenant = $link->tenant;
            if (! $tenant) {
                return null; // client tenant deleted but pivot row stale
            }

            $stats = $this->safeStats($tenant, $monthStart);

            return [
                'tenant_id'        => $tenant->id,
                'name'             => $tenant->display_name ?: ($tenant->legal_name ?: $tenant->id),
                'plan'             => optional($tenant->subscription)->plan?->slug,
                'plan_status'      => optional($tenant->subscription)->status,
                'permission_level' => $link->permission_level,
                'revenue_mtd'      => $stats['revenue_mtd'],
                'ar_outstanding'   => $stats['ar_outstanding'],
                'overdue_count'    => $stats['overdue_count'],
                'last_activity_at' => $stats['last_activity_at'],
                'ar_aging'         => $stats['ar_aging'],
                'revenue_trend'    => $stats['revenue_trend'],
                'expenses_mtd'     => $stats['expenses_mtd'],
                'cash_balance'     => $stats['cash_balance'],
                'financial_year_start_month' => (int) ($tenant->financial_year_start_month ?? 1),
                'health'           => $this->healthTag($stats),
            ];
        })->filter()->values();
    }

    /**
     * Initialise tenancy on `$tenant`, run the per-client queries, and
     * always tear it down — even on exception. Returns zero-filled
     * stats on any failure so the UI still renders.
     */
    private function safeStats(Tenant $tenant, Carbon $monthStart): array
    {
        $defaults = [
            'revenue_mtd'      => 0.0,
            'ar_outstanding'   => 0.0,
            'overdue_count'    => 0,
            'last_activity_at' => null,
            'ar_aging'         => $this->emptyAgingBuckets(),
            'revenue_trend'    => $this->emptyRevenueTrend(),
            'expenses_mtd'     => 0.0,
            'cash_balance'     => 0.0,
        ];

        try {
            tenancy()->initialize($tenant);

            // Some tenants may have been provisioned before certain
            // tables existed. Skip silently rather than 500.
            if (! Schema::hasTable('invoices')) {
                return $defaults;
            }

            $today = Carbon::now()->toDateString();

            $headline = [
                'revenue_mtd'      => (float) DB::table('invoices')
                    ->where('status', 'paid')
                    ->whereDate('issue_date', '>=', $monthStart->toDateString())
                    ->sum('total_amount'),
                'ar_outstanding'   => (float) DB::table('invoices')
                    ->whereNotIn('status', ['paid', 'void', 'draft'])
                    ->sum(DB::raw('total_amount - coalesce(amount_paid, 0)')),
                'overdue_count'    => DB::table('invoices')
                    ->whereNotIn('status', ['paid', 'void', 'draft'])
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', $today)
                    ->count(),
                'last_activity_at' => DB::table('invoices')->max('created_at')
                    ?: (Schema::hasTable('bills') ? DB::table('bills')->max('created_at') : null),
            ];

            $headline['ar_aging']      = $this->computeAgingBuckets($today);
            $headline['revenue_trend'] = $this->computeRevenueTrend();
            $headline['expenses_mtd']  = Schema::hasTable('bills')
                ? (float) DB::table('bills')
                    ->whereIn('status', ['unpaid', 'partially paid', 'paid'])
                    ->whereDate('bill_date', '>=', $monthStart->toDateString())
                    ->sum('total_amount')
                : 0.0;
            try {
                $headline['cash_balance'] = $this->computeCashBalance();
            } catch (\Throwable $e) {
                Log::warning('PracticeMetricsService: cash balance failed', [
                    'tenant_id' => $tenant->id,
                    'err'       => $e->getMessage(),
                ]);
                $headline['cash_balance'] = 0.0;
            }

            return $headline;
        } catch (\Throwable $e) {
            Log::warning('PracticeMetricsService: per-tenant stats failed', [
                'tenant_id' => $tenant->id,
                'err'       => $e->getMessage(),
            ]);
            return $defaults;
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Walk overdue invoices and split them by days past due. Buckets
     * mirror what every aged-receivables report in the app already
     * uses, so the firm sees consistent numbers.
     */
    private function computeAgingBuckets(string $today): array
    {
        $buckets = $this->emptyAgingBuckets();

        // Pull each unpaid line and bucket in PHP — the math is small,
        // and it sidesteps SQLite vs MySQL date-diff dialect noise.
        $rows = DB::table('invoices')
            ->select('total_amount', 'amount_paid', 'due_date')
            ->whereNotIn('status', ['paid', 'void', 'draft'])
            ->whereNotNull('due_date')
            ->get();

        $todayCarbon = Carbon::parse($today);

        foreach ($rows as $row) {
            $balance = (float) $row->total_amount - (float) ($row->amount_paid ?? 0);
            if ($balance <= 0) {
                continue;
            }

            $due = Carbon::parse($row->due_date)->startOfDay();
            $daysPast = (int) $due->diffInDays($todayCarbon, false);

            if ($daysPast <= 0) {
                $buckets['current'] += $balance;
            } elseif ($daysPast <= 30) {
                $buckets['days_1_30'] += $balance;
            } elseif ($daysPast <= 60) {
                $buckets['days_31_60'] += $balance;
            } elseif ($daysPast <= 90) {
                $buckets['days_61_90'] += $balance;
            } else {
                $buckets['days_90_plus'] += $balance;
            }
        }

        // Round once at the end so the donut sums cleanly.
        return array_map(fn ($v) => round($v, 2), $buckets);
    }

    /**
     * Last `TREND_MONTHS` months of revenue (paid invoices), oldest
     * first. Months with no activity report 0 — never gaps — so the
     * line chart is continuous.
     */
    private function computeRevenueTrend(): array
    {
        $points = [];
        for ($i = self::TREND_MONTHS - 1; $i >= 0; $i--) {
            $start = Carbon::now()->startOfMonth()->subMonths($i);
            $end   = $start->copy()->endOfMonth();

            $points[] = [
                'month'   => $start->format('M Y'),
                'iso'     => $start->format('Y-m'),
                'revenue' => round((float) DB::table('invoices')
                    ->where('status', 'paid')
                    ->whereDate('issue_date', '>=', $start->toDateString())
                    ->whereDate('issue_date', '<=', $end->toDateString())
                    ->sum('total_amount'), 2),
            ];
        }
        return $points;
    }

    /**
     * Sum of bank-type account balances. Used as a rough cash-on-hand
     * indicator on the practice dashboard so firms can spot clients
     * running low without having to dive into each book.
     */
    private function computeCashBalance(): float
    {
        if (! Schema::hasTable('accounts') || ! Schema::hasColumn('accounts', 'sub_type')) {
            return 0.0;
        }

        if (Schema::hasColumn('accounts', 'current_balance')) {
            return round((float) DB::table('accounts')
                ->whereIn('sub_type', ['bank', 'cash'])
                ->sum('current_balance'), 2);
        }

        if (! Schema::hasTable('journal_items') || ! Schema::hasTable('journal_entries')) {
            return 0.0;
        }

        $query = DB::table('journal_items as ji')
            ->join('journal_entries as je', 'je.id', '=', 'ji.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'ji.account_id')
            ->whereIn('a.sub_type', ['bank', 'cash']);

        // Invoice/bill posters leave status at the column default (draft).
        // Count those system rows; only skip explicit voids.
        if (Schema::hasColumn('journal_entries', 'status')) {
            $query->where('je.status', '!=', 'void');
        }

        if (Schema::hasColumn('journal_items', 'deleted_at')) {
            $query->whereNull('ji.deleted_at');
        }
        if (Schema::hasColumn('journal_entries', 'deleted_at')) {
            $query->whereNull('je.deleted_at');
        }

        $balance = (float) $query->selectRaw('COALESCE(SUM(ji.debit - ji.credit), 0) as bal')->value('bal');

        return round($balance, 2);
    }

    private function emptyAgingBuckets(): array
    {
        return [
            'current'      => 0.0,
            'days_1_30'    => 0.0,
            'days_31_60'   => 0.0,
            'days_61_90'   => 0.0,
            'days_90_plus' => 0.0,
        ];
    }

    private function emptyRevenueTrend(): array
    {
        $points = [];
        for ($i = self::TREND_MONTHS - 1; $i >= 0; $i--) {
            $start = Carbon::now()->startOfMonth()->subMonths($i);
            $points[] = [
                'month'   => $start->format('M Y'),
                'iso'     => $start->format('Y-m'),
                'revenue' => 0.0,
            ];
        }
        return $points;
    }

    /**
     * 'good' | 'watch' | 'risk' indicator. Rule of thumb that's worked
     * across our demo data and which we can tune later when we have
     * real signal:
     *
     *   - risk:  any 90+ day overdue OR ≥5 overdue invoices
     *   - watch: any 1–90 day overdue OR ≥1 overdue invoice
     *   - good:  no overdue and a paid invoice in the last 30 days
     *            (i.e. they're alive and current)
     */
    private function healthTag(array $stats): string
    {
        $aging = $stats['ar_aging'] ?? $this->emptyAgingBuckets();
        $overdue = (int) ($stats['overdue_count'] ?? 0);

        if (($aging['days_90_plus'] ?? 0) > 0 || $overdue >= 5) {
            return 'risk';
        }
        if (($aging['days_1_30'] ?? 0) + ($aging['days_31_60'] ?? 0) + ($aging['days_61_90'] ?? 0) > 0
            || $overdue > 0) {
            return 'watch';
        }
        return 'good';
    }

    /**
     * Aggregate the headline numbers shown above the client table.
     * Computed by summing the per-row figures from `clientRows()`.
     */
    public function aggregates(Collection $clientRows): array
    {
        // Aging totals across the whole firm.
        $aging = $this->emptyAgingBuckets();
        foreach ($clientRows as $row) {
            foreach ($row['ar_aging'] ?? [] as $bucket => $value) {
                $aging[$bucket] = ($aging[$bucket] ?? 0) + (float) $value;
            }
        }
        $aging = array_map(fn ($v) => round($v, 2), $aging);

        // Health distribution for the donut.
        $health = ['good' => 0, 'watch' => 0, 'risk' => 0];
        foreach ($clientRows as $row) {
            $tag = $row['health'] ?? 'good';
            $health[$tag] = ($health[$tag] ?? 0) + 1;
        }

        // Revenue trend rolled up across clients. Each client's trend
        // has the same shape (TREND_MONTHS rows, oldest first), so we
        // can stack-sum them column-wise.
        $trendIso   = collect(range(self::TREND_MONTHS - 1, 0))
            ->map(fn ($i) => Carbon::now()->startOfMonth()->subMonths($i)->format('Y-m'));

        $trendByIso = $trendIso->mapWithKeys(fn ($iso) => [
            $iso => [
                'iso'     => $iso,
                'month'   => Carbon::createFromFormat('Y-m', $iso)->format('M Y'),
                'revenue' => 0.0,
            ],
        ])->all();

        foreach ($clientRows as $row) {
            foreach (($row['revenue_trend'] ?? []) as $point) {
                $iso = $point['iso'] ?? null;
                if ($iso && isset($trendByIso[$iso])) {
                    $trendByIso[$iso]['revenue'] += (float) ($point['revenue'] ?? 0);
                }
            }
        }
        $trend = array_values(array_map(
            fn ($p) => ['iso' => $p['iso'], 'month' => $p['month'], 'revenue' => round($p['revenue'], 2)],
            $trendByIso,
        ));

        // Top clients by revenue MTD — useful for the bar chart.
        $topByRevenue = $clientRows
            ->sortByDesc('revenue_mtd')
            ->take(8)
            ->values()
            ->map(fn ($r) => [
                'tenant_id'   => $r['tenant_id'],
                'name'        => $r['name'],
                'revenue_mtd' => round((float) $r['revenue_mtd'], 2),
                'ar_outstanding' => round((float) $r['ar_outstanding'], 2),
            ])->all();

        return [
            'total_clients'        => $clientRows->count(),
            'total_revenue_mtd'    => round($clientRows->sum('revenue_mtd'), 2),
            'total_ar_outstanding' => round($clientRows->sum('ar_outstanding'), 2),
            'total_overdue_count'  => (int) $clientRows->sum('overdue_count'),
            'total_expenses_mtd'   => round($clientRows->sum('expenses_mtd'), 2),
            'total_cash_balance'   => round($clientRows->sum('cash_balance'), 2),
            'ar_aging'             => $aging,
            'health_distribution'  => $health,
            'revenue_trend'        => $trend,
            'top_clients_by_revenue' => $topByRevenue,
        ];
    }

    /**
     * Clients sorted "most attention needed first" — overdue count
     * desc, then 90+ day AR, then total AR. Take the top N for the
     * dashboard's "Needs attention" panel.
     */
    public function clientsNeedingAttention(Collection $clientRows, int $limit = 5): array
    {
        return $clientRows
            ->sortByDesc(fn ($r) => [
                $r['overdue_count'] ?? 0,
                $r['ar_aging']['days_90_plus'] ?? 0,
                $r['ar_outstanding'] ?? 0,
            ])
            ->filter(fn ($r) =>
                ($r['overdue_count'] ?? 0) > 0
                || (($r['ar_aging']['days_90_plus'] ?? 0) > 0)
                || ($r['health'] ?? 'good') !== 'good'
            )
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Compute upcoming compliance deadlines per client. We project two
     * Malaysian-specific items based on the tenant's
     * `financial_year_start_month`:
     *
     *   - Year-end (last day of month *before* the start month).
     *     If it lands within the next 90 days, the firm should start
     *     audit prep.
     *
     *   - LHDN Form C — corporate income-tax return, 7 months after
     *     year-end. Surfaced when within the next 90 days.
     *
     *   - LHDN CP204 — annual estimate of tax payable, due within 30
     *     days of the new financial year. Surfaced when within next
     *     30 days.
     *
     * Returns a flat list sorted by due date ascending so the UI can
     * render the soonest items first.
     */
    public function upcomingDeadlines(Collection $clientRows, int $windowDays = 90): array
    {
        $today = Carbon::today();
        $items = [];

        foreach ($clientRows as $row) {
            $startMonth = (int) ($row['financial_year_start_month'] ?? 1);
            if ($startMonth < 1 || $startMonth > 12) {
                continue;
            }

            // The next *future* year-end (the audit deadline approaching).
            $nextYearEnd = $this->nextYearEndAfter($today, $startMonth);

            // The most recent *past* year-end (the one whose tax filings
            // are now due — Form C 7 months later, CP204 within 30 days
            // of the new period that started right after it).
            $priorYearEnd = $nextYearEnd->copy()->subYear()->endOfDay();

            $formC = $priorYearEnd->copy()->addMonths(7)->endOfDay();
            $cp204 = $priorYearEnd->copy()->addDay()->startOfDay()->addDays(30);

            $candidates = [
                ['kind' => 'year_end', 'label' => 'Financial year-end',          'due' => $nextYearEnd, 'window' => $windowDays],
                ['kind' => 'form_c',   'label' => 'LHDN Form C (corporate tax)', 'due' => $formC,       'window' => $windowDays],
                ['kind' => 'cp204',    'label' => 'LHDN CP204 (tax estimate)',   'due' => $cp204,       'window' => 30],
            ];

            foreach ($candidates as $c) {
                $daysAway = (int) $today->diffInDays($c['due'], false);
                if ($daysAway < 0 || $daysAway > $c['window']) {
                    continue;
                }

                $items[] = [
                    'tenant_id'  => $row['tenant_id'],
                    'client'     => $row['name'],
                    'kind'       => $c['kind'],
                    'label'      => $c['label'],
                    'due'        => $c['due']->toDateString(),
                    'days_away'  => $daysAway,
                ];
            }
        }

        usort($items, fn ($a, $b) => $a['days_away'] <=> $b['days_away']);

        return $items;
    }

    /**
     * Next year-end on or after `$reference`, given the tenant's
     * financial year *start* month. Year-end is the last day of the
     * month immediately before the start month.
     */
    private function nextYearEndAfter(Carbon $reference, int $startMonth): Carbon
    {
        $endMonth = $startMonth - 1;
        $endYear  = $reference->year;
        if ($endMonth === 0) {
            $endMonth = 12;
            // 31 Dec — if it's already past, push to next December.
            $candidate = Carbon::create($endYear, 12, 31)->endOfDay();
            return $candidate->isPast() ? $candidate->addYear() : $candidate;
        }

        $candidate = Carbon::create($endYear, $endMonth, 1)->endOfMonth();
        return $candidate->isPast() ? $candidate->addYear() : $candidate;
    }
}
