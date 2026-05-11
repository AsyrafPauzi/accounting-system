<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBillRequest;
use App\Http\Requests\UpdateBillRequest;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Supplier;
use App\Services\BillService;
use App\Services\OCRService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class BillController extends Controller
{
    public function __construct(
        protected BillService $billService,
        protected OCRService $ocrService
    ) {}

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

        $bills = $query->get();

        $totalOutstanding = $bills->whereIn('status', ['unpaid', 'partially paid'])->sum('balance_due');
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
        $journalEntryId = \Illuminate\Support\Facades\DB::table('journal_entries')
            ->where('reference_type', 'Bill')
            ->where('reference_id', $bill->id)
            ->latest()
            ->value('id');

        $expenseAccounts = Account::where('type', 'expense')->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->code} — {$a->name}"])->values()->all();
        $assetAccounts = Account::where('type', 'asset')->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->code} — {$a->name}"])->values()->all();

        return Inertia::render('Bills/Edit', [
            'bill'            => $bill,
            'suppliers'       => Supplier::orderBy('name')->get(['id', 'name', 'code']),
            'expenseAccounts' => $expenseAccounts,
            'assetAccounts'   => $assetAccounts,
            'journal_entry_id' => $journalEntryId,
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

    /**
     * Upload a receipt and process it with OCR.
     */
    public function uploadReceipt(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'receipt' => 'required|image|max:10240', // 10MB max
            'bill_id' => 'nullable|exists:bills,id',
        ]);

        $file = $request->file('receipt');
        $path = $file->store('receipts', 'public');

        // Process OCR
        $ocrResult = $this->ocrService->process($path);

        if ($request->has('bill_id')) {
            $bill = Bill::findOrFail($request->bill_id);
            $bill->update([
                'receipt_path' => $path,
                'ocr_status' => $ocrResult['status'] === 'success' ? 'completed' : 'failed',
                'ocr_data' => $ocrResult['data'] ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => route('bills.receipt', $request->bill_id ?? 0) . '?path=' . urlencode($path),
            'ocr_data' => $ocrResult['data'] ?? null,
        ]);
    }

    /**
     * Serve the receipt file securely.
     */
    public function showReceipt(Request $request, $id = null)
    {
        $path = $request->query('path');
        
        if (!$path && $id) {
            $bill = Bill::find($id);
            $path = $bill?->receipt_path;
        }

        if (!$path) {
            abort(404);
        }

        // Clean up path
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        if (!Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($path));
    }
}
