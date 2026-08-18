<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBillRequest;
use App\Http\Requests\UpdateBillRequest;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Product;
use App\Models\Supplier;
use App\Support\DocumentNumber;
use App\Services\BillService;
use App\Services\ImageMetadataStripper;
use App\Services\MyInvoisService;
use App\Services\OCRService;
use App\Services\PurchasesDocumentTrail;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessOcr;
use App\Support\OcrResultCache;

class BillController extends Controller
{
    public function __construct(
        protected BillService $billService,
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
                $request->user()?->id
            );
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('bills.show', $id)->with(
            'success',
            $bill->purchase_kind === 'claim' ? 'Reimbursement recorded.' : 'Payment recorded.'
        );
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
        $bill = Bill::with($with)->findOrFail($id);

        $bankAccounts = Account::bankOrCash()->active()->orderBy('code')->get(['code', 'name']);

        return Inertia::render('Bills/Show', [
            'bill' => array_merge($bill->toArray(), ['balance_due' => $this->billService->remainingBalance($bill)]),
            'bankAccounts' => $bankAccounts,
            'myinvois_gaps' => app(MyInvoisService::class)->selfBilledReadiness($bill),
            'trail' => app(PurchasesDocumentTrail::class)->forBill($bill),
            'company' => tenant()?->getCompanyDetails() ?? [],
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
     * Upload a receipt and process it with OCR.
     */
    public function uploadReceipt(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            // Images for photo-of-receipt uploads, PDFs for e-receipts (Shopee, fuel
            // station tax invoices, etc.). Tesseract handles PDFs via PdfPreprocessor;
            // Gemini handles them natively.
            'receipt' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
            'bill_id' => 'nullable|exists:bills,id',
        ]);

        $file = $request->file('receipt');

        // PDPA: scrub EXIF/GPS/device metadata from phone photos before the
        // bytes leave the temp directory. Best-effort — if magick isn't
        // available or fails, the upload still proceeds with the original
        // file (the stripper logs the failure for ops visibility).
        $tempPath = $file->getRealPath();
        if (is_string($tempPath) && $tempPath !== '') {
            $this->metadataStripper->strip($tempPath, $file->getMimeType());
        }

        $path = $file->store('receipts', 'public');

        if (! is_string($path) || $path === '') {
            return response()->json([
                'success' => false,
                'error' => 'Could not save the receipt. If using S3, check AWS_BUCKET and credentials; locally use FILESYSTEM_PUBLIC_DRIVER=local.',
            ], 500);
        }

        if ($request->has('bill_id')) {
            $bill = Bill::findOrFail($request->bill_id);
            $bill->update([
                'receipt_path' => $path,
                'ocr_status' => 'pending',
            ]);
        }

        // Dispatch background OCR job
        ProcessOcr::dispatch($path, $request->bill_id ? (int) $request->bill_id : null);

        return response()->json([
            'success' => true,
            'status' => 'pending',
            'path' => $path,
            'url' => route('bills.receipt', $request->bill_id ?? 0) . '?path=' . urlencode($path),
        ]);
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

        if ($result !== null) {
            $status = ($result['status'] ?? null) === 'success' ? 'completed' : 'failed';

            return response()->json([
                'status' => $status,
                'ocr_data' => $result['data'] ?? null,
                'error' => $result['error'] ?? null,
            ]);
        }

        return response()->json([
            'status' => 'pending',
        ]);
    }

    /**
     * Serve the receipt file securely.
     *
     * Defence in depth — even though Stancl's FilesystemTenancyBootstrapper
     * prefixes the public disk's root with the current tenant id (so a
     * cross-tenant `?path=` lookup naturally misses), we still:
     *   1. require an active tenant context (no central-side leakage),
     *   2. reject traversal sequences and absolute paths up front,
     *   3. require the path to live under `receipts/` (the only prefix we
     *      ever write to from upload flows),
     * before letting Storage handle the lookup.
     *
     * The `?path=` parameter is preserved because the bill-create flow
     * needs a way to preview a freshly uploaded receipt *before* the bill
     * row exists in the DB — otherwise we'd just key off the bill id.
     */
    public function showReceipt(Request $request, $id = null)
    {
        abort_if(tenant() === null, 403, 'Tenant context required.');

        $path = $request->query('path');

        if (! $path && $id) {
            $bill = Bill::find($id);
            $path = $bill?->receipt_path;
        }

        $path = $this->sanitiseReceiptPath($path);
        if ($path === null) {
            abort(404);
        }

        // Legacy copilot attachments lived on the local disk.
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
