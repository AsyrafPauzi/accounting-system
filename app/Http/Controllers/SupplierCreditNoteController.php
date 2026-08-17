<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Bill;
use App\Models\Supplier;
use App\Models\SupplierCreditNote;
use App\Services\SupplierCreditNoteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierCreditNoteController extends Controller
{
    public function __construct(protected SupplierCreditNoteService $notes) {}

    public function index()
    {
        $notes = SupplierCreditNote::query()->with('supplier:id,name')->orderByDesc('id')->get()
            ->map(fn ($n) => [...$n->toArray(), 'supplier_name' => $n->supplier?->name]);

        return Inertia::render('SupplierCreditNotes/Index', ['notes' => $notes]);
    }

    public function show($id)
    {
        $cn = SupplierCreditNote::with(['items', 'supplier', 'bill:id,bill_number,status', 'applications.bill:id,bill_number', 'refunds'])->findOrFail($id);
        $openBills = Bill::query()
            ->where('supplier_id', $cn->supplier_id)
            ->whereNotIn('status', ['draft', 'void', 'paid'])
            ->get(['id', 'bill_number', 'total_amount', 'amount_paid', 'status']);

        return Inertia::render('SupplierCreditNotes/Show', [
            'creditNote'   => array_merge($cn->toArray(), ['open_amount' => $cn->openAmount()]),
            'openBills'    => $openBills,
            'bankAccounts' => Account::bankOrCash()->active()->orderBy('code')->get(['code', 'name']),
        ]);
    }

    public function create(Request $request)
    {
        $bill = $request->query('bill_id') ? Bill::with(['items', 'supplier'])->find($request->query('bill_id')) : null;

        return Inertia::render('SupplierCreditNotes/Create', [
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
        $cn = $this->notes->issue(array_merge($request->only([
            'supplier_id', 'bill_id', 'scn_number', 'reason_code', 'reason_description', 'issue_date', 'notes',
        ]), ['created_by' => auth()->id()]), $request->input('items'));

        return redirect()->route('supplier-credit-notes.show', $cn->id)->with('success', 'Supplier credit note issued.');
    }

    public function apply(Request $request, $id)
    {
        $request->validate(['bill_id' => 'required|exists:bills,id', 'amount' => 'required|numeric|min:0.01']);
        try {
            $this->notes->applyToBill(SupplierCreditNote::findOrFail($id), Bill::findOrFail($request->bill_id), (float) $request->amount);
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Credit applied to bill.');
    }

    public function refund(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'bank_account_code' => 'required|exists:accounts,code',
        ]);
        try {
            $this->notes->refund(
                SupplierCreditNote::findOrFail($id),
                (float) $request->amount,
                $request->bank_account_code,
                $request->payment_date,
                $request->input('reference'),
                auth()->id()
            );
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Supplier credit refunded to bank.');
    }

    public function void($id)
    {
        try {
            $this->notes->void(SupplierCreditNote::findOrFail($id));
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Supplier credit note voided.');
    }

    public function downloadPdf($id)
    {
        $cn = SupplierCreditNote::with(['items', 'supplier'])->findOrFail($id);
        $pdf = Pdf::loadView('pdf.sales-document', [
            'title'      => 'Supplier Credit Note',
            'number'     => $cn->scn_number,
            'issue_date' => optional($cn->issue_date)->toDateString(),
            'customer'   => $cn->supplier,
            'company'    => tenant()?->getCompanyDetails() ?? config('invoice.company'),
            'items'      => $cn->items,
            'tax'        => $cn->tax_amount,
            'total'      => $cn->total_amount,
            'currency'   => $cn->currency,
            'notes'      => $cn->notes,
            'qr_url'     => null,
        ]);

        return $pdf->stream("Supplier-CN-{$cn->scn_number}.pdf");
    }
}
