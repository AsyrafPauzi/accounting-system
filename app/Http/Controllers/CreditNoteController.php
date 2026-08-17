<?php

namespace App\Http\Controllers;

use App\Jobs\SendCreditNoteEmail;
use App\Models\Account;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\CreditNoteService;
use App\Services\MyInvoisService;
use App\Services\SalesDocumentTrail;
use App\Support\ShareLink;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CreditNoteController extends Controller
{
    public function __construct(
        protected CreditNoteService $creditNotes,
        protected MyInvoisService $myinvois,
    ) {}

    public function index()
    {
        $creditNotes = DB::table('credit_notes')
            ->join('customers', 'credit_notes.customer_id', '=', 'customers.id')
            ->select('credit_notes.*', 'customers.name as customer_name', 'customers.email as customer_email')
            ->whereNull('credit_notes.deleted_at')
            ->orderBy('credit_notes.created_at', 'desc')
            ->get();

        return Inertia::render('CreditNotes/Index', [
            'creditNotes' => $creditNotes,
        ]);
    }

    public function show($id)
    {
        $cn = CreditNote::with(['items', 'customer', 'invoice:id,invoice_number,status', 'applications.invoice:id,invoice_number', 'refunds'])
            ->findOrFail($id);

        $openInvoices = Invoice::query()
            ->where('customer_id', $cn->customer_id)
            ->whereNotIn('status', ['draft', 'void', 'paid'])
            ->get(['id', 'invoice_number', 'total_amount', 'amount_paid', 'status']);

        $tenantId = function_exists('tenant') && tenant() ? tenant('id') : null;
        $share = $cn->uuid ? ShareLink::publicSigned(
            'public.credit-notes.download',
            ['uuid' => $cn->uuid, 'tenant_id' => $tenantId],
            'Credit note '.$cn->cn_number
        ) : ['public_url' => null, 'whatsapp_url' => null];

        return Inertia::render('CreditNotes/Show', [
            'creditNote'   => array_merge($cn->toArray(), ['open_amount' => $cn->openAmount()]),
            'openInvoices' => $openInvoices,
            'bankAccounts' => Account::bankOrCash()->active()->orderBy('code')->get(['code', 'name']),
            'document_trail' => app(SalesDocumentTrail::class)->forCreditNote($cn),
            'public_pdf_url' => $share['public_url'],
            'whatsapp_url' => $share['whatsapp_url'],
            'myinvois_gaps'=> $this->myinvois->readiness($cn),
            'can_cancel_einvoice' => $cn->lhdn_uuid && $cn->lhdn_submitted_at && now()->diffInHours($cn->lhdn_submitted_at) <= 72,
            'company' => tenant()?->getCompanyDetails() ?? [],
        ]);
    }

    public function createStandalone()
    {
        return Inertia::render('CreditNotes/CreateStandalone', [
            'customers'    => Customer::query()->orderBy('name')->get(['id', 'name', 'tin']),
            'next_number'  => $this->creditNotes->nextNumber(),
            'lhdn_reasons' => $this->reasons(),
        ]);
    }

    public function create($invoice_id)
    {
        $invoice = Invoice::with(['items', 'customer'])->findOrFail($invoice_id);

        return Inertia::render('CreditNotes/Create', [
            'invoice'      => $invoice,
            'next_number'  => $this->creditNotes->nextNumber(),
            'lhdn_reasons' => $this->reasons(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'invoice_id'          => 'nullable|exists:invoices,id',
            'customer_id'         => 'required|exists:customers,id',
            'cn_number'           => 'required|unique:credit_notes,cn_number',
            'reason_code'         => 'required|string',
            'reason_description'  => 'nullable|string',
            'items'               => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric',
            'items.*.tax_rate'    => 'nullable|numeric',
            'currency'            => 'nullable|string|size:3',
        ]);

        $cn = $this->creditNotes->issue($request->only([
            'invoice_id', 'customer_id', 'cn_number', 'reason_code', 'reason_description', 'currency', 'exchange_rate', 'customer_notes', 'issue_date',
        ]), $request->input('items'));

        return redirect()->route('credit-notes.show', $cn->id)->with('success', 'Credit note issued.');
    }

    public function edit($id)
    {
        $cn = CreditNote::with(['items', 'customer'])->findOrFail($id);
        $editable = true;
        $lockReason = null;
        try {
            $this->creditNotes->assertEditable($cn);
        } catch (\LogicException $e) {
            $editable = false;
            $lockReason = $e->getMessage();
        }

        return Inertia::render('CreditNotes/Edit', [
            'creditNote'   => $cn,
            'editable'     => $editable,
            'lock_reason'  => $lockReason,
            'lines_locked' => (float) $cn->applied_amount > 0 || (float) ($cn->refunded_amount ?? 0) > 0,
            'lhdn_reasons' => $this->reasons(),
            'customers'    => Customer::query()->orderBy('name')->get(['id', 'name']),
            'products'     => Product::query()->active()->orderBy('name')->get(['id', 'name', 'code', 'unit_price', 'tax_rate']),
        ]);
    }

    public function update(Request $request, $id)
    {
        $cn = CreditNote::findOrFail($id);
        $request->validate([
            'issue_date'             => 'nullable|date',
            'reason_code'            => 'nullable|string',
            'reason_description'     => 'nullable|string',
            'customer_notes'         => 'nullable|string',
            'items'                  => 'nullable|array|min:1',
            'items.*.description'    => 'required_with:items|string',
            'items.*.quantity'       => 'required_with:items|numeric|min:0.01',
            'items.*.unit_price'     => 'required_with:items|numeric',
        ]);

        try {
            $this->creditNotes->update(
                $cn,
                $request->only(['issue_date', 'reason_code', 'reason_description', 'customer_notes']),
                $request->has('items') ? $request->input('items') : null
            );
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('credit-notes.show', $cn->id)->with('success', 'Credit note updated.');
    }

    public function apply(Request $request, $id)
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount'     => 'required|numeric|min:0.01',
        ]);
        $cn = CreditNote::findOrFail($id);
        try {
            $this->creditNotes->applyToInvoice($cn, Invoice::findOrFail($request->invoice_id), (float) $request->amount);
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Credit applied to invoice.');
    }

    public function refund(Request $request, $id)
    {
        $request->validate([
            'amount'            => 'required|numeric|min:0.01',
            'payment_date'      => 'required|date',
            'bank_account_code' => 'required|string|exists:accounts,code',
            'reference'         => 'nullable|string|max:120',
        ]);
        try {
            $this->creditNotes->refund(
                CreditNote::findOrFail($id),
                (float) $request->input('amount'),
                $request->input('bank_account_code'),
                $request->input('payment_date'),
                $request->input('reference'),
                auth()->id()
            );
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Credit refunded to customer.');
    }

    public function void($id)
    {
        $cn = CreditNote::findOrFail($id);
        try {
            $this->creditNotes->void($cn);
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Credit note voided.');
    }

    public function downloadPdf($id)
    {
        return $this->pdfResponse(CreditNote::with(['items', 'customer'])->findOrFail($id), true);
    }

    public function publicDownloadPdf($uuid)
    {
        return $this->pdfResponse(CreditNote::with(['items', 'customer'])->where('uuid', $uuid)->firstOrFail(), true);
    }

    public function emailPdf($id)
    {
        $cn = CreditNote::with(['customer.contacts'])->findOrFail($id);
        $customer = $cn->customer;
        if (! $customer) {
            return redirect()->back()->with('error', 'Customer not found.');
        }
        $recipients = [];
        if ($customer->email && filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $customer->email;
        }
        if ($recipients === []) {
            return redirect()->back()->with('error', 'Customer has no email.');
        }
        $company = tenant()?->getCompanyDetails() ?? config('invoice.company');
        SendCreditNoteEmail::dispatch($cn->id, $recipients, $company);

        return redirect()->back()->with('success', 'Credit note email queued.');
    }

    public function submitMyInvois($id)
    {
        $cn = CreditNote::with(['items', 'customer'])->findOrFail($id);
        try {
            $this->myinvois->submit($cn);
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Credit note submitted to MyInvois.');
    }

    public function refreshMyInvois($id)
    {
        try {
            $this->myinvois->refreshStatus(CreditNote::findOrFail($id));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'MyInvois status refreshed.');
    }

    public function cancelMyInvois(Request $request, $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);
        $cn = CreditNote::findOrFail($id);
        try {
            $this->myinvois->cancel($cn, $request->input('reason'));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'MyInvois document cancelled.');
    }

    private function pdfResponse(CreditNote $cn, bool $attachment)
    {
        $company = tenant()?->getCompanyDetails() ?? config('invoice.company');
        $pdf = Pdf::loadView('pdf.sales-document', [
            'title'      => 'Credit Note',
            'number'     => $cn->cn_number,
            'issue_date' => optional($cn->issue_date)->toDateString() ?? $cn->issue_date,
            'customer'   => $cn->customer,
            'company'    => $company,
            'items'      => $cn->items,
            'tax'        => $cn->tax_amount,
            'total'      => $cn->total_amount,
            'currency'   => $cn->currency ?? 'MYR',
            'notes'      => $cn->customer_notes,
            'qr_url'     => $cn->lhdn_qr_url,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("Credit-Note-{$cn->cn_number}.pdf", ['Attachment' => $attachment]);
    }

    private function reasons(): array
    {
        return [
            ['id' => '01', 'name' => '01 - Return of Goods'],
            ['id' => '02', 'name' => '02 - Pricing Error'],
            ['id' => '03', 'name' => '03 - Discount/Rebate'],
            ['id' => '04', 'name' => '04 - Cancellation of Service'],
        ];
    }
}
