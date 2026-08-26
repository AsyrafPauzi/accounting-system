<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessOcr;
use App\Models\Account;
use App\Models\OcrJob;
use App\Models\Supplier;
use App\Services\BillService;
use App\Services\ImageMetadataStripper;
use App\Support\IndexFilters;
use App\Support\TaxCodeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptInboxController extends Controller
{
    public function __construct(
        protected BillService $billService,
        protected ImageMetadataStripper $metadataStripper,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('ocr.use');

        $filters = IndexFilters::from($request);
        $statuses = ['pending', 'processing', 'ready', 'failed', 'confirmed', 'discarded'];

        $jobs = OcrJob::query()
            ->with('bill:id,bill_number,status')
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $q->where(function ($qq) use ($filters) {
                    $qq->where('original_filename', 'like', '%'.$filters['search'].'%')
                        ->orWhere('file_path', 'like', '%'.$filters['search'].'%');
                });
            })
            ->when($filters['status'] !== '' && in_array($filters['status'], $statuses, true), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('id')
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (OcrJob $job) => $this->serializeJob($job));

        return Inertia::render('Receipts/Inbox', [
            'jobs'    => $jobs,
            'filters' => $filters,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('ocr.use');

        $request->validate([
            'receipt' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        ]);

        $file = $request->file('receipt');
        $tempPath = $file->getRealPath();
        if (is_string($tempPath) && $tempPath !== '') {
            $this->metadataStripper->strip($tempPath, $file->getMimeType());
        }

        $path = $file->store('receipts', 'public');
        if (! is_string($path) || $path === '') {
            return redirect()->back()->with('error', 'Could not save the receipt. Check storage configuration.');
        }

        $ocrJob = OcrJob::create([
            'file_path'          => $path,
            'original_filename'  => $file->getClientOriginalName(),
            'status'             => 'pending',
            'created_by'         => $request->user()?->id,
        ]);

        ProcessOcr::dispatch($path, null, $ocrJob->id);

        return redirect()->route('receipts.show', $ocrJob->id)
            ->with('success', 'Receipt uploaded. OCR is running — fields will appear when ready.');
    }

    public function show(int $id): Response|RedirectResponse
    {
        $this->authorize('ocr.use');

        $ocrJob = OcrJob::query()->findOrFail($id);
        if ($ocrJob->status === 'confirmed' && $ocrJob->bill_id) {
            return redirect()->route('bills.edit', $ocrJob->bill_id);
        }

        if ($ocrJob->status === 'discarded') {
            return redirect()->route('receipts.index')->with('error', 'This receipt was discarded.');
        }

        $parsed = $ocrJob->parsed_data ?? [];
        $defaultAccount = Account::query()->where('type', 'expense')->active()->orderBy('code')->value('code') ?? '5000';

        return Inertia::render('Receipts/Review', [
            'job'             => $this->serializeJob($ocrJob->load('bill:id,bill_number,status')),
            'suppliers'       => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'expenseAccounts' => Account::query()->where('type', 'expense')->active()->orderBy('code')->get(['code', 'name'])
                ->map(fn ($a) => ['code' => $a->code, 'name' => $a->name])
                ->values()
                ->all(),
            'taxCodes'        => TaxCodeResolver::activeOptions()->values()->all(),
            'defaults'        => $this->reviewDefaults($parsed, $defaultAccount),
        ]);
    }

    public function confirm(Request $request, int $id): RedirectResponse
    {
        $this->authorize('bills.create');

        $ocrJob = OcrJob::query()->findOrFail($id);
        if (! in_array($ocrJob->status, ['ready', 'failed'], true)) {
            return redirect()->back()->with('error', 'Only ready or failed receipts can be confirmed.');
        }

        $validated = $request->validate([
            'supplier_id'              => 'nullable|exists:suppliers,id',
            'vendor_name'              => 'nullable|string|max:255|required_without:supplier_id',
            'bill_date'                => 'required|date',
            'due_date'                 => 'nullable|date|after_or_equal:bill_date',
            'reference'                => 'nullable|string|max:100',
            'tax_code_id'              => 'nullable|exists:tax_codes,id',
            'tax_amount'               => 'nullable|numeric|min:0',
            'items'                    => 'required|array|min:1',
            'items.*.account_code'     => 'required|string|exists:accounts,code',
            'items.*.description'      => 'nullable|string|max:255',
            'items.*.quantity'         => 'nullable|numeric|min:0',
            'items.*.unit_amount'      => 'nullable|numeric|min:0',
            'items.*.amount'           => 'required|numeric|min:0',
            'items.*.tax_code_id'      => 'nullable|exists:tax_codes,id',
        ]);

        $supplier = $this->resolveSupplier($validated);

        $items = collect($validated['items'])->map(function (array $item) use ($validated) {
            $taxCodeId = $item['tax_code_id'] ?? $validated['tax_code_id'] ?? null;
            $normalized = TaxCodeResolver::normalizeLineItem([
                'tax_code_id' => $taxCodeId,
                'tax_rate'    => 0,
            ]);

            return [
                'account_code' => $item['account_code'],
                'description'  => $item['description'] ?? 'Item',
                'quantity'     => (float) ($item['quantity'] ?? 1),
                'unit_amount'  => (float) ($item['unit_amount'] ?? $item['amount']),
                'amount'       => (float) $item['amount'],
                'tax_code_id'  => $normalized['tax_code_id'],
                'tax_rate'     => $normalized['tax_rate'],
            ];
        })->all();

        $taxAmount = isset($validated['tax_amount'])
            ? (float) $validated['tax_amount']
            : (float) ($ocrJob->parsed_data['tax_amount'] ?? 0);

        $bill = $this->billService->create([
            'supplier_id'   => $supplier->id,
            'bill_date'     => $validated['bill_date'],
            'due_date'      => $validated['due_date'] ?? null,
            'reference'     => $validated['reference'] ?? ($ocrJob->parsed_data['reference'] ?? null),
            'tax_amount'    => $taxAmount,
            'receipt_path'  => $ocrJob->file_path,
            'ocr_status'    => $ocrJob->status === 'ready' ? 'completed' : 'failed',
            'ocr_data'      => $ocrJob->parsed_data,
            'created_by'    => $request->user()?->id,
            'purchase_kind' => 'credit',
        ], $items);

        $ocrJob->update([
            'status'  => 'confirmed',
            'bill_id' => $bill->id,
        ]);

        return redirect()->route('bills.edit', $bill->id)
            ->with('success', 'Bill draft created from receipt.');
    }

    public function discard(int $id): RedirectResponse
    {
        $this->authorize('ocr.use');

        $ocrJob = OcrJob::query()->findOrFail($id);
        if ($ocrJob->status === 'confirmed') {
            return redirect()->back()->with('error', 'Confirmed receipts cannot be discarded.');
        }

        $ocrJob->update(['status' => 'discarded']);

        return redirect()->route('receipts.index')->with('success', 'Receipt discarded.');
    }

    public function retry(int $id): RedirectResponse
    {
        $this->authorize('ocr.use');

        $ocrJob = OcrJob::query()->findOrFail($id);
        if (! $ocrJob->isRetryable()) {
            return redirect()->back()->with('error', 'Only failed jobs can be retried.');
        }

        $ocrJob->update([
            'status'        => 'pending',
            'parsed_data'   => null,
            'error_message' => null,
        ]);

        ProcessOcr::dispatch($ocrJob->file_path, null, $ocrJob->id);

        return redirect()->route('receipts.show', $ocrJob->id)
            ->with('success', 'OCR retry queued.');
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
    private function reviewDefaults(array $parsed, string $defaultAccount): array
    {
        $items = [];
        foreach ($parsed['items'] ?? [] as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            if ($qty <= 0) {
                $qty = 1;
            }
            $amount = (float) ($item['amount'] ?? 0);
            $unit = (float) ($item['unit_amount'] ?? ($qty > 0 ? $amount / $qty : $amount));

            $items[] = [
                'account_code' => $defaultAccount,
                'description'  => $item['description'] ?? 'Item',
                'quantity'     => $qty,
                'unit_amount'  => $unit,
                'amount'       => $amount,
                'tax_code_id'  => null,
            ];
        }

        if ($items === [] && ! empty($parsed['total_amount'])) {
            $total = (float) $parsed['total_amount'];
            $tax = (float) ($parsed['tax_amount'] ?? 0);
            $items[] = [
                'account_code' => $defaultAccount,
                'description'  => $parsed['vendor_name'] ?? 'Purchase',
                'quantity'     => 1,
                'unit_amount'  => max(0, $total - $tax),
                'amount'       => max(0, $total - $tax),
                'tax_code_id'  => null,
            ];
        }

        if ($items === []) {
            $items[] = [
                'account_code' => $defaultAccount,
                'description'  => 'Purchase',
                'quantity'     => 1,
                'unit_amount'  => 0,
                'amount'       => 0,
                'tax_code_id'  => null,
            ];
        }

        $subtotal = collect($items)->sum(fn ($i) => (float) $i['amount']);
        $taxAmount = (float) ($parsed['tax_amount'] ?? 0);
        $taxCode = TaxCodeResolver::resolve(null, $subtotal > 0 && $taxAmount > 0 ? ($taxAmount / $subtotal) * 100 : 0);

        return [
            'vendor_name'  => $parsed['vendor_name'] ?? '',
            'bill_date'    => $parsed['bill_date'] ?? now()->toDateString(),
            'due_date'     => null,
            'reference'    => $parsed['reference'] ?? '',
            'tax_amount'   => $taxAmount,
            'tax_code_id'  => $taxCode?->id,
            'items'        => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function resolveSupplier(array $validated): Supplier
    {
        if (! empty($validated['supplier_id'])) {
            return Supplier::query()->findOrFail((int) $validated['supplier_id']);
        }

        $name = trim((string) ($validated['vendor_name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Supplier name is required.');
        }

        $existing = Supplier::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return Supplier::create([
            'name'      => $name,
            'code'      => 'SUP-'.str_pad((string) ((int) Supplier::max('id') + 1), 4, '0', STR_PAD_LEFT),
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeJob(OcrJob $job): array
    {
        $parsed = $job->parsed_data ?? [];

        return [
            'id'                => $job->id,
            'file_path'         => $job->file_path,
            'original_filename' => $job->original_filename,
            'status'            => $job->status,
            'parsed_data'       => $parsed,
            'vendor_name'       => $parsed['vendor_name'] ?? null,
            'total_amount'      => isset($parsed['total_amount']) ? (float) $parsed['total_amount'] : null,
            'bill_date'         => $parsed['bill_date'] ?? null,
            'error_message'     => $job->error_message,
            'bill_id'           => $job->bill_id,
            'bill'              => $job->bill ? [
                'id'          => $job->bill->id,
                'bill_number' => $job->bill->bill_number,
                'status'      => $job->bill->status,
            ] : null,
            'receipt_url'       => $job->receipt_url,
            'created_at'        => optional($job->created_at)?->toIso8601String(),
            'is_retryable'      => $job->isRetryable(),
        ];
    }
}
