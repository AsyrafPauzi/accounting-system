<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\Supplier;
use App\Services\BillService;
use App\Services\SupplierStatementService;
use App\Support\IndexFilters;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SupplierStatementController extends Controller
{
    public function __construct(private SupplierStatementService $statements) {}

    public function index(Request $request)
    {
        $filters = IndexFilters::from($request, 10);
        $search = $filters['search'];
        $status = $filters['status'];
        $openBill = fn ($q) => $q->whereNotIn('status', ['draft', 'void'])
            ->whereColumn('amount_paid', '<', 'total_amount');

        $suppliers = Supplier::query()
            ->select(['id', 'name', 'email', 'tin'])
            ->withCount(['bills as outstanding_bills_count' => $openBill])
            ->withSum(['bills as outstanding_amount' => function ($q) {
                $q->whereNotIn('status', ['draft', 'void']);
            }], DB::raw('total_amount - amount_paid'))
            ->when($search !== '', fn ($q) => $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('tin', 'like', "%{$search}%");
            }))
            ->when($status === 'outstanding', fn ($q) => $q->whereHas('bills', $openBill))
            ->when($status === 'settled', fn ($q) => $q->whereDoesntHave('bills', $openBill))
            ->orderBy('name')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $totalCount = Supplier::count();
        $outstandingCount = Supplier::whereHas('bills', $openBill)->count();
        $outstandingTotal = (float) Bill::query()
            ->whereNotIn('status', ['draft', 'void'])
            ->selectRaw('COALESCE(SUM(total_amount - amount_paid), 0) as t')
            ->value('t');

        return Inertia::render('SupplierStatements/Index', [
            'suppliers' => $suppliers,
            'filters' => $filters,
            'base_currency' => 'MYR',
            'totalCount' => $totalCount,
            'outstandingCount' => $outstandingCount,
            'settledCount' => $totalCount - $outstandingCount,
            'outstandingTotal' => round($outstandingTotal, 2),
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
