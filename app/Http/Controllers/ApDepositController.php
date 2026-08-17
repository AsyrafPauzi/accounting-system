<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ApDeposit;
use App\Models\Bill;
use App\Models\Supplier;
use App\Services\ApDepositService;
use App\Services\BillService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApDepositController extends Controller
{
    public function __construct(
        protected ApDepositService $deposits,
        protected BillService $bills,
    ) {}

    public function index()
    {
        return Inertia::render('ApDeposits/Index', [
            'deposits' => ApDeposit::query()->with('supplier:id,name')->orderByDesc('id')->get(),
        ]);
    }

    public function show($id)
    {
        $deposit = ApDeposit::with(['supplier', 'applications.bill:id,bill_number,status'])->findOrFail($id);

        return Inertia::render('ApDeposits/Show', [
            'deposit'   => array_merge($deposit->toArray(), ['open_amount' => $deposit->openAmount()]),
            'openBills' => $this->bills->openBillsForSupplier((int) $deposit->supplier_id),
        ]);
    }

    public function create(Request $request)
    {
        $supplierId = $request->integer('supplier_id') ?: null;

        return Inertia::render('ApDeposits/Create', [
            'suppliers'    => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'bankAccounts' => Account::bankOrCash()->active()->orderBy('code')->get(['code', 'name']),
            'supplier_id'  => $supplierId,
            'openBills'    => $supplierId ? $this->bills->openBillsForSupplier($supplierId) : [],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id'           => 'required|exists:suppliers,id',
            'amount'                => 'required|numeric|min:0.01',
            'payment_date'          => 'required|date',
            'bank_account_code'     => 'required|string|exists:accounts,code',
            'reference'             => 'nullable|string|max:120',
            'notes'                 => 'nullable|string|max:2000',
            'allocations'           => 'nullable|array',
            'allocations.*.bill_id' => 'required_with:allocations|exists:bills,id',
            'allocations.*.amount'  => 'required_with:allocations|numeric|min:0',
        ]);

        try {
            $deposit = $this->deposits->receiveAndAllocate(
                array_merge($request->only([
                    'supplier_id', 'amount', 'payment_date', 'bank_account_code', 'notes', 'reference',
                ]), ['created_by' => auth()->id()]),
                $request->input('allocations', [])
            );
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('ap-deposits.show', $deposit->id)->with('success', 'Supplier payment recorded.');
    }

    public function apply(Request $request, $id)
    {
        $request->validate(['bill_id' => 'required|exists:bills,id', 'amount' => 'required|numeric|min:0.01']);
        try {
            $this->deposits->applyToBill(ApDeposit::findOrFail($id), Bill::findOrFail($request->bill_id), (float) $request->amount);
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Deposit applied to bill.');
    }
}
