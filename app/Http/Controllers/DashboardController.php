<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $startOfMonth = $today->copy()->startOfMonth();
        $endOfMonth = $today->copy()->endOfMonth();

        // Customer Analytics
        $customerCount = Customer::count();
        $activeCustomers = Customer::where('is_active', true)->count();
        $totalCustomerOutstanding = Invoice::whereNotIn('status', ['draft', 'void'])
            ->selectRaw('SUM(total_amount - amount_paid) as balance')
            ->value('balance') ?? 0;

        // Invoice Analytics
        $invoiceStats = Invoice::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
            SUM(CASE WHEN status = 'unpaid' THEN 1 ELSE 0 END) as unpaid_count,
            SUM(CASE WHEN status = 'partially paid' THEN 1 ELSE 0 END) as partially_paid_count,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN status = 'void' THEN 1 ELSE 0 END) as void_count,
            SUM(CASE WHEN status NOT IN ('draft', 'void') THEN total_amount ELSE 0 END) as total_invoiced,
            SUM(amount_paid) as total_collected,
            SUM(CASE WHEN status NOT IN ('draft', 'void') THEN (total_amount - amount_paid) ELSE 0 END) as total_outstanding
        ")->first();

        // Invoices overdue (unpaid/partially paid, due_date < today)
        $overdueInvoicesCount = Invoice::whereIn('status', ['unpaid', 'partially paid'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->whereRaw('(total_amount - amount_paid) > 0')
            ->count();

        // Sales this month (posted invoices)
        $salesThisMonth = (float) Invoice::whereIn('status', ['unpaid', 'partially paid', 'paid'])
            ->whereBetween('issue_date', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        // Credit Notes
        $creditNotesCount = \App\Models\CreditNote::count();
        $creditNotesValue = (float) \App\Models\CreditNote::sum('total_amount');

        // Suppliers
        $supplierCount = Supplier::count();
        $activeSuppliers = Supplier::where('is_active', true)->count();

        // Bills / AP
        $billStats = Bill::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
            SUM(CASE WHEN status IN ('unpaid', 'partially paid') THEN 1 ELSE 0 END) as unpaid_count,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN status NOT IN ('draft', 'void') THEN total_amount ELSE 0 END) as total_billed,
            SUM(CASE WHEN status NOT IN ('draft', 'void') THEN (total_amount - amount_paid) ELSE 0 END) as total_ap
        ")->first();

        $expensesThisMonth = (float) Bill::whereIn('status', ['unpaid', 'partially paid', 'paid'])
            ->whereBetween('bill_date', [$startOfMonth, $endOfMonth])
            ->sum('total_amount');

        $overdueBillsCount = Bill::whereIn('status', ['unpaid', 'partially paid'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->whereRaw('(total_amount - amount_paid) > 0')
            ->count();

        return Inertia::render('Dashboard', [
            'stats' => [
                'customers' => [
                    'total' => $customerCount,
                    'active' => $activeCustomers,
                    'outstanding' => (float) $totalCustomerOutstanding,
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
                    'total_outstanding' => (float) ($invoiceStats->total_outstanding ?? 0),
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
                    'unaudited' => Bill::where('audit_status', 'unaudited')->orWhereNull('audit_status')->count(),
                    'verified' => Bill::where('audit_status', 'verified')->count(),
                    'flagged' => Bill::where('audit_status', 'flagged')->count(),
                ],
            ],
        ]);
    }
}
