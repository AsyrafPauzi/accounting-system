<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Account;
use App\Models\Product;
use App\Support\IndexFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('products.view');

        $filters = IndexFilters::from($request, 10);
        $search = $filters['search'];
        $statusFilter = $filters['status'];

        $query = Product::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $products = $query
            ->orderBy('display_order')
            ->orderBy('name')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $totalCount = Product::count();
        $activeCount = Product::where('is_active', true)->count();

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => $filters,
            'totalCount' => $totalCount,
            'activeCount' => $activeCount,
            'inactiveCount' => $totalCount - $activeCount,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('products.create');

        return Inertia::render('Products/Create', [
            'incomeAccounts' => $this->incomeAccountOptions(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        Product::create($request->validated());

        return redirect()
            ->route('products.index')
            ->with('success', 'Product saved.');
    }

    public function edit(int $id): Response
    {
        $this->authorize('products.edit');

        $product = Product::findOrFail($id);

        return Inertia::render('Products/Edit', [
            'product'        => $product,
            'incomeAccounts' => $this->incomeAccountOptions(),
        ]);
    }

    public function update(UpdateProductRequest $request, int $id): RedirectResponse
    {
        $product = Product::findOrFail($id);
        $product->update($request->validated());

        return redirect()
            ->route('products.index')
            ->with('success', 'Product updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('products.delete');

        $product = Product::findOrFail($id);
        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('success', 'Product removed from catalogue.');
    }

    /**
     * Revenue (income) account dropdown options for the picker.
     * Soft-link via `code` because that's what invoice items reference.
     */
    private function incomeAccountOptions(): array
    {
        return Account::query()
            ->where('type', 'income')
            ->active()
            ->orderBy('code')
            ->get(['code', 'name'])
            ->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->code} — {$a->name}"])
            ->values()
            ->all();
    }
}
