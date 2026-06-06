<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Supplier;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Dashboard — Wave-style "Insights for you" home screen.
 *
 * Sections produced:
 *   - top KPIs (receivables, payables, net month, collection rate)
 *   - quick action buttons (estimate / invoice / transaction / bill)
 *   - overdue invoices and bills (top 5 each)
 *   - 12-month cash flow series (inflow / outflow / net)
 *   - 12-month P&L series (income / expenses)
 *   - top expense categories (donut, current fiscal year)
 *   - net income comparison (previous month vs current)
 *   - AR / AP aging buckets
 */
class DashboardController extends Controller
{
    public function index()
    {
        // Defence-in-depth for firm (Practice) users:
        //   - If they're "acting into" a client (acting_tenant_id is
        //     set AND tenancy initialised), let them see the SME
        //     dashboard for that client. This is the whole point of
        //     the client switcher.
        //   - If they're NOT acting on a client, they have no tenant
        //     DB, so the queries below would fall back to the central
        //     connection and 500. Bounce them to the Practice console.
        $user = auth()->user();
        if ($user && method_exists($user, 'isFirmUser') && $user->isFirmUser()) {
            $actingOnClient = (bool) session('acting_tenant_id');
            $tenancyReady = function_exists('tenancy') && tenancy()->initialized;
            if (! $actingOnClient || ! $tenancyReady) {
                return redirect()->route('practice.dashboard');
            }
        }

        $today = Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();
        $startOfYear = $today->copy()->startOfYear();

        // ───────── Top KPIs (existing behaviour, preserved) ─────────
        $customerCount = Customer::count();
        $activeCustomers = Customer::where('is_active', true)->count();

        $invoiceStats = Invoice::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
            SUM(CASE WHEN status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
            SUM(CASE WHEN status = 'partially paid' THEN 1 ELSE 0 END) as partially_paid_count,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN status = 'void' THEN 1 ELSE 0 END) as void_count,
            SUM(CASE WHEN status NOT IN ('draft','void') THEN total_amount ELSE 0 END) as total_invoiced,
            SUM(amount_paid) as total_collected,
            SUM(CASE WHEN status NOT IN ('draft','void') THEN (total_amount - amount_paid) ELSE 0 END) as total_outstanding
        ")->first();

        $totalCustomerOutstanding = (float) ($invoiceStats->total_outstanding ?? 0);

        $overdueInvoicesCount = Invoice::whereIn('status', ['unpaid', 'partially paid'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->whereRaw('(total_amount - amount_paid) > 0')
            ->count();

        $salesThisMonth = (float) Invoice::whereIn('status', ['unpaid', 'partially paid', 'paid'])
            ->whereBetween('issue_date', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        $creditNotesCount = \App\Models\CreditNote::count();
        $creditNotesValue = (float) \App\Models\CreditNote::sum('total_amount');

        $supplierCount = Supplier::count();
        $activeSuppliers = Supplier::where('is_active', true)->count();

        $billStats = Bill::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
            SUM(CASE WHEN status IN ('unpaid','partially paid') THEN 1 ELSE 0 END) as unpaid_count,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN status NOT IN ('draft','void') THEN total_amount ELSE 0 END) as total_billed,
            SUM(CASE WHEN status NOT IN ('draft','void') THEN (total_amount - amount_paid) ELSE 0 END) as total_ap
        ")->first();

        $expensesThisMonth = (float) Bill::whereIn('status', ['unpaid', 'partially paid', 'paid'])
            ->whereBetween('bill_date', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        $overdueBillsCount = Bill::whereIn('status', ['unpaid', 'partially paid'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->whereRaw('(total_amount - amount_paid) > 0')
            ->count();

        // ───────── Wave-style insight series ─────────
        $cashFlow = $this->cashFlowSeries($today);
        $pnl = $this->profitAndLossSeries($today);
        $expensesBreakdown = $this->expensesBreakdown($startOfYear, $today);
        $netIncomeComparison = $this->netIncomeComparison($today);
        $arAging = $this->agingBuckets('receivables', $today);
        $apAging = $this->agingBuckets('payables', $today);
        $overdueInvoiceList = $this->overdueInvoiceList($today);
        $overdueBillList = $this->overdueBillList($today);

        return Inertia::render('Dashboard', [
            'stats' => [
                'customers' => [
                    'total' => $customerCount,
                    'active' => $activeCustomers,
                    'outstanding' => $totalCustomerOutstanding,
                ],
                'invoices' => [
                    'total' => (int) ($invoiceStats->total ?? 0),
                    'draft' => (int) ($invoiceStats->draft_count ?? 0),
                    'unpaid' => (int) ($invoiceStats->unpaid_count ?? 0),
                    'partially_paid' => (int) ($invoiceStats->partially_paid_count ?? 0),
                    'paid' => (int) ($invoiceStats->paid_count ?? 0),
                    'void' => (int) ($invoiceStats->void_count ?? 0),
                    'total_invoiced' => (float) ($invoiceStats->total_invoiced ?? 0),
                    'total_collected' => (float) ($invoiceStats->total_collected ?? 0),
                    'total_outstanding' => $totalCustomerOutstanding,
                    'overdue_count' => $overdueInvoicesCount,
                ],
                'credit_notes' => [
                    'count' => $creditNotesCount,
                    'value' => $creditNotesValue,
                ],
                'suppliers' => [
                    'total' => $supplierCount,
                    'active' => $activeSuppliers,
                ],
                'bills' => [
                    'total' => (int) ($billStats->total ?? 0),
                    'draft' => (int) ($billStats->draft_count ?? 0),
                    'unpaid_count' => (int) ($billStats->unpaid_count ?? 0),
                    'paid' => (int) ($billStats->paid_count ?? 0),
                    'total_billed' => (float) ($billStats->total_billed ?? 0),
                    'total_ap' => (float) ($billStats->total_ap ?? 0),
                    'overdue_count' => $overdueBillsCount,
                ],
                'period' => [
                    'sales_this_month' => $salesThisMonth,
                    'expenses_this_month' => $expensesThisMonth,
                    'net_this_month' => round($salesThisMonth - $expensesThisMonth, 2),
                ],
                'audit' => [
                    'total' => Bill::count(),
                    'unaudited' => Bill::where(function ($q) {
                        $q->where('audit_status', 'unaudited')->orWhereNull('audit_status');
                    })->count(),
                    'verified' => Bill::where('audit_status', 'verified')->count(),
                    'flagged' => Bill::where('audit_status', 'flagged')->count(),
                ],
                // ── Wave-style insights ──
                'cash_flow'           => $cashFlow,
                'profit_and_loss'     => $pnl,
                'expenses_breakdown'  => $expensesBreakdown,
                'net_income_compare'  => $netIncomeComparison,
                'ar_aging'            => $arAging,
                'ap_aging'            => $apAging,
                'overdue_invoices'    => $overdueInvoiceList,
                'overdue_bills'       => $overdueBillList,
            ],
        ]);
    }

    /**
     * 12-month cash flow series. Pulls every journal_item hitting an account
     * with sub_type = bank|cash and aggregates by month:
     *   - inflow  = sum of debits  (money in)
     *   - outflow = sum of credits (money out)
     */
    private function cashFlowSeries(CarbonInterface $today): array
    {
        $start = $today->copy()->startOfMonth()->subMonths(11);
        $end = $today->copy()->endOfMonth();

        $rows = DB::table('journal_items as ji')
            ->join('journal_entries as je', 'je.id', '=', 'ji.journal_entry_id')
            ->join('accounts as a', 'a.id', '=', 'ji.account_id')
            ->select([
                DB::raw("DATE_FORMAT(je.date, '%Y-%m') as ym"),
                DB::raw('SUM(ji.debit) as inflow'),
                DB::raw('SUM(ji.credit) as outflow'),
            ])
            ->whereIn('a.sub_type', ['bank', 'cash'])
            ->whereBetween('je.date', [$start, $end])
            ->whereNull('ji.deleted_at')
            ->whereNull('je.deleted_at')
            ->where('je.status', 'posted')
            ->groupBy('ym')
            ->get()
            ->keyBy('ym');

        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $start->copy()->addMonths($i);
            $key = $m->format('Y-m');
            $row = $rows[$key] ?? null;
            $in = $row ? (float) $row->inflow : 0;
            $out = $row ? (float) $row->outflow : 0;
            $months[] = [
                'month'   => $m->format('M Y'),
                'short'   => $m->format('M'),
                'ym'      => $key,
                'inflow'  => round($in, 2),
                'outflow' => round($out, 2),
                'net'     => round($in - $out, 2),
            ];
        }

        return [
            'series'      => $months,
            'total_in'    => round(array_sum(array_column($months, 'inflow')), 2),
            'total_out'   => round(array_sum(array_column($months, 'outflow')), 2),
            'total_net'   => round(array_sum(array_column($months, 'net')), 2),
        ];
    }

    /**
     * 12-month income vs expenses based on invoice/bill issue dates.
     * Uses headers (not journal-items) because P&L on the dashboard is meant
     * to track sales activity, not strict accrual GL totals.
     */
    private function profitAndLossSeries(CarbonInterface $today): array
    {
        $start = $today->copy()->startOfMonth()->subMonths(11);
        $end = $today->copy()->endOfMonth();

        $income = Invoice::query()
            ->whereNotIn('status', ['draft', 'void'])
            ->whereBetween('issue_date', [$start, $end])
            ->select(DB::raw("DATE_FORMAT(issue_date, '%Y-%m') as ym"), DB::raw('SUM(total_amount) as v'))
            ->groupBy('ym')
            ->pluck('v', 'ym');

        $expense = Bill::query()
            ->where('status', '!=', 'void')
            ->whereBetween('bill_date', [$start, $end])
            ->select(DB::raw("DATE_FORMAT(bill_date, '%Y-%m') as ym"), DB::raw('SUM(total_amount) as v'))
            ->groupBy('ym')
            ->pluck('v', 'ym');

        $months = [];
        $totalIncome = 0;
        $totalExpense = 0;
        for ($i = 0; $i < 12; $i++) {
            $m = $start->copy()->addMonths($i);
            $key = $m->format('Y-m');
            $inc = (float) ($income[$key] ?? 0);
            $exp = (float) ($expense[$key] ?? 0);
            $totalIncome += $inc;
            $totalExpense += $exp;
            $months[] = [
                'month'   => $m->format('M Y'),
                'short'   => $m->format('M'),
                'ym'      => $key,
                'income'  => round($inc, 2),
                'expense' => round($exp, 2),
                'net'     => round($inc - $exp, 2),
            ];
        }

        return [
            'series'        => $months,
            'total_income'  => round($totalIncome, 2),
            'total_expense' => round($totalExpense, 2),
            'total_net'     => round($totalIncome - $totalExpense, 2),
        ];
    }

    /**
     * Top expense categories. Uses bill_items.account_code so we get a real
     * category split, not just one big bucket of "Expenses".
     *
     * Returns the top 6 categories with the rest collapsed into "Other".
     */
    private function expensesBreakdown(CarbonInterface $start, CarbonInterface $end): array
    {
        $rows = DB::table('bill_items as bi')
            ->join('bills as b', 'b.id', '=', 'bi.bill_id')
            ->leftJoin('accounts as a', 'a.code', '=', 'bi.account_code')
            ->select([
                'bi.account_code',
                'a.name as account_name',
                DB::raw('SUM(bi.amount) as total'),
            ])
            ->where('b.status', '!=', 'void')
            ->whereBetween('b.bill_date', [$start, $end])
            ->whereNull('b.deleted_at')
            ->whereNull('bi.deleted_at')
            ->whereNotNull('bi.account_code')
            ->groupBy('bi.account_code', 'a.name')
            ->orderByDesc('total')
            ->get();

        $top = $rows->take(6)
            ->map(fn ($r) => [
                'code'  => $r->account_code,
                'name'  => $r->account_name ?: ('Account ' . $r->account_code),
                'total' => round((float) $r->total, 2),
            ])
            ->all();

        $otherTotal = (float) $rows->slice(6)->sum('total');
        if ($otherTotal > 0) {
            $top[] = [
                'code'  => 'OTHER',
                'name'  => 'Other categories',
                'total' => round($otherTotal, 2),
            ];
        }

        $grand = (float) $rows->sum('total');
        $top = array_map(function ($row) use ($grand) {
            $row['percent'] = $grand > 0 ? round(($row['total'] / $grand) * 100, 1) : 0;
            return $row;
        }, $top);

        return [
            'categories' => $top,
            'total'      => round($grand, 2),
            'period'     => [
                'from' => $start->toDateString(),
                'to'   => $end->toDateString(),
            ],
        ];
    }

    /**
     * Compare current month-to-date with the previous month-to-date.
     * Uses the same header-based logic as the P&L series so the cards agree.
     */
    private function netIncomeComparison(CarbonInterface $today): array
    {
        $currentStart = $today->copy()->startOfMonth();
        $currentEnd = $today->copy();
        $prevEnd = $currentStart->copy()->subDay();
        $prevStart = $prevEnd->copy()->startOfMonth();

        $income = function (CarbonInterface $a, CarbonInterface $b): float {
            return (float) Invoice::whereNotIn('status', ['draft', 'void'])
                ->whereBetween('issue_date', [$a, $b])
                ->sum('total_amount');
        };
        $expense = function (CarbonInterface $a, CarbonInterface $b): float {
            return (float) Bill::where('status', '!=', 'void')
                ->whereBetween('bill_date', [$a, $b])
                ->sum('total_amount');
        };

        $previous = [
            'label'   => $prevStart->format('M Y'),
            'income'  => round($income($prevStart, $prevEnd), 2),
            'expense' => round($expense($prevStart, $prevEnd), 2),
        ];
        $previous['net'] = round($previous['income'] - $previous['expense'], 2);

        $current = [
            'label'   => $currentStart->format('M Y'),
            'income'  => round($income($currentStart, $currentEnd), 2),
            'expense' => round($expense($currentStart, $currentEnd), 2),
        ];
        $current['net'] = round($current['income'] - $current['expense'], 2);

        return [
            'previous' => $previous,
            'current'  => $current,
        ];
    }

    /**
     * Aging buckets. Used for both AR (invoices) and AP (bills).
     *
     *  - "coming_due" : balance not yet due
     *  - "1_30"       : 1-30 days overdue
     *  - "31_60"
     *  - "61_90"
     *  - "over_90"
     */
    private function agingBuckets(string $kind, CarbonInterface $today): array
    {
        $buckets = [
            'coming_due' => 0.0,
            '1_30'       => 0.0,
            '31_60'      => 0.0,
            '61_90'      => 0.0,
            'over_90'    => 0.0,
        ];

        $query = $kind === 'receivables'
            ? Invoice::query()
                ->whereIn('status', ['unpaid', 'partially paid'])
                ->whereRaw('(total_amount - amount_paid) > 0')
                ->select(['due_date', DB::raw('(total_amount - amount_paid) as balance')])
            : Bill::query()
                ->whereIn('status', ['unpaid', 'partially paid'])
                ->whereRaw('(total_amount - amount_paid) > 0')
                ->select(['due_date', DB::raw('(total_amount - amount_paid) as balance')]);

        foreach ($query->get() as $row) {
            $bal = (float) $row->balance;
            if (! $row->due_date) {
                $buckets['coming_due'] += $bal;
                continue;
            }
            $days = (int) Carbon::parse($row->due_date)->startOfDay()->diffInDays($today, false);
            // diffInDays(false) returns negative if due_date is in the future.
            if ($days < 0)         $buckets['coming_due'] += $bal;
            elseif ($days <= 30)   $buckets['1_30']       += $bal;
            elseif ($days <= 60)   $buckets['31_60']      += $bal;
            elseif ($days <= 90)   $buckets['61_90']      += $bal;
            else                   $buckets['over_90']    += $bal;
        }

        $buckets = array_map(fn ($v) => round($v, 2), $buckets);
        $buckets['total'] = round(array_sum($buckets), 2);
        return $buckets;
    }

    /**
     * Top 5 overdue invoices, biggest balance first, eager-loading customer.
     */
    private function overdueInvoiceList(CarbonInterface $today): array
    {
        return Invoice::with('customer:id,name')
            ->whereIn('status', ['unpaid', 'partially paid'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->whereRaw('(total_amount - amount_paid) > 0')
            ->orderBy('due_date')
            ->limit(5)
            ->get(['id', 'invoice_number', 'customer_id', 'due_date', 'total_amount', 'amount_paid', 'currency'])
            ->map(function ($i) use ($today) {
                $balance = (float) $i->total_amount - (float) $i->amount_paid;
                $daysOverdue = (int) Carbon::parse($i->due_date)->startOfDay()->diffInDays($today, false);
                return [
                    'id'             => $i->id,
                    'invoice_number' => $i->invoice_number,
                    'customer_name'  => optional($i->customer)->name ?? '—',
                    'due_date'       => $i->due_date,
                    'days_overdue'   => max(0, $daysOverdue),
                    'balance'        => round($balance, 2),
                    'currency'       => $i->currency ?? 'MYR',
                ];
            })
            ->all();
    }

    /**
     * Top 5 overdue bills, biggest balance first, eager-loading supplier.
     */
    private function overdueBillList(CarbonInterface $today): array
    {
        return Bill::with('supplier:id,name')
            ->whereIn('status', ['unpaid', 'partially paid'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->whereRaw('(total_amount - amount_paid) > 0')
            ->orderBy('due_date')
            ->limit(5)
            ->get(['id', 'bill_number', 'supplier_id', 'due_date', 'total_amount', 'amount_paid', 'currency'])
            ->map(function ($b) use ($today) {
                $balance = (float) $b->total_amount - (float) $b->amount_paid;
                $daysOverdue = (int) Carbon::parse($b->due_date)->startOfDay()->diffInDays($today, false);
                return [
                    'id'            => $b->id,
                    'bill_number'   => $b->bill_number,
                    'supplier_name' => optional($b->supplier)->name ?? '—',
                    'due_date'      => $b->due_date,
                    'days_overdue'  => max(0, $daysOverdue),
                    'balance'       => round($balance, 2),
                    'currency'      => $b->currency ?? 'MYR',
                ];
            })
            ->all();
    }
}
