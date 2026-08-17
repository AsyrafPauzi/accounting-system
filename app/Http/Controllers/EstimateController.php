<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEstimateRequest;
use App\Http\Requests\UpdateEstimateRequest;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\DocumentBulkService;
use App\Services\EstimateService;
use App\Services\SalesDocumentTrail;
use App\Support\ShareLink;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EstimateController extends Controller
{
    public function __construct(private EstimateService $estimates) {}

    public function index(Request $request): Response
    {
        $this->authorize('estimates.view');

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status', 'all');

        $estimates = Estimate::query()
            ->select(['id', 'estimate_number', 'customer_id', 'issue_date', 'expiry_date', 'status', 'currency', 'total_amount', 'converted_invoice_id', 'last_emailed_status', 'last_emailed_at'])
            // Pull customer email so the front-end can show the
            // "Email" button only for customers who actually have an
            // address. Avoids exposing a button that always errors out.
            ->with(['customer:id,name,email'])
            ->when($search !== '', fn ($q) => $q->where(function ($qq) use ($search) {
                $qq->where('estimate_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            }))
            ->when(in_array($status, Estimate::STATUSES, true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Flatten customer.email → top-level customer_email for a
        // simpler React shape; the rest of the customer relation is
        // still available if the frontend wants it.
        $estimates->getCollection()->transform(function ($estimate) {
            $estimate->customer_email = $estimate->customer?->email;
            return $estimate;
        });

        $counts = Estimate::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return Inertia::render('Estimates/Index', [
            'estimates' => $estimates,
            'filters'   => [
                'search' => $search,
                'status' => $status,
            ],
            'counts'    => $counts,
            'base_currency' => $this->tenantBaseCurrency(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('estimates.create');

        return Inertia::render('Estimates/Create', [
            'customers'           => $this->customerOptions(),
            'customer_id'         => $request->query('customer_id'),
            'products'            => $this->productOptions(),
            'next_estimate_number'=> $this->estimates->nextNumber(),
            'base_currency'       => $this->tenantBaseCurrency(),
        ]);
    }

    public function store(StoreEstimateRequest $request): RedirectResponse
    {
        $estimate = $this->estimates->create(
            array_merge($request->validated(), ['created_by' => $request->user()->id]),
            $request->validated()['items']
        );

        return redirect()
            ->route('estimates.show', $estimate->id)
            ->with('success', "Estimate {$estimate->estimate_number} saved as draft.");
    }

    public function batchCreate(): Response
    {
        $this->authorize('estimates.create');

        return Inertia::render('Estimates/Batch', [
            'customers' => $this->customerOptions(),
        ]);
    }

    public function batchStore(Request $request): RedirectResponse
    {
        $this->authorize('estimates.create');
        $request->validate([
            'rows'                       => 'required|array|min:1|max:200',
            'rows.*.customer_id'         => 'required|exists:customers,id',
            'rows.*.description'         => 'required|string',
            'rows.*.quantity'            => 'required|numeric|min:0.01',
            'rows.*.unit_price'          => 'required|numeric',
            'rows.*.tax_rate'            => 'nullable|numeric',
            'rows.*.issue_date'          => 'nullable|date',
        ]);

        $created = 0;
        foreach ($request->input('rows') as $row) {
            $this->estimates->create([
                'estimate_number' => $this->estimates->nextNumber(),
                'customer_id'     => $row['customer_id'],
                'issue_date'      => $row['issue_date'] ?? now()->toDateString(),
                'expiry_date'     => $row['expiry_date'] ?? now()->addDays(14)->toDateString(),
                'currency'        => $row['currency'] ?? 'MYR',
                'created_by'      => $request->user()->id,
            ], [[
                'description'         => $row['description'],
                'quantity'            => $row['quantity'],
                'unit_price'          => $row['unit_price'],
                'tax_rate'            => $row['tax_rate'] ?? 0,
                'item_classification' => $row['item_classification'] ?? '022',
                'discount_amount'     => 0,
            ]]);
            $created++;
        }

        return redirect()->route('estimates.index')->with('success', "{$created} draft estimate(s) created.");
    }

    public function bulkEmail(Request $request): RedirectResponse
    {
        $this->authorize('estimates.email');
        $request->validate(['ids' => 'required|array|min:1|max:'.DocumentBulkService::MAX_IDS, 'ids.*' => 'integer']);
        $bulk = app(DocumentBulkService::class);
        $result = $bulk->queueEstimateEmails($request->input('ids'), $bulk->companyDetails());

        return redirect()->back()->with('success', "{$result['queued']} estimate email(s) queued.".($result['skipped'] ? " {$result['skipped']} skipped (no email)." : ''));
    }

    public function bulkPdf(Request $request)
    {
        $this->authorize('estimates.view');
        $request->validate(['ids' => 'required|array|min:1|max:'.DocumentBulkService::MAX_IDS, 'ids.*' => 'integer']);
        $bulk = app(DocumentBulkService::class);
        try {
            $path = $bulk->zipEstimatePdfs($request->input('ids'), $bulk->companyDetails());
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return response()->download($path, 'estimates-'.now()->format('Ymd').'.zip')->deleteFileAfterSend(true);
    }

    public function show(int $id): Response
    {
        $this->authorize('estimates.view');

        $estimate = Estimate::with(['items', 'customer', 'convertedInvoice:id,invoice_number,status'])
            ->findOrFail($id);

        $tenantId = function_exists('tenant') && tenant() ? tenant('id') : null;
        $share = $estimate->uuid ? ShareLink::publicSigned(
            'public.estimates.download',
            ['uuid' => $estimate->uuid, 'tenant_id' => $tenantId],
            'Estimate '.$estimate->estimate_number
        ) : ['public_url' => null, 'whatsapp_url' => null];

        return Inertia::render('Estimates/Show', [
            'estimate'      => $estimate,
            'base_currency' => $this->tenantBaseCurrency(),
            'document_trail'=> app(SalesDocumentTrail::class)->forEstimate($estimate),
            'public_pdf_url'=> $share['public_url'],
            'whatsapp_url'  => $share['whatsapp_url'],
        ]);
    }

    public function edit(int $id): Response
    {
        $this->authorize('estimates.edit');

        $estimate = Estimate::with('items')->findOrFail($id);

        if ($estimate->isConverted()) {
            return redirect()->route('estimates.show', $estimate->id)
                ->with('error', 'This estimate has been converted to an invoice and is locked.');
        }

        return Inertia::render('Estimates/Edit', [
            'estimate'     => $estimate,
            'customers'    => $this->customerOptions(),
            'products'     => $this->productOptions(),
            'base_currency'=> $this->tenantBaseCurrency(),
        ]);
    }

    public function update(UpdateEstimateRequest $request, int $id): RedirectResponse
    {
        $estimate = Estimate::findOrFail($id);

        try {
            $this->estimates->update($estimate, $request->validated(), $request->validated()['items']);
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('estimates.show', $estimate->id)
            ->with('success', "Estimate {$estimate->estimate_number} updated.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->authorize('estimates.delete');

        $estimate = Estimate::findOrFail($id);

        if ($estimate->isConverted()) {
            return redirect()->back()->with('error', 'Converted estimates cannot be deleted — delete the resulting invoice instead.');
        }

        $estimate->delete();

        return redirect()->route('estimates.index')->with('success', 'Estimate removed.');
    }

    /**
     * Manually move an estimate's status (sent / accepted / rejected / re-open).
     * The Convert action is a separate endpoint because it produces an invoice.
     */
    public function transition(Request $request, int $id): RedirectResponse
    {
        $this->authorize('estimates.edit');

        $request->validate([
            'status' => ['required', 'string', \Illuminate\Validation\Rule::in(Estimate::STATUSES)],
        ]);

        $estimate = Estimate::findOrFail($id);

        try {
            $this->estimates->transition($estimate, $request->input('status'));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', "Estimate marked as {$estimate->status}.");
    }

    /**
     * Convert an estimate into a draft invoice.
     * Sets estimate.status = 'converted' and stores converted_invoice_id.
     */
    public function convert(Request $request, int $id): RedirectResponse
    {
        $this->authorize('estimates.convert');

        $estimate = Estimate::with('items')->findOrFail($id);

        try {
            $invoice = $this->estimates->convertToInvoice($estimate, [
                'created_by' => $request->user()->id,
                'msic_code'  => $request->input('msic_code', '00000'),
                'due_date'   => $request->input('due_date'),
            ]);
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.show', $invoice->id)
            ->with('success', "Estimate {$estimate->estimate_number} converted to invoice {$invoice->invoice_number}. Review and post when ready.");
    }

    public function duplicate($id)
    {
        $this->authorize('estimates.create');
        $copy = $this->estimates->duplicate(Estimate::with('items')->findOrFail($id), auth()->id());

        return redirect()->route('estimates.edit', $copy->id)
            ->with('success', "Draft {$copy->estimate_number} created.");
    }

    public function convertToSalesOrder($id)
    {
        $this->authorize('estimates.convert');
        try {
            $so = app(\App\Services\SalesOrderService::class)->fromEstimate(
                Estimate::with('items')->findOrFail($id),
                auth()->id()
            );
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('sales-orders.show', $so->id)
            ->with('success', 'Sales order created from estimate.');
    }

    /**
     * Authenticated PDF download for the in-app "Download" button on
     * the estimates index/edit pages.
     */
    public function downloadPdf($id)
    {
        $this->authorize('estimates.view');

        $estimate = Estimate::with(['items', 'customer'])->findOrFail($id);
        $company = $this->resolveCompany();

        return $this->respondWithPdf($estimate, $company, attachment: true);
    }

    /**
     * Public PDF download via signed URL — the link inside the
     * customer's email. No auth required because the signed URL is
     * the auth (TTL'd, signature-verified, scoped to a specific UUID).
     */
    public function publicDownloadPdf($uuid)
    {
        $estimate = Estimate::with(['items', 'customer'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        $company = $this->resolveCompany();

        return $this->respondWithPdf($estimate, $company, attachment: true);
    }

    /**
     * Email the estimate PDF to the customer. Mirrors the invoice
     * email flow so the queueing / tracking shape is the same.
     */
    public function email($id): RedirectResponse
    {
        $this->authorize('estimates.email');

        $bulk = app(DocumentBulkService::class);
        $result = $bulk->queueEstimateEmails([(int) $id], $this->resolveCompany());
        if ($result['queued'] === 0) {
            return redirect()->back()->with(
                'error',
                'Customer has no valid email, or delivery is set to Do not email.'
            );
        }

        return redirect()->back()->with('success', 'Estimate email queued for delivery.');
    }

    /**
     * Tenant company details — same shape the invoice flow uses so
     * the PDF / email templates can read the same keys.
     */
    private function resolveCompany(): array
    {
        $company = config('invoice.company') ?? [];
        if (function_exists('tenant') && tenant()) {
            $company = tenant()->getCompanyDetails();
        } elseif (auth()->check() && auth()->user()->tenant_id) {
            $tenant = Tenant::find(auth()->user()->tenant_id);
            if ($tenant) {
                $company = $tenant->getCompanyDetails();
            }
        }
        return $company;
    }

    private function respondWithPdf(Estimate $estimate, array $company, bool $attachment = true)
    {
        try {
            $estimate->loadMissing(['items', 'customer']);

            $pdf = Pdf::loadView('pdf.estimate', [
                'estimate' => $estimate,
                'customer' => $estimate->customer,
                'company'  => $company,
            ])->setPaper('a4', 'portrait');

            $filename = "Estimate-{$estimate->estimate_number}.pdf";

            return $pdf->stream($filename, ['Attachment' => $attachment]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Could not generate PDF. Please contact support.',
            ], 500);
        }
    }

    private function customerOptions(): \Illuminate\Support\Collection
    {
        return Customer::query()
            ->select(['id', 'name', 'tin'])
            ->orderBy('name')
            ->get();
    }

    private function productOptions(): \Illuminate\Support\Collection
    {
        return Product::query()
            ->active()
            ->select(['id', 'code', 'name', 'description', 'unit_price', 'account_code', 'tax_rate'])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();
    }

    private function tenantBaseCurrency(): string
    {
        if (function_exists('tenant') && tenant()) {
            return strtoupper((string) (tenant()->base_currency ?? 'MYR'));
        }
        if (auth()->user()?->tenant_id) {
            $t = Tenant::find(auth()->user()->tenant_id);
            if ($t?->base_currency) {
                return strtoupper((string) $t->base_currency);
            }
        }

        return 'MYR';
    }
}
