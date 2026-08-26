<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Support\ReportPeriod;
use App\Services\BillService;
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
    public function __construct(private BillService $billService) {}

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
        foreach (Bill::with('supplier:id,name,email')
            ->where('status', '!=', 'void')
            ->whereBetween('bill_date', [$start, $end])
            ->get() as $bill) {
            $supplierId = $bill->supplier_id;
            if (! isset($grouped[$supplierId])) {
                $grouped[$supplierId] = [
                    'supplier_id'    => $supplierId,
                    'supplier_name'  => $bill->supplier?->name ?? '— Deleted supplier —',
                    'supplier_email' => $bill->supplier?->email,
                    'total_billed'   => 0.0,
                    'total_paid'     => 0.0,
                    'total_unpaid'   => 0.0,
                    'bill_count'     => 0,
                ];
            }
            $grouped[$supplierId]['total_billed'] += (float) $bill->total_amount;
            $grouped[$supplierId]['total_paid'] += (float) $bill->amount_paid;
            $grouped[$supplierId]['total_unpaid'] += max(0, $this->billService->remainingBalance($bill));
            $grouped[$supplierId]['bill_count']++;
        }

        $rows = collect($grouped)
            ->sortByDesc('total_billed')
            ->values()
            ->map(fn ($r) => [
                'supplier_id'    => $r['supplier_id'],
                'supplier_name'  => $r['supplier_name'],
                'supplier_email' => $r['supplier_email'],
                'total_billed'   => round($r['total_billed'], 2),
                'total_paid'     => round($r['total_paid'], 2),
                'total_unpaid'   => round($r['total_unpaid'], 2),
                'bill_count'     => (int) $r['bill_count'],
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
            'filters'       => ['preset' => $resolved['preset'], 'start_date' => $start, 'end_date' => $end],
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
