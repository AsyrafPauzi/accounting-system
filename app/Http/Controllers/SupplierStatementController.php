<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\BillService;
use App\Services\SupplierStatementService;
use App\Support\IndexFilters;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierStatementController extends Controller
{
    public function __construct(
        private SupplierStatementService $statements,
        private BillService $billService,
    ) {}

    public function index(Request $request)
    {
        $filters = IndexFilters::from($request, 10);
        $search = $filters['search'];
        $status = $filters['status'];
        $bySupplier = $this->billService->outstandingBySupplier();
        $countsBySupplier = $this->billService->openBillCountBySupplier();
        $outstandingSupplierIds = array_keys(array_filter($bySupplier, fn ($amount) => $amount > 0));

        $suppliers = Supplier::query()
            ->select(['id', 'name', 'email', 'tin'])
            ->when($search !== '', fn ($q) => $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('tin', 'like', "%{$search}%");
            }))
            ->when($status === 'outstanding', fn ($q) => $q->whereIn('id', $outstandingSupplierIds ?: [-1]))
            ->when($status === 'settled', fn ($q) => $q->whereNotIn('id', $outstandingSupplierIds ?: [-1]))
            ->orderBy('name')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $suppliers->getCollection()->transform(function ($supplier) use ($bySupplier, $countsBySupplier) {
            $supplier->outstanding_amount = $bySupplier[$supplier->id] ?? 0.0;
            $supplier->outstanding_bills_count = $countsBySupplier[$supplier->id] ?? 0;

            return $supplier;
        });

        $totalCount = Supplier::count();
        $outstandingCount = count($outstandingSupplierIds);
        $outstandingTotal = round(array_sum($bySupplier), 2);

        return Inertia::render('SupplierStatements/Index', [
            'suppliers' => $suppliers,
            'filters' => $filters,
            'base_currency' => $this->tenantBaseCurrency(),
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

    private function tenantBaseCurrency(): string
    {
        if (function_exists('tenant') && tenant()) {
            return strtoupper((string) (tenant()->base_currency ?? 'MYR'));
        }

        return 'MYR';
    }
}
