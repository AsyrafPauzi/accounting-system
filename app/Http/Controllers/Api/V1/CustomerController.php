<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * /api/v1/customers — read-only directory of the tenant's customers.
 *
 * Deliberately omits internal-only fields (internal_notes, risk_rating,
 * credit_limit, credit_hold, account_manager) — partners get the
 * shipping/billing identity columns and contact details only.
 */
class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'search'    => ['nullable', 'string', 'max:200'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $page = Customer::query()
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
            'data' => collect($page->items())->map(fn (Customer $c) => [
                'id'             => $c->id,
                'name'           => $c->name,
                'code'           => $c->code,
                'email'          => $c->email,
                'phone'          => $c->phone,
                'tin'            => $c->tin,
                'brn'            => $c->brn,
                'currency'       => $c->currency,
                'payment_terms'  => $c->payment_terms,
                'billing_country' => $c->billing_country,
                'is_active'      => (bool) $c->is_active,
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
