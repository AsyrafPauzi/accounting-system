<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Services\InvoiceService;
use App\Services\SalesDocumentTrail;
use App\Services\ToyyibpayService;
use App\Support\DocumentNumber;
use App\Support\ShareLink;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Customer;
use App\Models\Product;
use App\Services\DocumentBulkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoiceService) {}

    /**
     * Company books base currency (for FX hint on invoices).
     */
    protected function tenantBaseCurrency(): string
    {
        if (function_exists('tenant') && tenant()) {
            return strtoupper((string) (tenant()->base_currency ?? 'MYR'));
        }
        if (auth()->check() && auth()->user()->tenant_id) {
            $t = \App\Models\Tenant::find(auth()->user()->tenant_id);
            if ($t?->base_currency) {
                return strtoupper((string) $t->base_currency);
            }
        }

        return 'MYR';
    }

    /**
     * Global default for the invoice PDF customer-notes box (Company settings).
     */
    protected function defaultInvoiceCustomerNotes(): string
    {
        if (function_exists('tenant') && tenant()) {
            return (string) (tenant()->default_invoice_customer_notes ?? '');
        }
        if (auth()->check() && auth()->user()->tenant_id) {
            $t = \App\Models\Tenant::find(auth()->user()->tenant_id);

            return (string) ($t?->default_invoice_customer_notes ?? '');
        }

        return '';
    }
    /**
     * Official LHDN Classification Codes for Malaysia
     */
    private function getLhdnCodes()
    {
        return [
            ['id' => '011', 'name' => 'General Merchandise'],
            ['id' => '022', 'name' => 'Professional Services'],
            ['id' => '001', 'name' => 'Standard Rate Item'],
            ['id' => '010', 'name' => 'Exempt Item'],
        ];
    }

    /**
     * Display a listing of invoices with pagination and filters.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        if (! in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 10;
        }
        $search = $request->input('search', '');
        $statusFilter = $request->input('status', '');

        // NOTE: We use the raw query builder here for performance (large lists,
        // joined customer columns, KPI clones). That bypasses Eloquent's
        // SoftDeletingScope so we MUST filter `deleted_at IS NULL` ourselves on
        // every soft-deletable table in the query — both `invoices` and the
        // joined `customers`. Without this, soft-deleted invoices reappear in
        // the list while their /edit URL 404s.
        $baseQuery = DB::table('invoices')
            ->join('customers', 'invoices.customer_id', '=', 'customers.id')
            ->whereNull('invoices.deleted_at')
            ->whereNull('customers.deleted_at')
            ->select(
                'invoices.*',
                'customers.name as customer_name',
                'customers.email as customer_email'
            )
            ->orderBy('invoices.created_at', 'desc');

        if ($search !== '') {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('invoices.invoice_number', 'like', '%' . $search . '%')
                    ->orWhere('customers.name', 'like', '%' . $search . '%');
            });
        }
        if ($statusFilter) {
            $baseQuery->where('invoices.status', $statusFilter);
        }

        // KPIs from filtered set (same filters, no pagination).
        // Use select(DB::raw(...)): selectRaw() addSelect() would keep invoices.* from
        // $baseQuery and break ONLY_FULL_GROUP_BY. reorder() drops orderBy on the clone.
        $totalCount = (clone $baseQuery)->count();
        $totalOutstanding = $this->invoiceService->sumOutstanding(
            Invoice::query()
                ->whereIn('id', (clone $baseQuery)
                    ->reorder()
                    ->whereNotIn('invoices.status', ['draft', 'void'])
                    ->pluck('invoices.id'))
        );
        $totalCollected = (clone $baseQuery)
            ->reorder()
            ->select(DB::raw('COALESCE(SUM(invoices.amount_paid), 0) as total'))
            ->value('total') ?? 0;

        $paginator = $baseQuery->paginate($perPage)->withQueryString();
        $invoiceModels = Invoice::whereIn('id', collect($paginator->items())->pluck('id'))
            ->get()
            ->keyBy('id');
        $invoices = collect($paginator->items())->map(function ($row) use ($invoiceModels) {
            $model = $invoiceModels->get($row->id);
            $row->balance_due = $model ? $this->invoiceService->remainingBalance($model) : 0.0;

            return $row;
        })->all();

        $bankAccounts = Account::bankOrCash()
            ->active()
            ->orderBy('code')
            ->get(['code', 'name'])
            ->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->name} ({$a->code})"])
            ->values()
            ->all();

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'bankAccounts' => $bankAccounts,
            'totalOutstanding' => (float) $totalOutstanding,
            'totalCollected' => (float) $totalCollected,
            'totalCount' => $totalCount,
            'paginator' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $statusFilter,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * Show the form for creating a new invoice.
     * Supports ?customer_id= for preselection and provides next_invoice_number suggestion.
     */
    public function create(Request $request)
    {
        $nextNumber = $this->invoiceService->nextNumber();

        return Inertia::render('Invoices/Create', [
            'customers' => $this->customerOptions(),
            'lhdn_codes' => $this->getLhdnCodes(),
            'customer_id' => $request->query('customer_id'),
            'next_invoice_number' => $nextNumber,
            'base_currency' => $this->tenantBaseCurrency(),
            'products' => $this->productOptions(),
            'default_customer_notes' => $this->defaultInvoiceCustomerNotes(),
        ]);
    }

    /**
     * Active products served to the invoice editor for the line-item picker.
     * Only the columns the picker uses, not the whole row.
     */
    protected function productOptions(): \Illuminate\Support\Collection
    {
        return Product::query()
            ->active()
            ->select(['id', 'code', 'name', 'description', 'unit_price', 'account_code', 'tax_rate', 'classification_code'])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Customer dropdown projection. Only the columns the form actually
     * renders (id, name, tin) — avoids hauling the full row over the wire
     * for every invoice form load on tenants with many customers.
     */
    protected function customerOptions(): \Illuminate\Support\Collection
    {
        return Customer::query()
            ->select(['id', 'name', 'tin'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Store a newly created invoice in storage (Draft Mode).
     */
    public function store(StoreInvoiceRequest $request)
    {
        $invoice = $this->invoiceService->create(
            array_merge($request->except('items'), ['created_by' => auth()->id()]),
            $request->input('items')
        );

        return redirect()->route('invoices.show', $invoice->id)->with('success', 'Invoice draft created successfully.');
    }

    /**
     * Post the invoice to the General Ledger.
     */
    public function postInvoice($id)
    {
        $invoice = Invoice::with('customer')->findOrFail($id);
        try {
            $this->invoiceService->post($invoice);
            return redirect()->back()->with('success', 'Invoice posted to ledger.');
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Void an invoice and reverse ledger impact.
     */
    public function voidInvoice($id)
    {
        $invoice = Invoice::findOrFail($id);
        try {
            $this->invoiceService->void($invoice);
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
        return redirect()->back();
    }

    /**
     * Show edit form.
     */
    public function edit($id)
    {
        $invoice = Invoice::with('items')->findOrFail($id);
        $journalEntryId = DB::table('journal_entries')
            ->where('reference_type', 'Invoice')
            ->where('reference_id', $invoice->id)
            ->latest()
            ->value('id');

        return Inertia::render('Invoices/Edit', [
            'invoice'    => $invoice,
            'customers'  => $this->customerOptions(),
            'lhdn_codes' => $this->getLhdnCodes(),
            'journal_entry_id' => $journalEntryId,
            'base_currency' => $this->tenantBaseCurrency(),
            'products' => $this->productOptions(),
        ]);
    }

    /**
     * Update an existing invoice.
     */
    public function update(UpdateInvoiceRequest $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        if (! in_array($invoice->status, ['paid', 'void'], true)) {
            $this->invoiceService->update($invoice, $request->except('items'), $request->input('items'));
        }

        return redirect()->route('invoices.index')->with('success', 'Invoice updated successfully.');
    }

    /**
     * Delete a draft invoice (soft delete).
     */
    public function destroy($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->items()->delete();
        $invoice->delete();
        return redirect()->route('invoices.index')->with('success', 'Invoice deleted.');
    }

    /**
     * Download invoice as enterprise-standard PDF.
     */
    public function downloadPdf($id)
    {
        return $this->renderInvoicePdf($id, attachment: true);
    }

    /**
     * Read-only inline PDF preview. Same content as `downloadPdf` but with
     * `Content-Disposition: inline` so the browser renders it in the tab
     * instead of forcing a download. Lets users review an invoice without
     * editing or saving anything to disk.
     */
    public function previewPdf($id)
    {
        // Inertia <Link> fetches with X-Inertia and would dump the PDF bytes
        // into the SPA as text. Send those visits to the invoice screen.
        if (request()->header('X-Inertia')) {
            return redirect()->route('invoices.show', $id);
        }

        return $this->renderInvoicePdf($id, attachment: false);
    }

    /**
     * Shared invoice → PDF rendering. The only difference between download
     * and preview is the Content-Disposition header.
     */
    private function renderInvoicePdf(int|string $id, bool $attachment): \Symfony\Component\HttpFoundation\Response
    {
        $invoice = Invoice::with(['items', 'customer'])->findOrFail($id);

        $company = config('invoice.company');
        if (function_exists('tenant') && tenant()) {
            $company = tenant()->getCompanyDetails();
        } elseif (auth()->check() && auth()->user()->tenant_id) {
            $tenant = \App\Models\Tenant::find(auth()->user()->tenant_id);
            if ($tenant) {
                $company = $tenant->getCompanyDetails();
            }
        }

        return $this->respondWithPdf($invoice, $company, $attachment);
    }

    /**
     * Public download via signed URL (No auth required).
     */
    public function publicDownloadPdf($uuid)
    {
        $invoice = Invoice::with(['items', 'customer'])->where('uuid', $uuid)->firstOrFail();
        $this->invoiceService->markViewed($invoice);

        $company = config('invoice.company');
        if (function_exists('tenant') && tenant()) {
            $company = tenant()->getCompanyDetails();
        }

        return $this->respondWithPdf($invoice, $company, attachment: true);
    }

    private function respondWithPdf(Invoice $invoice, array $company, bool $attachment = true)
    {
        try {
            $invoice->loadMissing(['items', 'customer']);

            $pdf = Pdf::loadView('pdf.invoice', [
                'invoice' => $invoice,
                'customer' => $invoice->customer,
                'company' => $company,
            ])->setPaper('a4', 'portrait');

            $filename = "Invoice-{$invoice->invoice_number}.pdf";

            return $pdf->stream($filename, ['Attachment' => $attachment]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Could not generate PDF. Please contact support.',
            ], 500);
        }
    }

    /**
     * Email the invoice PDF directly to the customer.
     */
    public function emailPdf($id)
    {
        $bulk = app(DocumentBulkService::class);
        $result = $bulk->queueInvoiceEmails([(int) $id], $bulk->companyDetails());
        if ($result['queued'] === 0) {
            return redirect()->back()->with('error', 'Customer does not have a valid email, or delivery is set to Do not email.');
        }

        return redirect()->back()->with('success', 'Invoice email queued for delivery.');
    }

    public function bulkEmail(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1|max:'.DocumentBulkService::MAX_IDS, 'ids.*' => 'integer']);
        $bulk = app(DocumentBulkService::class);
        $result = $bulk->queueInvoiceEmails($request->input('ids'), $bulk->companyDetails());

        return redirect()->back()->with('success', "{$result['queued']} invoice email(s) queued.".($result['skipped'] ? " {$result['skipped']} skipped (no email)." : ''));
    }

    public function bulkPdf(Request $request)
    {
        $request->validate(['ids' => 'required|array|min:1|max:'.DocumentBulkService::MAX_IDS, 'ids.*' => 'integer']);
        $bulk = app(DocumentBulkService::class);
        try {
            $path = $bulk->zipInvoicePdfs($request->input('ids'), $bulk->companyDetails());
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return response()->download($path, 'invoices-'.now()->format('Ymd').'.zip')->deleteFileAfterSend(true);
    }

    /**
     * Record a payment receipt.
     */
    public function recordPayment(\App\Http\Requests\RecordPaymentRequest $request, $id)
    {
        $validated = $request->validated();

        $invoice = Invoice::findOrFail($id);
        try {
            $this->invoiceService->recordPayment(
                $invoice,
                (float) $validated['amount'],
                $validated['payment_date'],
                $validated['bank_account_code'],
                $validated['reference'] ?? null,
                auth()->id(),
                isset($validated['payment_exchange_rate']) ? (float) $validated['payment_exchange_rate'] : null,
            );
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Payment recorded.');
    }

    public function reversePayment($id, $paymentId)
    {
        $invoice = Invoice::findOrFail($id);
        $payment = InvoicePayment::query()
            ->where('invoice_id', $invoice->id)
            ->findOrFail($paymentId);
        try {
            $this->invoiceService->reversePayment($payment, auth()->id());
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Payment reversed.');
    }

    public function show($id)
    {
        $invoice = Invoice::with([
            'items',
            'customer',
            'payments',
            'attachments',
            'creditNoteApplications.creditNote:id,cn_number,total_amount,status',
        ])->findOrFail($id);

        $journalEntryId = DB::table('journal_entries')
            ->where('reference_type', 'Invoice')
            ->where('reference_id', $invoice->id)
            ->latest()
            ->value('id');

        $bankAccounts = Account::bankOrCash()
            ->active()
            ->orderBy('code')
            ->get(['code', 'name'])
            ->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->name} ({$a->code})"])
            ->values()
            ->all();

        $openCredits = \App\Models\CreditNote::query()
            ->where('customer_id', $invoice->customer_id)
            ->where('status', '!=', 'void')
            ->get()
            ->filter(fn ($cn) => $cn->openAmount() > 0)
            ->map(fn ($cn) => [
                'id'     => $cn->id,
                'number' => $cn->cn_number,
                'open'   => $cn->openAmount(),
            ])
            ->values();

        $openDeposits = \App\Models\ArDeposit::query()
            ->where('customer_id', $invoice->customer_id)
            ->where('status', 'open')
            ->orderByDesc('payment_date')
            ->get()
            ->filter(fn ($d) => $d->openAmount() > 0)
            ->map(fn ($d) => [
                'id'     => $d->id,
                'number' => $d->reference ?: ('DEP-'.$d->id),
                'date'   => optional($d->payment_date)->toDateString(),
                'open'   => $d->openAmount(),
            ])
            ->values();

        $balance = $this->invoiceService->remainingBalance($invoice);
        $myinvois = app(\App\Services\MyInvoisService::class);

        $tenantId = function_exists('tenant') && tenant() ? tenant('id') : null;
        $share = $invoice->uuid ? ShareLink::publicSigned(
            'public.invoices.show',
            ['uuid' => $invoice->uuid, 'tenant_id' => $tenantId],
            'Invoice '.$invoice->invoice_number
        ) : ['public_url' => null, 'whatsapp_url' => null];
        $pdfShare = $invoice->uuid ? ShareLink::publicSigned(
            'public.invoices.download',
            ['uuid' => $invoice->uuid, 'tenant_id' => $tenantId],
            'Invoice '.$invoice->invoice_number
        ) : ['public_url' => null, 'whatsapp_url' => null];

        return Inertia::render('Invoices/Show', [
            'invoice'             => $invoice,
            'balance'             => $balance,
            'journal_entry_id'    => $journalEntryId,
            'bankAccounts'        => $bankAccounts,
            'openCredits'         => $openCredits,
            'openDeposits'        => $openDeposits,
            'document_trail'      => app(SalesDocumentTrail::class)->forInvoice($invoice),
            'myinvois_gaps'       => $myinvois->readiness($invoice),
            'can_cancel_einvoice' => $invoice->lhdn_uuid && $invoice->lhdn_submitted_at && now()->diffInHours($invoice->lhdn_submitted_at) <= 72,
            'pay_now_configured'  => app(\App\Services\InvoicePayNowService::class)->isConfigured(),
            'public_pdf_url'      => $pdfShare['public_url'],
            'public_html_url'     => $share['public_url'],
            'whatsapp_url'        => $share['whatsapp_url'],
            'company'             => tenant()?->getCompanyDetails() ?? [],
            'base_currency'       => $this->tenantBaseCurrency(),
            'reminder_offsets'    => app(\App\Services\InvoiceReminderService::class)->offsetsFor($invoice),
            'late_fee_percent'    => (float) (tenant()?->late_fee_percent ?? 1.5),
            'can_issue_late_fee'  => $invoice->status !== 'draft'
                && $invoice->status !== 'void'
                && $invoice->status !== 'paid'
                && $invoice->due_date
                && $invoice->due_date->copy()->startOfDay()->lt(now()->startOfDay())
                && $balance > 0,
        ]);
    }

    public function updateReminders(Request $request, $id)
    {
        $request->validate([
            'offsets'   => 'nullable|array',
            'offsets.*' => 'integer',
        ]);
        $invoice = Invoice::findOrFail($id);
        $invoice->forceFill([
            'reminder_overrides' => [
                'offsets' => array_values(array_map('intval', $request->input('offsets', []))),
            ],
        ])->save();

        return redirect()->back()->with('success', 'Reminder schedule saved.');
    }

    public function duplicate($id)
    {
        $source = Invoice::with('items')->findOrFail($id);
        $copy = $this->invoiceService->duplicate($source, auth()->id());

        return redirect()->route('invoices.edit', $copy->id)
            ->with('success', "Draft {$copy->invoice_number} created from {$source->invoice_number}.");
    }

    public function cashSaleCreate(Request $request)
    {
        return Inertia::render('Invoices/Create', [
            'customers' => $this->customerOptions(),
            'lhdn_codes' => $this->getLhdnCodes(),
            'customer_id' => $request->query('customer_id'),
            'next_invoice_number' => $this->invoiceService->nextNumber(),
            'base_currency' => $this->tenantBaseCurrency(),
            'products' => $this->productOptions(),
            'cash_sale' => true,
            'bankAccounts' => Account::bankOrCash()->active()->orderBy('code')->get(['code', 'name']),
            'default_customer_notes' => $this->defaultInvoiceCustomerNotes(),
        ]);
    }

    public function cashSaleStore(StoreInvoiceRequest $request)
    {
        $request->validate([
            'bank_account_code' => 'required|string',
            'payment_date'      => 'required|date',
        ]);
        $invoice = $this->invoiceService->cashSale(
            array_merge($request->except('items'), ['created_by' => auth()->id()]),
            $request->input('items'),
            $request->input('bank_account_code'),
            $request->input('payment_date')
        );

        return redirect()->route('invoices.show', $invoice->id)->with('success', 'Cash sale recorded.');
    }

    public function submitMyInvois($id)
    {
        $invoice = Invoice::with(['items', 'customer'])->findOrFail($id);
        try {
            app(\App\Services\MyInvoisService::class)->submit($invoice);
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Submitted to MyInvois.');
    }

    public function refreshMyInvois($id)
    {
        try {
            app(\App\Services\MyInvoisService::class)->refreshStatus(Invoice::findOrFail($id));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'MyInvois status refreshed.');
    }

    public function issueLateFee($id)
    {
        $invoice = Invoice::findOrFail($id);
        try {
            $fee = $this->invoiceService->issueLateFee($invoice, null, auth()->id());
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.edit', $fee->id)
            ->with('success', "Draft {$fee->invoice_number} created as late interest on {$invoice->invoice_number}.");
    }

    public function cancelMyInvois(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);
        try {
            app(\App\Services\MyInvoisService::class)->cancel(Invoice::findOrFail($id), $request->input('reason'));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'MyInvois document cancelled.');
    }

    public function attach(Request $request, $id)
    {
        $request->validate(['file' => 'required|file|max:10240']);
        $invoice = Invoice::findOrFail($id);
        $file = $request->file('file');
        $path = $file->store('invoice-attachments/'.$invoice->id, 'local');
        $invoice->attachments()->create([
            'original_name' => $file->getClientOriginalName(),
            'path'          => $path,
            'mime'          => $file->getClientMimeType(),
            'size_bytes'    => $file->getSize(),
            'uploaded_by'   => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Attachment added.');
    }

    public function detach($id, $attachmentId)
    {
        $invoice = Invoice::findOrFail($id);
        $attachment = $invoice->attachments()->where('id', $attachmentId)->firstOrFail();
        \Illuminate\Support\Facades\Storage::disk('local')->delete($attachment->path);
        $attachment->delete();

        return redirect()->back()->with('success', 'Attachment removed.');
    }

    public function createRecurring(Request $request, $id)
    {
        $request->validate([
            'cadence'    => 'required|in:weekly,monthly,quarterly,yearly',
            'interval'   => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'auto_email' => 'nullable|boolean',
            'auto_post'  => 'nullable|boolean',
        ]);
        $invoice = Invoice::with('items')->findOrFail($id);
        $template = app(\App\Services\RecurringInvoiceService::class)->createFromInvoice($invoice, $request->only([
            'name', 'cadence', 'interval', 'start_date', 'end_date', 'auto_email', 'auto_post',
        ]), auth()->id());

        return redirect()->route('recurring-invoices.edit', $template->id)
            ->with('success', 'Recurring invoice created from '.$invoice->invoice_number.'.');
    }

    public function paymentReceipt($id, $paymentId)
    {
        $invoice = Invoice::with('customer')->findOrFail($id);
        $payment = $invoice->payments()->findOrFail($paymentId);

        if (! $payment->receipt_number) {
            $payment->receipt_number = DocumentNumber::next('invoice_payments', 'receipt_number', 'OR');
            $payment->save();
        }

        $company = tenant()?->getCompanyDetails() ?? config('invoice.company');
        $pdf = Pdf::loadView('pdf.payment-receipt', [
            'invoice'  => $invoice,
            'payment'  => $payment->fresh(),
            'customer' => $invoice->customer,
            'company'  => $company,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("Official-Receipt-{$payment->receipt_number}.pdf", ['Attachment' => false]);
    }

    public function publicPixel($uuid)
    {
        $invoice = Invoice::where('uuid', $uuid)->firstOrFail();
        $this->invoiceService->markViewed($invoice);
        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($gif, 200, [
            'Content-Type'  => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function payNow($id)
    {
        $invoice = Invoice::with('customer')->findOrFail($id);
        $url = app(\App\Services\InvoicePayNowService::class)->paymentUrl($invoice);
        if (! $url) {
            return redirect()->back()->with('error', 'Pay Now is not configured or this invoice has no balance.');
        }

        return redirect()->away($url);
    }

    public function publicPayReturn($uuid)
    {
        $invoice = Invoice::where('uuid', $uuid)->first();

        return response()->view('public.invoice-paid', [
            'invoice' => $invoice,
        ]);
    }

    public function batchCreate()
    {
        return Inertia::render('Invoices/Batch', [
            'customers'              => $this->customerOptions(),
            'lhdn_codes'             => $this->getLhdnCodes(),
            'default_customer_notes' => $this->defaultInvoiceCustomerNotes(),
        ]);
    }

    public function batchStore(Request $request)
    {
        $request->validate([
            'rows'                               => 'required|array|min:1|max:200',
            'rows.*.customer_id'                 => 'required|exists:customers,id',
            'rows.*.issue_date'                  => 'nullable|date',
            'rows.*.due_date'                    => 'nullable|date',
            'rows.*.customer_notes'              => 'nullable|string',
            'rows.*.items'                       => 'required|array|min:1',
            'rows.*.items.*.description'         => 'required|string|max:500',
            'rows.*.items.*.quantity'            => 'required|numeric|min:0.01',
            'rows.*.items.*.unit_price'          => 'required|numeric',
            'rows.*.items.*.tax_rate'            => 'nullable|numeric',
            'rows.*.items.*.item_classification' => 'nullable|string|max:20',
        ]);

        $created = 0;
        foreach ($request->input('rows') as $row) {
            $items = collect($row['items'] ?? [])->map(fn ($item) => [
                'description'         => $item['description'],
                'quantity'            => $item['quantity'],
                'unit_price'          => $item['unit_price'],
                'tax_rate'            => $item['tax_rate'] ?? 0,
                'item_classification' => $item['item_classification'] ?? '022',
                'discount_amount'     => 0,
            ])->all();

            $this->invoiceService->create([
                'invoice_number' => $this->invoiceService->nextNumber(),
                'msic_code'      => $row['msic_code'] ?? '00000',
                'customer_id'    => $row['customer_id'],
                'issue_date'     => $row['issue_date'] ?? now()->toDateString(),
                'due_date'       => $row['due_date'] ?? now()->addDays(30)->toDateString(),
                'currency'       => $row['currency'] ?? 'MYR',
                'customer_notes' => $row['customer_notes'] ?? ($this->defaultInvoiceCustomerNotes() ?: null),
                'created_by'     => auth()->id(),
            ], $items);
            $created++;
        }

        return redirect()->route('invoices.index')->with('success', "{$created} draft invoice(s) created.");
    }

    public function toyyibpayCallback(Request $request, ToyyibpayService $toyyibpay)
    {
        $ref = (string) $request->input('order_id', $request->input('billExternalReferenceNo', ''));
        $status = $request->input('status_id', $request->input('status'));
        if ((string) $status !== '1') {
            return response('unpaid', 200);
        }

        $billCode = (string) $request->input('billcode', '');
        if (! $toyyibpay->verifyPaidBill($billCode, $ref)) {
            Log::warning('Toyyibpay invoice callback rejected: verification failed', [
                'reference' => $ref,
                'billcode'  => $billCode,
            ]);

            return response('verification failed', 403);
        }

        return $this->settlePayNow($ref, 'ToyyibPay '.$billCode);
    }

    public function billplzCallback(Request $request)
    {
        $payload = $request->all();
        $ref = (string) ($payload['reference_1'] ?? '');
        $tenant = $this->tenantFromPayRef($ref);
        if (! $tenant) {
            return response('unknown tenant', 200);
        }
        tenancy()->initialize($tenant);
        $service = \App\Services\BillplzService::forTenant($tenant);
        if (! $service || ! $service->callbackIsPaid($payload)) {
            return response('unpaid', 200);
        }

        return $this->settlePayNow($ref, 'Billplz '.($payload['id'] ?? ''));
    }

    public function commercepayCallback(Request $request)
    {
        $payload = $request->all();
        $ref = (string) ($payload['referenceCode'] ?? $payload['reference_code'] ?? '');
        $tenant = $this->tenantFromPayRef($ref);
        if (! $tenant) {
            return response('unknown tenant', 200);
        }
        tenancy()->initialize($tenant);
        $service = \App\Services\CommercePayService::forTenant($tenant);
        if (! $service || ! $service->callbackIsPaid($payload)) {
            return response('unpaid', 200);
        }

        return $this->settlePayNow($ref, 'CommercePay '.($payload['transactionNumber'] ?? ''));
    }

    private function tenantFromPayRef(string $ref): ?\App\Models\Tenant
    {
        if (! preg_match('/^inv-(\d+)-(.+)$/', $ref, $m)) {
            return null;
        }

        return \App\Models\Tenant::find($m[2]);
    }

    private function settlePayNow(string $ref, string $paymentRef)
    {
        if (! preg_match('/^inv-(\d+)-(.+)$/', $ref, $m)) {
            return response('ignored', 200);
        }
        $tenant = \App\Models\Tenant::find($m[2]);
        if (! $tenant) {
            return response('unknown tenant', 200);
        }
        if (! tenancy()->initialized || tenant('id') !== $tenant->id) {
            tenancy()->initialize($tenant);
        }
        $invoice = Invoice::find($m[1]);
        if (! $invoice || in_array($invoice->status, ['paid', 'void', 'draft'], true)) {
            return response('ok', 200);
        }
        $bank = Account::bankOrCash()->active()->orderBy('code')->value('code') ?? '1200';
        $balance = $this->invoiceService->remainingBalance($invoice);
        if ($balance > 0) {
            $this->invoiceService->recordPayment($invoice, $balance, now()->toDateString(), $bank, $paymentRef);
        }

        return response('ok', 200);
    }
}