<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\IndexFilters;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('products.view');

        $filters = IndexFilters::from($request, 25);
        $search = $filters['search'];

        $query = Product::query()->where('track_inventory', true);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $products = $query
            ->orderBy('name')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return Inertia::render('Inventory/Index', [
            'products' => $products,
            'filters'  => $filters,
        ]);
    }
}
