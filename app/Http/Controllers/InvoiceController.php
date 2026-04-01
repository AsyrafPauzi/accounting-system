<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Invoice;
use App\Models\Customer;
use App\Jobs\SendInvoiceEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoiceService) {}
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

        $baseQuery = DB::table('invoices')
            ->join('customers', 'invoices.customer_id', '=', 'customers.id')
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
        if ($statusFilter !== '') {
            $baseQuery->where('invoices.status', $statusFilter);
        }

        // KPIs from filtered set (same filters, no pagination).
        // Use select(DB::raw(...)): selectRaw() addSelect() would keep invoices.* from
        // $baseQuery and break ONLY_FULL_GROUP_BY. reorder() drops orderBy on the clone.
        $totalCount = (clone $baseQuery)->count();
        $totalOutstanding = (clone $baseQuery)
            ->reorder()
            ->whereNotIn('invoices.status', ['draft', 'void'])
            ->select(DB::raw('COALESCE(SUM(invoices.total_amount - invoices.amount_paid), 0) as total'))
            ->value('total') ?? 0;
        $totalCollected = (clone $baseQuery)
            ->reorder()
            ->select(DB::raw('COALESCE(SUM(invoices.amount_paid), 0) as total'))
            ->value('total') ?? 0;

        $paginator = $baseQuery->paginate($perPage)->withQueryString();
        $invoices = $paginator->items();

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
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
        $lastInv = Invoice::where('invoice_number', 'like', 'INV-%')->orderBy('id', 'desc')->first();
        $nextNumber = 'INV-1';
        if ($lastInv && preg_match('/^INV-(\d+)$/', $lastInv->invoice_number, $m)) {
            $nextNumber = 'INV-' . ((int) $m[1] + 1);
        }

        return Inertia::render('Invoices/Create', [
            'customers' => Customer::all(),
            'lhdn_codes' => $this->getLhdnCodes(),
            'customer_id' => $request->query('customer_id'),
            'next_invoice_number' => $nextNumber,
        ]);
    }

    /**
     * Store a newly created invoice in storage (Draft Mode).
     */
    public function store(StoreInvoiceRequest $request)
    {
        $this->invoiceService->create(
            array_merge($request->except('items'), ['created_by' => auth()->id()]),
            $request->input('items')
        );

        return redirect()->route('invoices.index');
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
        return Inertia::render('Invoices/Edit', [
            'invoice'    => $invoice,
            'customers'  => Customer::all(),
            'lhdn_codes' => $this->getLhdnCodes(),
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

        return redirect()->route('invoices.index');
    }

    /**
     * Delete a draft invoice (soft delete).
     */
    public function destroy($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->items()->delete();
        $invoice->delete();
        return redirect()->route('invoices.index');
    }

    /**
     * Download invoice as enterprise-standard PDF.
     */
    public function downloadPdf($id)
    {
        $invoice = Invoice::with(['items', 'customer'])->findOrFail($id);
        $company = config('invoice.company');

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice'  => $invoice,
            'customer' => $invoice->customer,
            'company'  => $company,
        ])->setPaper('a4', 'portrait');

        return $pdf->download("Invoice-{$invoice->invoice_number}.pdf");
    }

    /**
     * Email the invoice PDF directly to the customer.
     */
    public function emailPdf($id)
    {
        $invoice = Invoice::with(['customer.contacts'])->findOrFail($id);

        if (!$invoice->customer) {
            return redirect()->back()->with('error', 'Customer not found.');
        }

        $customer = $invoice->customer;
        if (($customer->invoice_delivery_method ?? 'email') === 'none') {
            return redirect()->back()->with('error', 'Customer has invoice delivery set to Do not email.');
        }

        $recipients = [];
        $billingContacts = $customer->contacts->where('type', 'billing')->filter(fn ($c) => $c->email && filter_var($c->email, FILTER_VALIDATE_EMAIL));
        if ($billingContacts->isNotEmpty()) {
            $recipients = $billingContacts->pluck('email')->unique()->values()->all();
        }
        if (empty($recipients) && $customer->email && filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
            $recipients = [$customer->email];
        }
        if (empty($recipients)) {
            return redirect()->back()->with('error', 'Customer does not have a valid email address or billing contact.');
        }

        $invoice->forceFill([
            'last_emailed_status' => 'pending',
            'last_emailed_at'     => now(),
            'last_emailed_error'  => null,
            'last_emailed_to'     => implode(',', $recipients),
        ])->save();

        SendInvoiceEmail::dispatch($invoice->id, $recipients);

        return redirect()->back()->with('success', 'Invoice email queued for delivery.');
    }

    /**
     * Record a payment receipt.
     */
    public function recordPayment(Request $request, $id)
    {
        $request->validate([
            'amount'            => 'required|numeric|min:0.01',
            'payment_date'      => 'required|date',
            'bank_account_code' => 'required|string',
        ]);

        $invoice = Invoice::findOrFail($id);
        try {
            $this->invoiceService->recordPayment(
                $invoice,
                (float) $request->amount,
                $request->payment_date,
                $request->bank_account_code
            );
        } catch (\LogicException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('invoices.index');
    }
}