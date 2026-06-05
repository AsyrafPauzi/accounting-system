<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
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
    public function index(Request $request): Response
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        $start = Carbon::parse($request->input('start_date', now()->startOfYear()->toDateString()))->toDateString();
        $end = Carbon::parse($request->input('end_date', now()->toDateString()))->toDateString();

        $rows = DB::table('invoices as i')
            ->leftJoin('customers as c', 'c.id', '=', 'i.customer_id')
            ->select([
                'i.customer_id',
                'c.name as customer_name',
                'c.email as customer_email',
                DB::raw('SUM(i.total_amount) as total_invoiced'),
                DB::raw('SUM(i.amount_paid) as total_paid'),
                DB::raw('SUM(i.total_amount - i.amount_paid) as total_unpaid'),
                DB::raw('COUNT(i.id) as invoice_count'),
            ])
            ->whereNotIn('i.status', ['draft', 'void'])
            ->whereBetween('i.issue_date', [$start, $end])
            ->whereNull('i.deleted_at')
            ->groupBy('i.customer_id', 'c.name', 'c.email')
            ->orderByDesc('total_invoiced')
            ->get()
            ->map(fn ($r) => [
                'customer_id'    => $r->customer_id,
                'customer_name'  => $r->customer_name ?? '— Deleted customer —',
                'customer_email' => $r->customer_email,
                'total_invoiced' => round((float) $r->total_invoiced, 2),
                'total_paid'     => round((float) $r->total_paid, 2),
                'total_unpaid'   => round((float) $r->total_unpaid, 2),
                'invoice_count'  => (int) $r->invoice_count,
            ])
            ->all();

        $totals = [
            'total_invoiced' => round(array_sum(array_column($rows, 'total_invoiced')), 2),
            'total_paid'     => round(array_sum(array_column($rows, 'total_paid')), 2),
            'total_unpaid'   => round(array_sum(array_column($rows, 'total_unpaid')), 2),
            'invoice_count'  => (int) array_sum(array_column($rows, 'invoice_count')),
            'customer_count' => count($rows),
        ];

        return Inertia::render('Reports/IncomeByCustomer', [
            'filters'       => ['start_date' => $start, 'end_date' => $end],
            'rows'          => $rows,
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
