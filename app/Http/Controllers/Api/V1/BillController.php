<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * /api/v1/bills — read-only feed of supplier bills.
 *
 * Symmetric counterpart to /api/v1/invoices. Designed for partners
 * that need a "money-going-out" view of the tenant's payables.
 */
class BillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'      => ['nullable', 'string', 'in:draft,posted,paid,partial,overdue,void'],
            'supplier_id' => ['nullable', 'integer'],
            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $page = Bill::query()
            ->with(['supplier:id,name,email'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('supplier_id'), fn ($q, $s) => $q->where('supplier_id', $s))
            ->when($request->input('start_date'), fn ($q, $d) => $q->whereDate('bill_date', '>=', $d))
            ->when($request->input('end_date'),   fn ($q, $d) => $q->whereDate('bill_date', '<=', $d))
            ->orderByDesc('bill_date')
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 50));

        return response()->json([
            'data' => collect($page->items())->map(fn (Bill $bill) => [
                'id'              => $bill->id,
                'uuid'            => $bill->uuid,
                'bill_number'     => $bill->bill_number,
                'reference'       => $bill->reference ?? null,
                'status'          => $bill->status,
                'bill_date'       => optional($bill->bill_date)->toDateString(),
                'due_date'        => optional($bill->due_date)->toDateString(),
                'currency'        => $bill->currency,
                'total_amount'    => (float) $bill->total_amount,
                'amount_paid'     => (float) $bill->amount_paid,
                'balance_due'     => round((float) $bill->total_amount - (float) $bill->amount_paid, 2),
                'supplier' => $bill->supplier ? [
                    'id'    => $bill->supplier->id,
                    'name'  => $bill->supplier->name,
                    'email' => $bill->supplier->email,
                ] : null,
            ])->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }
}
