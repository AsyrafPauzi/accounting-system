<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Support\CashMovement;
use App\Support\Deployment;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportsHubController extends Controller
{
    /**
     * Reports hub: single entry point for all financial reports.
     */
    public function index(Request $request): Response
    {
        $month = ReportPeriod::range('this_month', null, null);
        $sstPeriod = ReportPeriod::sstPeriod();
        $today = now()->toDateString();

        $snapshot = [
            'month_label' => now()->format('F Y'),
            'net_profit' => null,
            'tax_owing' => null,
            'overdue_ar_amount' => null,
            'overdue_ar_count' => null,
            'cash' => null,
        ];

        if ($this->hasReportAccess($request, 'reports.profit-loss')) {
            $rows = DB::table('journal_items')
                ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
                ->join('accounts', 'journal_items.account_code', '=', 'accounts.code')
                ->whereIn('accounts.type', ['income', 'expense'])
                ->whereBetween('journal_entries.date', [$month['date_from'], $month['date_to']])
                ->select([
                    'accounts.type',
                    DB::raw('SUM(journal_items.debit) as total_debit'),
                    DB::raw('SUM(journal_items.credit) as total_credit'),
                ])
                ->groupBy('accounts.type')
                ->get();

            $totalRevenue = 0.0;
            $totalExpenses = 0.0;
            foreach ($rows as $row) {
                $debit = (float) $row->total_debit;
                $credit = (float) $row->total_credit;
                if ($row->type === 'income') {
                    $totalRevenue += $credit - $debit;
                } else {
                    $totalExpenses += $debit - $credit;
                }
            }
            $snapshot['net_profit'] = round($totalRevenue - $totalExpenses, 2);
        }

        if ($this->hasReportAccess($request, 'reports.sales-tax')) {
            $outputTax = (float) DB::table('invoices')
                ->whereNotIn('status', ['draft', 'void'])
                ->whereBetween('issue_date', [$sstPeriod['date_from'], $sstPeriod['date_to']])
                ->whereNull('deleted_at')
                ->sum('tax_amount');
            $inputTax = (float) DB::table('bills')
                ->whereNotIn('status', ['draft', 'void'])
                ->whereBetween('bill_date', [$sstPeriod['date_from'], $sstPeriod['date_to']])
                ->whereNull('deleted_at')
                ->sum('tax_amount');

            $snapshot['tax_owing'] = round($outputTax - $inputTax, 2);
        }

        if ($this->hasReportAccess($request, 'reports.aged-reports')) {
            $overdue = DB::table('invoices')
                ->whereIn('status', ['unpaid', 'partially paid'])
                ->whereDate('due_date', '<', $today)
                ->whereNull('deleted_at')
                ->select(['total_amount', 'amount_paid'])
                ->get()
                ->map(fn ($invoice) => (float) $invoice->total_amount - (float) $invoice->amount_paid)
                ->filter(fn ($balance) => $balance > 0);

            $snapshot['overdue_ar_amount'] = round($overdue->sum(), 2);
            $snapshot['overdue_ar_count'] = $overdue->count();
        }

        if ($this->hasReportAccess($request, 'journal.view')) {
            $snapshot['cash'] = CashMovement::netAsOf($today);
        }

        return Inertia::render('Reports/Hub', [
            'snapshot' => $snapshot,
            'sections' => $this->sections(),
            'base_currency' => $this->tenantBaseCurrency($request),
        ]);
    }

    /**
     * @return array<int, array{title:string,reports:array<int, array<string, string>>}>
     */
    private function sections(): array
    {
        return [
            [
                'title' => 'Financial',
                'reports' => [
                    ['title' => 'Profit & Loss', 'description' => 'Income and expenses from your ledger.', 'route_name' => 'profit-and-loss.index', 'permission' => 'reports.profit-loss'],
                    ['title' => 'Balance Sheet', 'description' => 'Assets, liabilities, and equity at a point in time.', 'route_name' => 'balance-sheet.index', 'permission' => 'reports.balance-sheet'],
                    ['title' => 'Trial Balance', 'description' => 'Check that ledger debits and credits balance.', 'route_name' => 'trial-balance.index', 'permission' => 'general-ledger.view'],
                    ['title' => 'General ledger', 'description' => 'Review every posting by account and journal entry.', 'route_name' => 'general-ledger.index', 'permission' => 'general-ledger.view'],
                ],
            ],
            [
                'title' => 'Money',
                'reports' => [
                    ['title' => 'Cash movement', 'description' => 'Money in and out of bank and cash.', 'route_name' => 'cashflow-summary.index', 'permission' => 'reports.cashflow'],
                    ['title' => 'Income by customer', 'description' => 'Revenue by customer and product, paid and unpaid.', 'route_name' => 'reports.income-by-customer.index', 'permission' => 'reports.sales'],
                    ['title' => 'Purchases by vendor', 'description' => 'Supplier spend with paid and unpaid breakdowns.', 'route_name' => 'reports.purchases-by-vendor.index', 'permission' => 'reports.purchases-by-vendor'],
                    ['title' => 'Customer credits', 'description' => 'Open credits and deposits held for customers.', 'route_name' => 'reports.customer-credits.index', 'permission' => 'reports.customer-credits'],
                ],
            ],
            [
                'title' => 'Collect',
                'reports' => [
                    ['title' => 'Aged receivables', 'description' => 'Outstanding customer invoices by age.', 'route_name' => 'aged-receivables.index', 'permission' => 'reports.aged-reports'],
                    ['title' => 'Aged payables', 'description' => 'Outstanding supplier bills by age.', 'route_name' => 'accounts-payable.index', 'permission' => 'reports.aged-reports'],
                ],
            ],
            [
                'title' => 'Tax & payroll',
                'reports' => [
                    ['title' => 'Sales tax', 'description' => 'Output tax less input tax for your SST return.', 'route_name' => 'reports.sales-tax.index', 'permission' => 'reports.sales-tax'],
                    ['title' => 'Payroll remittance due', 'description' => 'Statutory payroll payables still awaiting remittance.', 'route_name' => 'reports.payroll-remittance', 'permission' => 'payroll.run', 'user_permission' => 'journal.create'],
                ],
            ],
        ];
    }

    private function hasReportAccess(Request $request, string $permission): bool
    {
        $user = $request->user();
        if (! $user || ! $user->can($permission)) {
            return false;
        }
        if ($user->role_name === 'super-admin' || Deployment::isSelfHosted()) {
            return true;
        }

        $tenantId = $user->isFirmUser()
            ? $request->session()->get('acting_tenant_id')
            : $user->tenant_id;
        if (! $tenantId) {
            return false;
        }

        return Tenant::find($tenantId)?->hasPlanPermission($permission) ?? false;
    }

    private function tenantBaseCurrency(Request $request): string
    {
        if (function_exists('tenant') && tenant()) {
            return strtoupper((string) (tenant()->base_currency ?? 'MYR'));
        }

        $tenantId = $request->user()?->isFirmUser()
            ? $request->session()->get('acting_tenant_id')
            : $request->user()?->tenant_id;

        return strtoupper((string) (Tenant::find($tenantId)?->base_currency ?? 'MYR'));
    }
}
