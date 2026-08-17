<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Supplier;
use App\Models\SupplierDebitNote;
use App\Services\SupplierDebitNoteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierDebitNoteController extends Controller
{
    public function __construct(protected SupplierDebitNoteService $notes) {}

    public function index()
    {
        $notes = SupplierDebitNote::query()->with('supplier:id,name')->orderByDesc('id')->get()
            ->map(fn ($n) => [...$n->toArray(), 'supplier_name' => $n->supplier?->name]);

        return Inertia::render('SupplierDebitNotes/Index', ['notes' => $notes]);
    }

    public function show($id)
    {
        return Inertia::render('SupplierDebitNotes/Show', [
            'debitNote' => SupplierDebitNote::with(['items', 'supplier', 'bill:id,bill_number'])->findOrFail($id),
        ]);
    }

    public function create(Request $request)
    {
        $bill = $request->query('bill_id') ? Bill::with(['items', 'supplier'])->find($request->query('bill_id')) : null;

        return Inertia::render('SupplierDebitNotes/Create', [
            'bill'            => $bill,
            'suppliers'       => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'expenseAccounts' => Account::where('type', 'expense')->active()->orderBy('code')->get(['code', 'name']),
            'next_number'     => $this->notes->nextNumber(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id'         => 'required|exists:suppliers,id',
            'bill_id'             => 'nullable|exists:bills,id',
            'items'               => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity'    => 'required|numeric|min:0.01',
            'items.*.unit_price'  => 'required|numeric',
        ]);
        $dn = $this->notes->issue(array_merge($request->only([
            'supplier_id', 'bill_id', 'sdn_number', 'reason_code', 'reason_description', 'issue_date', 'notes',
        ]), ['created_by' => auth()->id()]), $request->input('items'));

        return redirect()->route('supplier-debit-notes.show', $dn->id)->with('success', 'Supplier debit note issued.');
    }

    public function void($id)
    {
        try {
            $this->notes->void(SupplierDebitNote::findOrFail($id));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Supplier debit note voided.');
    }

    public function downloadPdf($id)
    {
        $dn = SupplierDebitNote::with(['items', 'supplier'])->findOrFail($id);
        $pdf = Pdf::loadView('pdf.sales-document', [
            'title'      => 'Supplier Debit Note',
            'number'     => $dn->sdn_number,
            'issue_date' => optional($dn->issue_date)->toDateString(),
            'customer'   => $dn->supplier,
            'company'    => tenant()?->getCompanyDetails() ?? config('invoice.company'),
            'items'      => $dn->items,
            'tax'        => $dn->tax_amount,
            'total'      => $dn->total_amount,
            'currency'   => $dn->currency,
            'notes'      => $dn->notes,
            'qr_url'     => null,
        ]);

        return $pdf->stream("Supplier-DN-{$dn->sdn_number}.pdf");
    }
}
