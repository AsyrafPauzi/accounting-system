<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class SupplierController extends Controller
{
    public function index(): Response
    {
        $suppliers = Supplier::orderBy('name')->get()->map(function (Supplier $supplier) {
            $supplier->balance = (float) Bill::where('supplier_id', $supplier->id)
                ->whereNotIn('status', ['draft', 'void'])
                ->sum(DB::raw('total_amount - amount_paid'));
            return $supplier;
        });

        $totalAp = $suppliers->sum(fn ($s) => (float) ($s->balance ?? 0));

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'totalAp' => round($totalAp, 2),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Suppliers/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:suppliers,code',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email',
            'tin' => 'nullable|string|max:50',
            'brn' => 'nullable|string|max:50',
            'payment_terms' => 'required|integer|min:0|max:365',
            'currency' => 'nullable|string|size:3',
            'billing_street' => 'nullable|string|max:255',
            'billing_city' => 'nullable|string|max:100',
            'billing_state' => 'nullable|string|max:100',
            'billing_zip' => 'nullable|string|max:20',
            'billing_country' => 'nullable|string|max:100',
            'website' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:50',
            'segment' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'internal_notes' => 'nullable|string',
        ]);

        $validated['currency'] = $validated['currency'] ?? 'MYR';
        $validated['is_active'] = $request->boolean('is_active', true);

        Supplier::create($validated);

        return redirect()->route('suppliers.index')->with('success', 'Supplier created successfully.');
    }

    public function show(int $id): Response
    {
        $supplier = Supplier::findOrFail($id);
        $bills = Bill::where('supplier_id', $id)->with('items')->orderByDesc('bill_date')->get();

        $balance = (float) Bill::where('supplier_id', $id)
            ->whereNotIn('status', ['draft', 'void'])
            ->sum(DB::raw('total_amount - amount_paid'));

        return Inertia::render('Suppliers/Show', [
            'supplier' => $supplier,
            'bills' => $bills,
            'balance' => round($balance, 2),
        ]);
    }

    public function edit(int $id): Response
    {
        $supplier = Supplier::findOrFail($id);
        return Inertia::render('Suppliers/Edit', ['supplier' => $supplier]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $supplier = Supplier::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:50', Rule::unique('suppliers')->ignore($id)],
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email',
            'tin' => 'nullable|string|max:50',
            'brn' => 'nullable|string|max:50',
            'payment_terms' => 'required|integer|min:0|max:365',
            'currency' => 'nullable|string|size:3',
            'billing_street' => 'nullable|string|max:255',
            'billing_city' => 'nullable|string|max:100',
            'billing_state' => 'nullable|string|max:100',
            'billing_zip' => 'nullable|string|max:20',
            'billing_country' => 'nullable|string|max:100',
            'website' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:50',
            'segment' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'internal_notes' => 'nullable|string',
        ]);

        $validated['currency'] = $validated['currency'] ?? 'MYR';
        $validated['is_active'] = $request->boolean('is_active', true);

        $supplier->update($validated);

        return redirect()->route('suppliers.index')->with('success', 'Supplier updated successfully.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $supplier = Supplier::findOrFail($id);
        $hasBills = Bill::where('supplier_id', $id)->exists();
        if ($hasBills) {
            return redirect()->route('suppliers.index')
                ->with('error', 'Cannot delete supplier that has bills. Remove or reassign bills first.');
        }
        $supplier->delete();
        return redirect()->route('suppliers.index')->with('success', 'Supplier deleted.');
    }
}
