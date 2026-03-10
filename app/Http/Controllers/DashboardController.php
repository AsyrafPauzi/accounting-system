<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
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

        // Credit Notes
        $creditNotesCount = DB::table('credit_notes')->count();
        $creditNotesValue = DB::table('credit_notes')->sum('total_amount');

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
                ],
                'credit_notes' => [
                    'count' => $creditNotesCount,
                    'value' => (float) $creditNotesValue,
                ],
            ],
        ]);
    }
}
