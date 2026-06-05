<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Purchases by Vendor
 *
 * Per supplier, over a date range:
 *   - total billed   (excludes draft + void)
 *   - paid amount    (sum of amount_paid)
 *   - unpaid amount  (total - paid)
 *   - bill count
 *
 * Mirror of "Income by Customer" but for the AP side.
 */
class PurchasesByVendorController extends Controller
{
    public function index(Request $request): Response
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ]);

        $start = Carbon::parse($request->input('start_date', now()->startOfYear()->toDateString()))->toDateString();
        $end = Carbon::parse($request->input('end_date', now()->toDateString()))->toDateString();

        $rows = DB::table('bills as b')
            ->leftJoin('suppliers as s', 's.id', '=', 'b.supplier_id')
            ->select([
                'b.supplier_id',
                's.name as supplier_name',
                's.email as supplier_email',
                DB::raw('SUM(b.total_amount) as total_billed'),
                DB::raw('SUM(b.amount_paid) as total_paid'),
                DB::raw('SUM(b.total_amount - b.amount_paid) as total_unpaid'),
                DB::raw('COUNT(b.id) as bill_count'),
            ])
            ->where('b.status', '!=', 'void')
            ->whereBetween('b.bill_date', [$start, $end])
            ->whereNull('b.deleted_at')
            ->groupBy('b.supplier_id', 's.name', 's.email')
            ->orderByDesc('total_billed')
            ->get()
            ->map(fn ($r) => [
                'supplier_id'    => $r->supplier_id,
                'supplier_name'  => $r->supplier_name ?? '— Deleted supplier —',
                'supplier_email' => $r->supplier_email,
                'total_billed'   => round((float) $r->total_billed, 2),
                'total_paid'     => round((float) $r->total_paid, 2),
                'total_unpaid'   => round((float) $r->total_unpaid, 2),
                'bill_count'     => (int) $r->bill_count,
            ])
            ->all();

        $totals = [
            'total_billed'   => round(array_sum(array_column($rows, 'total_billed')), 2),
            'total_paid'     => round(array_sum(array_column($rows, 'total_paid')), 2),
            'total_unpaid'   => round(array_sum(array_column($rows, 'total_unpaid')), 2),
            'bill_count'     => (int) array_sum(array_column($rows, 'bill_count')),
            'supplier_count' => count($rows),
        ];

        return Inertia::render('Reports/PurchasesByVendor', [
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
