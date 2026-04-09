<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBillRequest;
use App\Http\Requests\UpdateBillRequest;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Supplier;
use App\Services\BillService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class BillController extends Controller
{
    public function __construct(protected BillService $billService) {}

    public function index(Request $request): Response
    {
        $query = Bill::with('supplier')->orderByDesc('bill_date');

        $statusFilter = $request->input('status');
        $supplierId = $request->input('supplier_id');
        if ($statusFilter && in_array($statusFilter, ['draft', 'unpaid', 'partially paid', 'paid', 'void'], true)) {
            $query->where('status', $statusFilter);
        }
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $bills = $query->get()->map(function (Bill $bill) {
            $bill->supplier_name = $bill->supplier?->name ?? '—';
            $bill->balance_due = $bill->balance_due;
            return $bill;
        });

        $totalOutstanding = $bills->whereIn('status', ['unpaid', 'partially paid'])->sum(fn (Bill $b) => (float) $b->total_amount - (float) $b->amount_paid);
        $totalPaidPeriod = $bills->where('status', 'paid')->sum('amount_paid');
        $assetAccounts = Account::where('type', 'asset')->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->code} — {$a->name}"])->values()->all();

        return Inertia::render('Bills/Index', [
            'bills'            => $bills,
            'suppliers'        => Supplier::orderBy('name')->get(['id', 'name', 'code']),
            'assetAccounts'    => $assetAccounts,
            'totalOutstanding' => round($totalOutstanding, 2),
            'totalPaidPeriod'  => round($totalPaidPeriod, 2),
        ]);
    }

    public function create(Request $request): Response
    {
        $supplierId = $request->query('supplier_id');
        $lastBill = Bill::where('bill_number', 'like', 'BILL-%')->orderBy('id', 'desc')->first();
        $nextNumber = 'BILL-1';
        if ($lastBill && preg_match('/^BILL-(\d+)$/', $lastBill->bill_number, $m)) {
            $nextNumber = 'BILL-' . ((int) $m[1] + 1);
        }

        $expenseAccounts = Account::where('type', 'expense')->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->code} — {$a->name}"])->values()->all();
        $assetAccounts = Account::where('type', 'asset')->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->code} — {$a->name}"])->values()->all();

        return Inertia::render('Bills/Create', [
            'suppliers'           => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'expenseAccounts'     => $expenseAccounts,
            'assetAccounts'       => $assetAccounts,
            'nextBillNumber'      => $nextNumber,
            'preselectedSupplierId' => $supplierId ? (int) $supplierId : null,
        ]);
    }

    public function store(StoreBillRequest $request): RedirectResponse
    {
        $bill = $this->billService->create(
            array_merge($request->validated(), ['created_by' => $request->user()?->id]),
            $request->input('items')
        );

        return redirect()->route('bills.edit', $bill->id)->with('success', 'Bill created as draft.');
    }

    public function edit(int $id): Response|RedirectResponse
    {
        $bill = Bill::with(['supplier', 'items'])->findOrFail($id);
        $expenseAccounts = Account::where('type', 'expense')->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->code} — {$a->name}"])->values()->all();
        $assetAccounts = Account::where('type', 'asset')->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->code} — {$a->name}"])->values()->all();

        return Inertia::render('Bills/Edit', [
            'bill'            => $bill,
            'suppliers'       => Supplier::orderBy('name')->get(['id', 'name', 'code']),
            'expenseAccounts' => $expenseAccounts,
            'assetAccounts'   => $assetAccounts,
        ]);
    }

    public function update(UpdateBillRequest $request, int $id): RedirectResponse
    {
        $bill = Bill::findOrFail($id);
        if ($bill->status !== 'draft') {
            return redirect()->route('bills.edit', $id)->with('error', 'Only draft bills can be edited.');
        }

        $validated = $request->validated();
        $this->billService->update($bill, $validated, $validated['items']);

        return redirect()->route('bills.edit', $id)->with('success', 'Bill updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $bill = Bill::findOrFail($id);
        if ($bill->status !== 'draft') {
            return redirect()->route('bills.index')->with('error', 'Only draft bills can be deleted.');
        }
        $bill->items()->delete();
        $bill->delete();
        return redirect()->route('bills.index')->with('success', 'Bill deleted.');
    }

    public function postBill(int $id): RedirectResponse
    {
        $bill = Bill::with('items')->findOrFail($id);
        try {
            $this->billService->post($bill);
            return redirect()->back()->with('success', 'Bill posted to ledger.');
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function voidBill(int $id): RedirectResponse
    {
        $bill = Bill::findOrFail($id);
        try {
            $this->billService->void($bill);
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        return redirect()->back()->with('success', 'Bill voided.');
    }

    public function recordPayment(\App\Http\Requests\RecordPaymentRequest $request, int $id): RedirectResponse
    {
        $validated = $request->validated();

        $bill = Bill::findOrFail($id);
        try {
            $this->billService->recordPayment(
                $bill,
                (float) $validated['amount'],
                $validated['payment_date'],
                $validated['bank_account_code']
            );
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('bills.index')->with('success', 'Payment recorded.');
    }
}
