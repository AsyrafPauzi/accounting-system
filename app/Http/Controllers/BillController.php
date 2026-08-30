<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBillRequest;
use App\Http\Requests\UpdateBillRequest;
use App\Models\Account;
use App\Models\Bill;
use App\Models\BillDocumentVersion;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\DocumentNumber;
use App\Services\BillDocumentService;
use App\Services\BillService;
use App\Services\ImageMetadataStripper;
use App\Services\MyInvoisService;
use App\Services\OCRService;
use App\Services\PurchasesDocumentTrail;
use App\Support\OcrProgress;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessOcr;
use App\Support\OcrResultCache;
use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;

class BillController extends Controller
{
    public function __construct(
        protected BillService $billService,
        protected BillDocumentService $billDocumentService,
        protected OCRService $ocrService,
        protected ImageMetadataStripper $metadataStripper,
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

        $bankAccounts = Account::bankOrCash()->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->name} ({$a->code})"])->values()->all();

        return Inertia::render('Bills/Index', [
            'bills'            => $bills,
            'suppliers'        => Supplier::orderBy('name')->get(['id', 'name', 'code']),
            'bankAccounts'     => $bankAccounts,
            'totalOutstanding' => round($totalOutstanding, 2),
            'totalPaidPeriod'  => round($totalPaidPeriod, 2),
        ]);
    }

    public function create(Request $request): Response
    {
        $supplierId = $request->query('supplier_id');
        $nextNumber = DocumentNumber::next('bills', 'bill_number', 'BILL');

        $expenseAccounts = Account::where('type', 'expense')->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->code} — {$a->name}"])->values()->all();

        $bankAccounts = Account::bankOrCash()->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->name} ({$a->code})"])->values()->all();

        return Inertia::render('Bills/Create', [
            'suppliers'           => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'expenseAccounts'     => $expenseAccounts,
            'bankAccounts'        => $bankAccounts,
            'products'            => Product::query()->active()->orderBy('name')->get(['id', 'name', 'code', 'unit_price', 'tax_rate', 'account_code']),
            'nextBillNumber'      => $nextNumber,
            'preselectedSupplierId' => $supplierId ? (int) $supplierId : null,
        ]);
    }

    public function store(StoreBillRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $kind = $this->billService->normalizeKind($validated['purchase_kind'] ?? 'credit');
        $bill = $this->billService->create(
            array_merge($validated, ['created_by' => $request->user()?->id, 'purchase_kind' => $kind]),
            $request->input('items')
        );

        if ($kind === 'cash') {
            return redirect()->route('bills.show', $bill->id)->with('success', 'Cash purchase recorded and paid.');
        }
        if ($kind === 'claim') {
            return redirect()->route('bills.show', $bill->id)->with('success', 'Expense claim saved as draft. Reimburse later from the claim page.');
        }

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
        $bankAccounts = Account::bankOrCash()->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->name} ({$a->code})"])->values()->all();

        return Inertia::render('Bills/Edit', [
            'bill'            => $bill,
            'suppliers'       => Supplier::orderBy('name')->get(['id', 'name', 'code']),
            'expenseAccounts' => $expenseAccounts,
            'bankAccounts'    => $bankAccounts,
            'products'        => Product::query()->active()->orderBy('name')->get(['id', 'name', 'code', 'unit_price', 'tax_rate', 'account_code']),
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
                $validated['bank_account_code'],
                $validated['reference'] ?? null,
                $request->user()?->id,
                isset($validated['payment_exchange_rate']) ? (float) $validated['payment_exchange_rate'] : null,
            );
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('bills.show', $id)->with(
            'success',
            $bill->purchase_kind === 'claim' ? 'Reimbursement recorded.' : 'Payment recorded.'
        );
    }

    public function paymentVoucher(int $id, int $paymentId)
    {
        $bill = Bill::with('supplier')->findOrFail($id);
        $payment = $bill->payments()->findOrFail($paymentId);

        if (! $payment->voucher_number) {
            $payment->voucher_number = DocumentNumber::next('bill_payments', 'voucher_number', 'PV');
            $payment->save();
        }

        $company = tenant()?->getCompanyDetails() ?? config('invoice.company');
        $pdf = Pdf::loadView('pdf.payment-voucher', [
            'bill'     => $bill,
            'payment'  => $payment->fresh(),
            'supplier' => $bill->supplier,
            'company'  => $company,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("Payment-Voucher-{$payment->voucher_number}.pdf", ['Attachment' => false]);
    }

    public function show(int $id): Response
    {
        $with = ['supplier', 'items'];
        if (Schema::hasTable('bill_payments')) {
            $with[] = 'payments';
        }
        if (Schema::hasTable('supplier_credit_note_applications')) {
            $with[] = 'creditNoteApplications.creditNote:id,scn_number,status';
        }
        if (Schema::hasTable('ap_deposit_applications')) {
            $with[] = 'depositApplications.deposit:id,reference,status';
        }
        if (Schema::hasTable('bill_document_versions')) {
            $with[] = 'documentVersions.uploader';
        }
        $bill = Bill::with($with)->findOrFail($id);

        $bankAccounts = Account::bankOrCash()->active()->orderBy('code')->get(['code', 'name']);

        $documentVersions = [];
        if ($bill->relationLoaded('documentVersions')) {
            $documentVersions = $bill->documentVersions->map(function (BillDocumentVersion $v) use ($bill) {
                return [
                    'id' => $v->id,
                    'slot' => $v->slot,
                    'action' => $v->action,
                    'reason' => $v->reason,
                    'created_at' => optional($v->created_at)?->toIso8601String(),
                    'uploader_name' => $v->uploader?->name,
                    'original_filename' => $v->original_filename,
                    'url' => route('bills.document-versions', [$bill->id, $v->id]),
                ];
            })->values()->all();
        }

        return Inertia::render('Bills/Show', [
            'bill' => array_merge($bill->toArray(), ['balance_due' => $this->billService->remainingBalance($bill)]),
            'bankAccounts' => $bankAccounts,
            'myinvois_gaps' => app(MyInvoisService::class)->selfBilledReadiness($bill),
            'trail' => app(PurchasesDocumentTrail::class)->forBill($bill),
            'company' => tenant()?->getCompanyDetails() ?? [],
            'document_versions' => $documentVersions,
        ]);
    }

    public function batchCreate(): Response
    {
        $expenseAccounts = Account::where('type', 'expense')->active()->orderBy('code')->get(['code', 'name']);
        $bankAccounts = Account::bankOrCash()->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->name} ({$a->code})"])->values()->all();

        return Inertia::render('Bills/Batch', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'expenseAccounts' => $expenseAccounts,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function batchStore(Request $request): RedirectResponse
    {
        $request->validate([
            'rows'                         => 'required|array|min:1|max:200',
            'rows.*.supplier_id'           => 'required|exists:suppliers,id',
            'rows.*.purchase_kind'         => 'nullable|in:credit,cash,claim',
            'rows.*.bill_date'             => 'nullable|date',
            'rows.*.due_date'              => 'nullable|date',
            'rows.*.tax_amount'            => 'nullable|numeric|min:0',
            'rows.*.private_notes'         => 'nullable|string',
            'rows.*.bank_account_code'     => 'nullable|required_if:rows.*.purchase_kind,cash|string|exists:accounts,code',
            'rows.*.items'                 => 'required|array|min:1',
            'rows.*.items.*.description'   => 'required|string|max:255',
            'rows.*.items.*.quantity'      => 'required|numeric|min:0.01',
            'rows.*.items.*.unit_price'    => 'required|numeric|min:0',
            'rows.*.items.*.account_code'  => 'nullable|string|exists:accounts,code',
        ]);

        $created = 0;
        foreach ($request->input('rows') as $row) {
            $kind = $this->billService->normalizeKind($row['purchase_kind'] ?? 'credit');
            $items = collect($row['items'] ?? [])->map(function ($item) {
                $qty = (float) $item['quantity'];
                $unit = (float) ($item['unit_price'] ?? $item['unit_amount'] ?? 0);

                return [
                    'account_code' => $item['account_code'] ?? '5000',
                    'description'  => $item['description'],
                    'quantity'     => $qty,
                    'unit_amount'  => $unit,
                    'amount'       => round($qty * $unit, 2),
                ];
            })->all();

            $this->billService->create([
                'bill_number'       => $this->billService->nextNumber(),
                'supplier_id'       => $row['supplier_id'],
                'bill_date'         => $row['bill_date'] ?? now()->toDateString(),
                'due_date'          => $row['due_date'] ?? null,
                'tax_amount'        => $row['tax_amount'] ?? 0,
                'private_notes'     => $row['private_notes'] ?? null,
                'purchase_kind'     => $kind,
                'bank_account_code' => $row['bank_account_code'] ?? null,
                'created_by'        => $request->user()?->id,
            ], $items);
            $created++;
        }

        return redirect()->route('bills.index')->with('success', "{$created} bill(s) created.");
    }

    public function submitMyInvois(int $id): RedirectResponse
    {
        $bill = Bill::with(['supplier', 'items'])->findOrFail($id);
        try {
            app(MyInvoisService::class)->submitSelfBilled($bill);
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Self-billed e-invoice submitted to MyInvois.');
    }

    public function refreshMyInvois(int $id): RedirectResponse
    {
        try {
            app(MyInvoisService::class)->refreshStatus(Bill::findOrFail($id));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'MyInvois status refreshed.');
    }

    public function cancelMyInvois(Request $request, int $id): RedirectResponse
    {
        $request->validate(['reason' => 'required|string|max:500']);
        try {
            app(MyInvoisService::class)->cancel(Bill::findOrFail($id), $request->input('reason'));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Self-billed e-invoice cancelled.');
    }

    /**
     * Upload a bill document (supplier invoice and/or payment receipt).
     * Supplier invoice triggers OCR; payment receipt is storage-only.
     */
    public function uploadDocument(Request $request): \Illuminate\Http\JsonResponse
    {
        if ($request->hasFile('receipt') && ! $request->hasFile('document')) {
            $request->files->set('document', $request->file('receipt'));
        }

        $request->validate([
            'slot' => 'required|in:supplier_invoice,payment_receipt',
            'document' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
            'bill_id' => 'nullable|exists:bills,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $slot = (string) $request->input('slot');
        if ($slot === 'supplier_invoice') {
            $tenant = tenant();
            $planOk = $tenant && method_exists($tenant, 'hasPlanPermission')
                ? $tenant->hasPlanPermission('ocr.use')
                : true;
            abort_unless($planOk, 403, 'OCR / supplier invoice upload is not available on this plan.');
        }

        $file = $request->file('document');
        $billId = $request->filled('bill_id') ? (int) $request->input('bill_id') : null;
        $reason = $request->input('reason');
        $userId = $request->user()?->id;

        try {
            if ($billId) {
                $bill = Bill::findOrFail($billId);
                $version = $this->billDocumentService->attach(
                    $bill,
                    $slot,
                    $file,
                    is_string($reason) ? $reason : null,
                    $userId
                );
                $path = $version->path;
                $isDraft = $bill->status === 'draft';
            } else {
                $path = $this->billDocumentService->storeFile($file);
                $isDraft = true;
            }
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }

        $runOcr = $slot === 'supplier_invoice';
        $applyOcr = $runOcr && $isDraft;

        if ($runOcr) {
            ProcessOcr::dispatch($path, $billId);
        }

        $url = $billId
            ? route('bills.document', $billId).'?slot='.urlencode($slot)
            : route('bills.receipt', 0).'?path='.urlencode($path);

        return response()->json([
            'success' => true,
            'slot' => $slot,
            'status' => $runOcr ? 'pending' : 'stored',
            'apply_ocr' => $applyOcr,
            'path' => $path,
            'url' => $url,
            ...($runOcr ? OcrProgress::forUploadPercent(100) : [
                'phase' => 'done',
                'progress' => 100,
                'label' => 'Payment receipt attached',
            ]),
        ]);
    }

    /**
     * Legacy alias — supplier invoice + OCR.
     */
    public function uploadReceipt(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->merge(['slot' => 'supplier_invoice']);

        return $this->uploadDocument($request);
    }

    /**
     * Poll the status of background OCR processing.
     */
    public function ocrStatus(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->input('path');
        $result = OcrResultCache::get($path);
        $startedAt = (int) $request->query('started_at', 0);
        $elapsed = $startedAt > 0
            ? max(0, (int) (microtime(true) * 1000) - $startedAt)
            : 5_000;

        if ($result !== null) {
            $done = ($result['status'] ?? null) === 'success';
            $prog = $done ? OcrProgress::completed() : OcrProgress::failed();

            return response()->json([
                'status' => $done ? 'completed' : 'failed',
                'ocr_data' => $result['data'] ?? null,
                'error' => $result['error'] ?? null,
                ...$prog,
            ]);
        }

        return response()->json([
            'status' => 'pending',
            ...OcrProgress::forPending($elapsed),
        ]);
    }

    /**
     * Serve the current document for a bill slot (or legacy ?path= preview).
     */
    public function showDocument(Request $request, $id = null)
    {
        abort_if(tenant() === null, 403, 'Tenant context required.');

        $path = $request->query('path');
        $slot = $request->query('slot', 'supplier_invoice');

        if (! $path && $id) {
            $bill = Bill::find($id);
            if ($bill) {
                $column = $slot === 'payment_receipt' ? 'payment_receipt_path' : 'supplier_invoice_path';
                $path = $bill->{$column};
            }
        }

        return $this->respondWithStoredPath($path);
    }

    /**
     * Serve a historical document version.
     */
    public function showDocumentVersion(Request $request, int $id, int $version)
    {
        abort_if(tenant() === null, 403, 'Tenant context required.');

        $row = BillDocumentVersion::query()
            ->where('bill_id', $id)
            ->where('id', $version)
            ->firstOrFail();

        return $this->respondWithStoredPath($row->path);
    }

    /**
     * Legacy receipt URL — defaults to supplier invoice when no ?path=.
     */
    public function showReceipt(Request $request, $id = null)
    {
        if (! $request->query('path') && ! $request->query('slot') && $id) {
            $request->query->set('slot', 'supplier_invoice');
        }

        return $this->showDocument($request, $id);
    }

    private function respondWithStoredPath(?string $path)
    {
        $path = $this->sanitiseReceiptPath($path);
        if ($path === null) {
            abort(404);
        }

        if (str_starts_with($path, 'copilot-receipts/')) {
            if (! Storage::disk('local')->exists($path)) {
                abort(404);
            }

            return Storage::disk('local')->response($path);
        }

        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }

    /**
     * Normalise a user-supplied receipt path, returning null for anything
     * that doesn't look like a legitimate receipt key. Used as the single
     * choke point for everything that resolves a path on the public disk.
     */
    private function sanitiseReceiptPath(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        // Strip leading slashes and the legacy `storage/` prefix (older bills
        // sometimes have receipt_path saved with that prefix).
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        // Block obvious traversal / null-byte / encoded slash attempts. S3
        // wouldn't resolve `..` segments anyway, but the local disk fallback
        // would, and we don't want to rely on the storage backend's quirks
        // for security.
        if (str_contains($path, "\0")
            || str_contains($path, '..')
            || str_contains($path, '\\')) {
            return null;
        }

        // Bill uploads use receipts/; older copilot drafts used copilot-receipts/.
        if (! str_starts_with($path, 'receipts/') && ! str_starts_with($path, 'copilot-receipts/')) {
            return null;
        }

        return $path;
    }
}
