<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBillRequest;
use App\Http\Requests\UpdateBillRequest;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Supplier;
use App\Services\BillService;
use App\Services\ImageMetadataStripper;
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
        $lastBill = Bill::where('bill_number', 'like', 'BILL-%')->orderBy('id', 'desc')->first();
        $nextNumber = 'BILL-1';
        if ($lastBill && preg_match('/^BILL-(\d+)$/', $lastBill->bill_number, $m)) {
            $nextNumber = 'BILL-' . ((int) $m[1] + 1);
        }

        $expenseAccounts = Account::where('type', 'expense')->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->code} — {$a->name}"])->values()->all();

        return Inertia::render('Bills/Create', [
            'suppliers'           => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'expenseAccounts'     => $expenseAccounts,
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
        $bankAccounts = Account::bankOrCash()->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->name} ({$a->code})"])->values()->all();

        return Inertia::render('Bills/Edit', [
            'bill'            => $bill,
            'suppliers'       => Supplier::orderBy('name')->get(['id', 'name', 'code']),
            'expenseAccounts' => $expenseAccounts,
            'bankAccounts'    => $bankAccounts,
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

        // Receipts are the only thing this route serves. If a future flow
        // needs to expose a different prefix, add it here explicitly.
        if (! str_starts_with($path, 'receipts/')) {
            return null;
        }

        return $path;
    }
}
