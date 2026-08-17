<?php

namespace App\Http\Controllers;

use App\Jobs\SendDebitNoteEmail;
use App\Models\Customer;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\DebitNoteService;
use App\Services\MyInvoisService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DebitNoteController extends Controller
{
    public function __construct(
        protected DebitNoteService $debitNotes,
        protected MyInvoisService $myinvois,
    ) {}

    public function index()
    {
        $notes = DebitNote::query()
            ->with(['customer:id,name', 'invoice:id,invoice_number'])
            ->orderByDesc('id')
            ->get()
            ->map(fn ($dn) => [
                ...$dn->toArray(),
                'customer_name' => $dn->customer?->name,
            ]);

        return Inertia::render('DebitNotes/Index', ['debitNotes' => $notes]);
    }

    public function show($id)
    {
        $dn = DebitNote::with(['items', 'customer', 'invoice:id,invoice_number'])->findOrFail($id);

        return Inertia::render('DebitNotes/Show', [
            'debitNote'    => $dn,
            'myinvois_gaps'=> $this->myinvois->readiness($dn),
        ]);
    }

    public function create(Request $request)
    {
        $invoice = $request->query('invoice_id')
            ? Invoice::with(['items', 'customer'])->find($request->query('invoice_id'))
            : null;

        return Inertia::render('DebitNotes/Create', [
            'invoice'     => $invoice,
            'customers'   => Customer::query()->orderBy('name')->get(['id', 'name']),
            'products'    => Product::query()->active()->orderBy('name')->get(['id', 'name', 'code', 'unit_price', 'tax_rate']),
            'next_number' => $this->debitNotes->nextNumber(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id'         => 'required|exists:customers,id',
            'invoice_id'          => 'nullable|exists:invoices,id',
            'dn_number'           => 'required|unique:debit_notes,dn_number',
            'items'               => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric',
        ]);
        $dn = $this->debitNotes->issue($request->only([
            'customer_id', 'invoice_id', 'dn_number', 'reason_code', 'reason_description', 'currency', 'issue_date', 'customer_notes',
        ]), $request->input('items'));

        return redirect()->route('debit-notes.show', $dn->id)->with('success', 'Debit note issued.');
    }

    public function edit($id)
    {
        $dn = DebitNote::with(['items', 'customer', 'invoice:id,invoice_number'])->findOrFail($id);
        $editable = true;
        $lockReason = null;
        try {
            $this->debitNotes->assertEditable($dn);
        } catch (\LogicException $e) {
            $editable = false;
            $lockReason = $e->getMessage();
        }

        return Inertia::render('DebitNotes/Edit', [
            'debitNote'   => $dn,
            'editable'    => $editable,
            'lock_reason' => $lockReason,
            'customers'   => Customer::query()->orderBy('name')->get(['id', 'name']),
            'products'    => Product::query()->active()->orderBy('name')->get(['id', 'name', 'code', 'unit_price', 'tax_rate']),
        ]);
    }

    public function update(Request $request, $id)
    {
        $dn = DebitNote::findOrFail($id);
        $request->validate([
            'issue_date'          => 'nullable|date',
            'reason_code'         => 'nullable|string',
            'reason_description'  => 'nullable|string',
            'customer_notes'      => 'nullable|string',
            'items'               => 'nullable|array|min:1',
            'items.*.description' => 'required_with:items|string',
            'items.*.quantity'    => 'required_with:items|numeric|min:0.01',
            'items.*.unit_price'  => 'required_with:items|numeric',
        ]);

        try {
            $this->debitNotes->update(
                $dn,
                $request->only(['issue_date', 'reason_code', 'reason_description', 'customer_notes']),
                $request->has('items') ? $request->input('items') : null
            );
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('debit-notes.show', $dn->id)->with('success', 'Debit note updated.');
    }

    public function emailPdf($id)
    {
        $dn = DebitNote::with(['customer'])->findOrFail($id);
        $customer = $dn->customer;
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
        SendDebitNoteEmail::dispatch($dn->id, $recipients, $company);

        return redirect()->back()->with('success', 'Debit note email queued.');
    }

    public function void($id)
    {
        try {
            $this->debitNotes->void(DebitNote::findOrFail($id));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Debit note voided.');
    }

    public function downloadPdf($id)
    {
        $dn = DebitNote::with(['items', 'customer'])->findOrFail($id);
        $pdf = Pdf::loadView('pdf.sales-document', [
            'title'      => 'Debit Note',
            'number'     => $dn->dn_number,
            'issue_date' => optional($dn->issue_date)->toDateString(),
            'customer'   => $dn->customer,
            'company'    => tenant()?->getCompanyDetails() ?? config('invoice.company'),
            'items'      => $dn->items,
            'tax'        => $dn->tax_amount,
            'total'      => $dn->total_amount,
            'currency'   => $dn->currency ?? 'MYR',
            'notes'      => $dn->customer_notes,
            'qr_url'     => $dn->lhdn_qr_url,
        ]);

        return $pdf->stream("Debit-Note-{$dn->dn_number}.pdf");
    }

    public function submitMyInvois($id)
    {
        try {
            $this->myinvois->submit(DebitNote::with(['items', 'customer'])->findOrFail($id));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Submitted to MyInvois.');
    }

    public function refreshMyInvois($id)
    {
        try {
            $this->myinvois->refreshStatus(DebitNote::findOrFail($id));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'MyInvois status refreshed.');
    }
}
