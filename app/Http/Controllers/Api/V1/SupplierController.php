<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * /api/v1/suppliers — read-only directory of the tenant's suppliers.
 *
 * Symmetric counterpart to /api/v1/customers. Internal_notes is
 * intentionally not exposed.
 */
class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'search'    => ['nullable', 'string', 'max:200'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $page = Supplier::query()
            ->when($request->has('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->input('search'), function ($q, $s) {
                $needle = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $s) . '%';
                $q->where(function ($qq) use ($needle) {
                    $qq->where('name', 'like', $needle)
                        ->orWhere('email', 'like', $needle)
                        ->orWhere('code', 'like', $needle);
                });
            })
            ->orderBy('name')
            ->paginate((int) $request->input('per_page', 50));

        return response()->json([
            'data' => collect($page->items())->map(fn (Supplier $s) => [
                'id'             => $s->id,
                'name'           => $s->name,
                'code'           => $s->code,
                'email'          => $s->email,
                'phone'          => $s->phone,
                'tin'            => $s->tin,
                'brn'            => $s->brn,
                'currency'       => $s->currency,
                'payment_terms'  => $s->payment_terms,
                'billing_country' => $s->billing_country,
                'is_active'      => (bool) $s->is_active,
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
