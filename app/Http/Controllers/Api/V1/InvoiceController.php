<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * /api/v1/invoices — read-only feed of customer invoices.
 *
 * Designed for partners that need a "money-coming-in" view of the
 * tenant's books. Mirrors the columns shown on the in-app invoices
 * index, minus internal notes / per-line audit fields.
 *
 * Pagination follows Laravel's default LengthAwarePaginator shape
 * (`data`, `meta.{current_page,per_page,total,last_page}`).
 */
class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'      => ['nullable', 'string', 'in:draft,posted,paid,partial,overdue,void'],
            'customer_id' => ['nullable', 'integer'],
            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $page = Invoice::query()
            ->with(['customer:id,name,email'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('customer_id'), fn ($q, $c) => $q->where('customer_id', $c))
            ->when($request->input('start_date'), fn ($q, $d) => $q->whereDate('issue_date', '>=', $d))
            ->when($request->input('end_date'),   fn ($q, $d) => $q->whereDate('issue_date', '<=', $d))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate((int) $request->input('per_page', 50));

        return response()->json([
            'data' => collect($page->items())->map(fn (Invoice $inv) => [
                'id'                 => $inv->id,
                'uuid'               => $inv->uuid,
                'invoice_number'     => $inv->invoice_number,
                'status'             => $inv->status,
                'issue_date'         => optional($inv->issue_date)->toDateString(),
                'due_date'           => optional($inv->due_date)->toDateString(),
                'currency'           => $inv->currency,
                'amount_before_tax'  => (float) $inv->amount_before_tax,
                'tax_amount'         => (float) $inv->tax_amount,
                'total_amount'       => (float) $inv->total_amount,
                'amount_paid'        => (float) $inv->amount_paid,
                'balance_due'        => round((float) $inv->total_amount - (float) $inv->amount_paid, 2),
                'customer' => $inv->customer ? [
                    'id'    => $inv->customer->id,
                    'name'  => $inv->customer->name,
                    'email' => $inv->customer->email,
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
