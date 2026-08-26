<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Support\ReportPeriod;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Income by Customer
 *
 * Per customer, over a date range:
 *   - total invoiced (excludes draft + void)
 *   - paid amount   (sum of amount_paid)
 *   - unpaid amount (total - paid)
 *   - invoice count
 *
 * Sorted by total invoiced desc so the top revenue customers float to the top.
 *
 * This is an upgrade over the old "Sales Report" — that one only showed
 * total revenue. This adds the paid-vs-unpaid breakdown that Wave shows.
 */
class IncomeByCustomerController extends Controller
{
    public function __construct(private InvoiceService $invoiceService) {}

    public function index(Request $request): Response
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        $resolved = ReportPeriod::range(
            $request->input('preset'),
            $request->input('start_date'),
            $request->input('end_date')
        );
        $start = $resolved['date_from'];
        $end = $resolved['date_to'];

        $grouped = [];
        foreach (Invoice::with('customer:id,name,email')
            ->whereNotIn('status', ['draft', 'void'])
            ->whereBetween('issue_date', [$start, $end])
            ->get() as $invoice) {
            $customerId = $invoice->customer_id;
            if (! isset($grouped[$customerId])) {
                $grouped[$customerId] = [
                    'customer_id'    => $customerId,
                    'customer_name'  => $invoice->customer?->name ?? '— Deleted customer —',
                    'customer_email' => $invoice->customer?->email,
                    'total_invoiced' => 0.0,
                    'total_paid'     => 0.0,
                    'total_unpaid'   => 0.0,
                    'invoice_count'  => 0,
                ];
            }
            $grouped[$customerId]['total_invoiced'] += (float) $invoice->total_amount;
            $grouped[$customerId]['total_paid'] += (float) $invoice->amount_paid;
            $grouped[$customerId]['total_unpaid'] += max(0, $this->invoiceService->remainingBalance($invoice));
            $grouped[$customerId]['invoice_count']++;
        }

        $rows = collect($grouped)
            ->sortByDesc('total_invoiced')
            ->values()
            ->map(fn ($r) => [
                'customer_id'    => $r['customer_id'],
                'customer_name'  => $r['customer_name'],
                'customer_email' => $r['customer_email'],
                'total_invoiced' => round($r['total_invoiced'], 2),
                'total_paid'     => round($r['total_paid'], 2),
                'total_unpaid'   => round($r['total_unpaid'], 2),
                'invoice_count'  => (int) $r['invoice_count'],
            ])
            ->all();

        $totals = [
            'total_invoiced' => round(array_sum(array_column($rows, 'total_invoiced')), 2),
            'total_paid'     => round(array_sum(array_column($rows, 'total_paid')), 2),
            'total_unpaid'   => round(array_sum(array_column($rows, 'total_unpaid')), 2),
            'invoice_count'  => (int) array_sum(array_column($rows, 'invoice_count')),
            'customer_count' => count($rows),
        ];

        $products = DB::table('invoice_items as ii')
            ->join('invoices as i', 'i.id', '=', 'ii.invoice_id')
            ->leftJoin('products as p', 'p.id', '=', 'ii.product_id')
            ->whereBetween('i.issue_date', [$start, $end])
            ->where('i.status', '!=', 'void')
            ->whereNull('i.deleted_at')
            ->whereNull('ii.deleted_at')
            ->groupBy('ii.product_id', 'p.name', 'ii.description')
            ->select([
                DB::raw("COALESCE(p.name, ii.description, 'Uncategorised') as product_name"),
                DB::raw('SUM(ii.amount) as total_sales'),
                DB::raw('SUM(ii.quantity) as quantity'),
                DB::raw('COUNT(DISTINCT i.id) as invoice_count'),
            ])
            ->orderByDesc('total_sales')
            ->get()
            ->map(fn ($row) => [
                'product_name'  => $row->product_name,
                'total_sales'   => round((float) $row->total_sales, 2),
                'quantity'      => round((float) $row->quantity, 2),
                'invoice_count' => (int) $row->invoice_count,
            ])
            ->all();

        return Inertia::render('Reports/IncomeByCustomer', [
            'filters'       => ['preset' => $resolved['preset'], 'start_date' => $start, 'end_date' => $end],
            'rows'          => $rows,
            'products'      => $products,
            'totals'        => $totals,
            'base_currency' => $this->tenantBaseCurrency(),
        ]);
    }

    private function tenantBaseCurrency(): string
    {
        if (function_exists('tenant') && tenant()) {
            return strtoupper((string) (tenant()->base_currency ?? 'MYR'));
        }
        if (auth()->user()?->tenant_id) {
            $t = \App\Models\Tenant::find(auth()->user()->tenant_id);
            if ($t?->base_currency) {
                return strtoupper((string) $t->base_currency);
            }
        }
        return 'MYR';
    }
}
