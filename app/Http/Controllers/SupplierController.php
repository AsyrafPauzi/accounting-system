<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\BillService;
use App\Services\MyInvoisService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class SupplierController extends Controller
{
    public function __construct(private BillService $billService) {}

    public function index(): Response
    {
        $outstandingBySupplier = $this->billService->outstandingBySupplier();

        $suppliers = Supplier::orderBy('name')->get()->map(function (Supplier $supplier) use ($outstandingBySupplier) {
            $supplier->balance = $outstandingBySupplier[$supplier->id] ?? 0.0;

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

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['currency'] = $validated['currency'] ?? 'MYR';
        $validated['is_active'] = $request->boolean('is_active', true);

        Supplier::create($validated);

        return redirect()->route('suppliers.index')->with('success', 'Supplier created successfully.');
    }

    public function show(int $id): Response
    {
        $supplier = Supplier::findOrFail($id);
        $bills = Bill::where('supplier_id', $id)
            ->with(['items', 'supplier:id,name'])
            ->orderByDesc('bill_date')
            ->limit(20)
            ->get();

        $openBills = Bill::where('supplier_id', $id)->whereNotIn('status', ['draft', 'void']);
        $totalBilled = (float) (clone $openBills)->sum('total_amount');
        $totalPaid = (float) (clone $openBills)->sum('amount_paid');
        $balance = round($totalBilled - $totalPaid, 2);
        $creditLimit = (float) ($supplier->credit_limit ?? 0);
        $remainingLimit = $creditLimit > 0 ? max(0, round($creditLimit - $balance, 2)) : null;

        return Inertia::render('Suppliers/Show', [
            'supplier' => $supplier,
            'bills' => $bills,
            'balance' => $balance,
            'stats' => [
                'total_billed' => round($totalBilled, 2),
                'total_paid' => round($totalPaid, 2),
                'balance' => $balance,
                'remaining_limit' => $remainingLimit,
            ],
            'myinvois_gaps' => MyInvoisService::supplierGaps($supplier),
        ]);
    }

    public function edit(int $id): Response
    {
        $supplier = Supplier::findOrFail($id);
        return Inertia::render('Suppliers/Edit', ['supplier' => $supplier]);
    }

    public function update(UpdateSupplierRequest $request, int $id): RedirectResponse
    {
        $supplier = Supplier::findOrFail($id);

        $validated = $request->validated();
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
