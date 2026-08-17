<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\BillService;
use App\Services\SupplierStatementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SupplierStatementController extends Controller
{
    public function __construct(private SupplierStatementService $statements) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $suppliers = Supplier::query()
            ->select(['id', 'name', 'email', 'tin'])
            ->withSum(['bills as outstanding_amount' => function ($q) {
                $q->whereNotIn('status', ['draft', 'void']);
            }], DB::raw('total_amount - amount_paid'))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('SupplierStatements/Index', [
            'suppliers' => $suppliers,
            'filters'   => ['search' => $search],
        ]);
    }

    public function show(Request $request, int $supplierId)
    {
        $supplier = Supplier::findOrFail($supplierId);
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $statement = $this->statements->build($supplier, Carbon::parse($from), Carbon::parse($to));

        return Inertia::render('SupplierStatements/Show', [
            'supplier'  => $supplier,
            'statement' => $statement,
            'openBills' => app(BillService::class)->openBillsForSupplier($supplier->id),
        ]);
    }
}
