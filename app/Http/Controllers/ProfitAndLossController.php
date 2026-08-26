<?php

namespace App\Http\Controllers;

use App\Support\PostedJournalScope;
use App\Support\ReportCompare;
use App\Support\ReportPeriod;
use App\Services\PlSourceDocumentsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfitAndLossController extends Controller
{
    /**
     * Real-time P&L: Income vs Expenses from the general ledger.
     */
    public function index(Request $request): Response
    {
        $resolved = ReportPeriod::range(
            $request->input('preset'),
            $request->input('date_from'),
            $request->input('date_to')
        );
        $dateFrom = $resolved['date_from'];
        $dateTo = $resolved['date_to'];
        $compare = $this->resolveCompare($request);
        $basis = $this->resolveBasis($request);
        $data = $this->buildComparedPlData($dateFrom, $dateTo, $compare, $basis);

        return Inertia::render('Reports/ProfitAndLoss', [
            ...$data,
            'filters' => [
                'preset' => $resolved['preset'],
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'compare' => $compare,
                'basis' => $basis,
            ],
        ]);
    }

    public function sources(Request $request, PlSourceDocumentsService $sources): Response
    {
        $resolved = ReportPeriod::range(
            $request->input('preset'),
            $request->input('date_from'),
            $request->input('date_to')
        );
        $dateFrom = $resolved['date_from'];
        $dateTo = $resolved['date_to'];
        $accountCode = (string) $request->input('account_code', '');
        $account = \App\Models\Account::query()->where('code', $accountCode)->firstOrFail();

        return Inertia::render('Reports/ProfitAndLossSources', [
            'account' => [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
            ],
            'sources' => $sources->forAccount($accountCode, $dateFrom, $dateTo),
            'filters' => [
                'preset' => $resolved['preset'],
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'account_code' => $accountCode,
                'basis' => $this->resolveBasis($request),
            ],
        ]);
    }

    /**
     * Public entry for other report services (e.g. cash flow statement).
     *
     * @return array<string, mixed>
     */
    public function buildPlDataPublic(string $dateFrom, string $dateTo, string $basis = 'accrual'): array
    {
        return $this->buildPlData($dateFrom, $dateTo, $basis);
    }

    /**
     * Build P&L data for the given date range.
     */
    protected function buildPlData(string $dateFrom, string $dateTo, string $basis = 'accrual'): array
    {
        if ($basis === 'cash') {
            return $this->buildCashBasisPlData($dateFrom, $dateTo);
        }

        $rows = DB::table('journal_items')
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_items.account_code', '=', 'accounts.code')
            ->whereIn('accounts.type', ['income', 'expense'])
            ->where('journal_entries.date', '>=', $dateFrom)
            ->where('journal_entries.date', '<=', $dateTo);
        PostedJournalScope::apply($rows);
        $rows = $rows->select(
                'accounts.code',
                'accounts.name',
                'accounts.type',
                DB::raw('SUM(journal_items.debit) as total_debit'),
                DB::raw('SUM(journal_items.credit) as total_credit')
            )
            ->groupBy('accounts.code', 'accounts.name', 'accounts.type')
            ->get();

        $revenueAccounts = [];
        $expenseAccounts = [];
        $totalRevenue = 0.0;
        $totalExpenses = 0.0;

        foreach ($rows as $row) {
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;
            $amount = $row->type === 'income' ? ($credit - $debit) : ($debit - $credit);
            $line = ['code' => $row->code, 'name' => $row->name, 'amount' => round($amount, 2)];
            if ($row->type === 'income') {
                $revenueAccounts[] = $line;
                $totalRevenue += $amount;
            } else {
                $expenseAccounts[] = $line;
                $totalExpenses += $amount;
            }
        }

        $netProfit = $totalRevenue - $totalExpenses;
        return [
            'revenue_accounts' => $revenueAccounts,
            'expense_accounts' => $expenseAccounts,
            'total_revenue' => round($totalRevenue, 2),
            'total_expenses' => round($totalExpenses, 2),
            'net_profit' => round($netProfit, 2),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'basis' => $basis,
        ];
    }

    /**
     * Cash-basis P&L — revenue when collected, expenses when paid (bank/cash journal lines).
     *
     * @return array<string, mixed>
     */
    protected function buildCashBasisPlData(string $dateFrom, string $dateTo): array
    {
        $cashInQuery = DB::table('journal_items as ji')
            ->join('journal_entries as je', 'je.id', '=', 'ji.journal_entry_id')
            ->join('accounts as a', 'a.code', '=', 'ji.account_code')
            ->where('je.reference_type', 'Invoice Payment')
            ->whereBetween('je.date', [$dateFrom, $dateTo])
            ->whereIn('a.sub_type', ['bank', 'cash']);
        PostedJournalScope::apply($cashInQuery, 'je');
        $cashIn = (float) $cashInQuery->sum('ji.debit');

        $cashOutQuery = DB::table('journal_items as ji')
            ->join('journal_entries as je', 'je.id', '=', 'ji.journal_entry_id')
            ->join('accounts as a', 'a.code', '=', 'ji.account_code')
            ->where('je.reference_type', 'Bill Payment')
            ->whereBetween('je.date', [$dateFrom, $dateTo])
            ->whereIn('a.sub_type', ['bank', 'cash']);
        PostedJournalScope::apply($cashOutQuery, 'je');
        $cashOut = (float) $cashOutQuery->sum('ji.credit');

        $revenueAccounts = $cashIn > 0
            ? [['code' => '4000', 'name' => 'Cash collected from customers', 'amount' => round($cashIn, 2)]]
            : [];
        $expenseAccounts = $cashOut > 0
            ? [['code' => '5000', 'name' => 'Cash paid to suppliers', 'amount' => round($cashOut, 2)]]
            : [];

        $netProfit = round($cashIn - $cashOut, 2);

        return [
            'revenue_accounts' => $revenueAccounts,
            'expense_accounts' => $expenseAccounts,
            'total_revenue'    => round($cashIn, 2),
            'total_expenses'   => round($cashOut, 2),
            'net_profit'       => $netProfit,
            'date_from'        => $dateFrom,
            'date_to'          => $dateTo,
            'basis'            => 'cash',
        ];
    }

    protected function buildComparedPlData(string $dateFrom, string $dateTo, string $compare, string $basis = 'accrual'): array
    {
        $current = $this->buildPlData($dateFrom, $dateTo, $basis);
        $current['compare'] = $compare;
        $current['compare_label'] = null;
        $current['compare_from'] = null;
        $current['compare_to'] = null;
        $current['compare_revenue'] = null;
        $current['compare_expenses'] = null;
        $current['compare_net_profit'] = null;
        $current['revenue_variance'] = null;
        $current['expenses_variance'] = null;
        $current['net_profit_variance'] = null;

        if ($compare === 'none') {
            return $current;
        }

        $window = $compare === 'last_year'
            ? ReportPeriod::lastYearSameDates($dateFrom, $dateTo)
            : ReportPeriod::previousOfSameLength($dateFrom, $dateTo);
        $prior = $this->buildPlData($window['date_from'], $window['date_to'], $basis);

        $current['revenue_accounts'] = ReportCompare::mergeLines(
            $current['revenue_accounts'],
            $prior['revenue_accounts']
        );
        $current['expense_accounts'] = ReportCompare::mergeLines(
            $current['expense_accounts'],
            $prior['expense_accounts']
        );
        $current['compare_label'] = $compare === 'last_year' ? 'Same period last year' : 'Previous period';
        $current['compare_from'] = $window['date_from'];
        $current['compare_to'] = $window['date_to'];
        $current['compare_revenue'] = $prior['total_revenue'];
        $current['compare_expenses'] = $prior['total_expenses'];
        $current['compare_net_profit'] = $prior['net_profit'];
        $current['revenue_variance'] = round($current['total_revenue'] - $prior['total_revenue'], 2);
        $current['expenses_variance'] = round($current['total_expenses'] - $prior['total_expenses'], 2);
        $current['net_profit_variance'] = round($current['net_profit'] - $prior['net_profit'], 2);

        return $current;
    }

    protected function resolveBasis(Request $request): string
    {
        $basis = $request->input('basis', 'accrual');

        return in_array($basis, ['accrual', 'cash'], true) ? $basis : 'accrual';
    }

    protected function resolveCompare(Request $request): string
    {
        $compare = $request->input('compare', 'previous');

        return in_array($compare, ['previous', 'last_year', 'none'], true) ? $compare : 'previous';
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $resolved = ReportPeriod::range(
            $request->input('preset'),
            $request->input('date_from'),
            $request->input('date_to')
        );
        $dateFrom = $resolved['date_from'];
        $dateTo = $resolved['date_to'];
        $basis = $this->resolveBasis($request);
        $data = $this->buildComparedPlData($dateFrom, $dateTo, $this->resolveCompare($request), $basis);

        $filename = 'profit-and-loss-' . $dateFrom . '-to-' . $dateTo . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return new StreamedResponse(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['section', 'code', 'name', 'amount', 'compare_amount', 'variance']);
            foreach ($data['revenue_accounts'] as $row) {
                fputcsv($out, ['Revenue', $row['code'], $row['name'], $row['amount'], $row['compare_amount'] ?? '', $row['variance'] ?? '']);
            }
            fputcsv($out, ['', '', 'Total revenue', $data['total_revenue'], $data['compare_revenue'] ?? '', $data['revenue_variance'] ?? '']);
            foreach ($data['expense_accounts'] as $row) {
                fputcsv($out, ['Expense', $row['code'], $row['name'], $row['amount'], $row['compare_amount'] ?? '', $row['variance'] ?? '']);
            }
            fputcsv($out, ['', '', 'Total expenses', $data['total_expenses'], $data['compare_expenses'] ?? '', $data['expenses_variance'] ?? '']);
            fputcsv($out, ['', '', 'Net profit/loss', $data['net_profit'], $data['compare_net_profit'] ?? '', $data['net_profit_variance'] ?? '']);
            fclose($out);
        }, 200, $headers);
    }

    public function exportPdf(Request $request)
    {
        $resolved = ReportPeriod::range(
            $request->input('preset'),
            $request->input('date_from'),
            $request->input('date_to')
        );
        $dateFrom = $resolved['date_from'];
        $dateTo = $resolved['date_to'];
        $basis = $this->resolveBasis($request);
        $data = $this->buildComparedPlData($dateFrom, $dateTo, $this->resolveCompare($request), $basis);
        $company = $this->reportCompany();

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.profit-and-loss', [
            ...$data,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'basis' => $basis,
            'company' => $company,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('profit-and-loss-' . $dateFrom . '-to-' . $dateTo . '.pdf');
    }

    protected function reportCompany(): array
    {
        $user = request()->user();
        if ($user && $user->tenant_id) {
            $tenant = \App\Models\Tenant::find($user->tenant_id);
            $data = $tenant?->data ?? [];
            $c = $data['company'] ?? [];
            $name = $c['display_name'] ?? $c['legal_name'] ?? config('invoice.company.name');
            $addressParts = array_filter([$c['street'] ?? '', $c['city'] ?? '', $c['state'] ?? '', $c['postcode'] ?? '', $c['country'] ?? '']);
            $address = implode(', ', $addressParts);
            return ['name' => $name ?: config('invoice.company.name'), 'address' => $address ?: config('invoice.company.address')];
        }
        return config('invoice.company');
    }
}
