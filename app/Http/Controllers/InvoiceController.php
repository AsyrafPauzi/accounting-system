<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\InvoiceItem;
use App\Jobs\SendInvoiceEmail;

class InvoiceController extends Controller
{
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

        // KPIs from filtered set (same filters, no pagination)
        $totalCount = (clone $baseQuery)->count();
        $totalOutstanding = (clone $baseQuery)
            ->whereNotIn('invoices.status', ['draft', 'void'])
            ->selectRaw('COALESCE(SUM(invoices.total_amount - invoices.amount_paid), 0) as total')
            ->value('total') ?? 0;
        $totalCollected = (clone $baseQuery)->selectRaw('COALESCE(SUM(invoices.amount_paid), 0) as total')->value('total') ?? 0;

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
    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'invoice_number' => 'required|string|unique:invoices',
            'msic_code' => 'required|string',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric',
            'items.*.tax_rate' => 'required|numeric',
            'items.*.item_classification' => 'required|string',
            'items.*.discount_amount' => 'nullable|numeric',
            'shipping_amount' => 'nullable|numeric',
            'customer_notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            $subtotal = collect($request->items)->sum(fn($i) => $i['quantity'] * $i['unit_price']);
            $discountTotal = collect($request->items)->sum(fn($i) => $i['discount_amount'] ?? 0);
            
            $taxTotal = collect($request->items)->sum(function($i) {
                $itemAmount = ($i['quantity'] * $i['unit_price']) - ($i['discount_amount'] ?? 0);
                return ($itemAmount * $i['tax_rate']) / 100;
            });

            $shipping = $request->shipping_amount ?? 0;
            $rawTotal = ($subtotal - $discountTotal) + $taxTotal + $shipping;

            // Enterprise Feature: Malaysia 5-Sen Rounding
            $roundedTotal = round($rawTotal / 0.05) * 0.05;
            $roundingAdjustment = $roundedTotal - $rawTotal;

            $invoice = Invoice::create([
                'invoice_number' => $request->invoice_number,
                'msic_code' => $request->msic_code,
                'customer_id' => $request->customer_id,
                'issue_date' => $request->issue_date,
                'due_date' => $request->due_date,
                'amount_before_tax' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_amount' => $taxTotal,
                'shipping_amount' => $shipping,
                'rounding_adjustment' => $roundingAdjustment,
                'total_amount' => $roundedTotal,
                'customer_notes' => $request->customer_notes,
                'status' => 'draft',
                'lhdn_status' => 'pending',
                'created_by' => auth()->id(),
            ]);

            foreach ($request->items as $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'],
                    'item_classification' => $item['item_classification'],
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'amount' => ($item['quantity'] * $item['unit_price']) - ($item['discount_amount'] ?? 0),
                ]);
            }
        });

        return redirect()->route('invoices.index');
    }

    /**
     * Post the invoice to the General Ledger.
     */
    public function postInvoice($id)
    {
        return DB::transaction(function () use ($id) {
            $invoice = Invoice::with('customer')->findOrFail($id);

            if ($invoice->status !== 'draft') {
                return redirect()->back()->with('error', 'Invoice is already posted.');
            }

            $customer = $invoice->customer;
            if ($customer) {
                if ($customer->credit_hold) {
                    return redirect()->back()->with('error', 'Customer is on credit hold. Cannot post invoice.');
                }
                $creditLimit = (float) ($customer->credit_limit ?? 0);
                if ($creditLimit > 0) {
                    $balance = (float) $customer->balance;
                    $projected = $balance + (float) $invoice->total_amount;
                    if ($projected > $creditLimit) {
                        return redirect()->back()->with('error', 'Posting would exceed customer credit limit (RM ' . number_format($creditLimit, 2) . '). Current exposure: RM ' . number_format($balance, 2) . '.');
                    }
                }
            }

            // Create Journal Entry Header
            $journalId = DB::table('journal_entries')->insertGetId([
                'date' => now(),
                'description' => "Posted Sales Invoice: " . $invoice->invoice_number,
                'reference_type' => 'Invoice',
                'reference_id' => $invoice->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // DEBIT: Accounts Receivable (1100)
            DB::table('journal_items')->insert([
                'journal_entry_id' => $journalId,
                'account_code' => '1100', 
                'debit' => $invoice->total_amount,
                'credit' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // CREDIT: Sales Revenue (4000) - Net of discount, excluding tax
            DB::table('journal_items')->insert([
                'journal_entry_id' => $journalId,
                'account_code' => '4000',
                'debit' => 0,
                'credit' => ($invoice->amount_before_tax - $invoice->discount_total) + $invoice->shipping_amount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // CREDIT: SST Payable (2100)
            if ($invoice->tax_amount > 0) {
                DB::table('journal_items')->insert([
                    'journal_entry_id' => $journalId,
                    'account_code' => '2100',
                    'debit' => 0,
                    'credit' => $invoice->tax_amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // NOTE: Rounding adjustments are typically handled in a 'Rounding' account 
            // if significant, but for 5-sen, it is often bundled into the revenue line.

            $invoice->update(['status' => 'unpaid']);

            return redirect()->back()->with('success', 'Invoice posted to ledger.');
        });
    }

    /**
     * Void an invoice and reverse ledger impact.
     */
    public function voidInvoice($id)
    {
        DB::transaction(function () use ($id) {
            $invoice = Invoice::findOrFail($id);
            if ($invoice->status === 'void' || $invoice->status === 'draft') return;

            $journalId = DB::table('journal_entries')->insertGetId([
                'date' => now(),
                'description' => "VOID REVERSAL: " . $invoice->invoice_number,
                'reference_type' => 'Invoice',
                'reference_id' => $invoice->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Reverse the original entries
            DB::table('journal_items')->insert([
                ['journal_entry_id' => $journalId, 'account_code' => '1100', 'debit' => 0, 'credit' => $invoice->total_amount, 'created_at' => now(), 'updated_at' => now()],
                ['journal_entry_id' => $journalId, 'account_code' => '4000', 'debit' => ($invoice->amount_before_tax - $invoice->discount_total) + $invoice->shipping_amount, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()],
            ]);

            if ($invoice->tax_amount > 0) {
                DB::table('journal_items')->insert([
                    ['journal_entry_id' => $journalId, 'account_code' => '2100', 'debit' => $invoice->tax_amount, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()]
                ]);
            }

            $invoice->update(['status' => 'void', 'amount_paid' => 0]);
        });
        return redirect()->back();
    }

    /**
     * Show edit form.
     */
    public function edit($id)
    {
        $invoice = Invoice::with('items')->findOrFail($id);
        return Inertia::render('Invoices/Edit', [
            'invoice' => $invoice,
            'customers' => Customer::all(),
            'lhdn_codes' => $this->getLhdnCodes()
        ]);
    }

    /**
     * Update an existing invoice.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'msic_code' => 'required|string',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.item_classification' => 'required',
        ]);

        DB::transaction(function () use ($id, $request) {
            $invoice = Invoice::findOrFail($id);
            if ($invoice->status === 'paid' || $invoice->status === 'void') return;

            $subtotal = collect($request->items)->sum(fn($i) => $i['quantity'] * $i['unit_price']);
            $discountTotal = collect($request->items)->sum(fn($i) => $i['discount_amount'] ?? 0);
            $taxTotal = collect($request->items)->sum(function($i) {
                $itemAmount = ($i['quantity'] * $i['unit_price']) - ($i['discount_amount'] ?? 0);
                return ($itemAmount * $i['tax_rate']) / 100;
            });

            $shipping = $request->shipping_amount ?? 0;
            $rawTotal = ($subtotal - $discountTotal) + $taxTotal + $shipping;
            $roundedTotal = round($rawTotal / 0.05) * 0.05;
            $roundingAdjustment = $roundedTotal - $rawTotal;

            $invoice->update([
                'customer_id' => $request->customer_id,
                'msic_code' => $request->msic_code,
                'issue_date' => $request->issue_date,
                'due_date' => $request->due_date,
                'amount_before_tax' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_amount' => $taxTotal,
                'shipping_amount' => $shipping,
                'rounding_adjustment' => $roundingAdjustment,
                'total_amount' => $roundedTotal,
                'customer_notes' => $request->customer_notes,
            ]);

            $invoice->items()->delete();
            foreach ($request->items as $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'],
                    'item_classification' => $item['item_classification'],
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'amount' => ($item['quantity'] * $item['unit_price']) - ($item['discount_amount'] ?? 0),
                ]);
            }

            // Sync Ledger if already posted
            if ($invoice->status !== 'draft') {
                $journal = DB::table('journal_entries')->where('reference_type', 'Invoice')->where('reference_id', $id)->latest()->first();
                if ($journal) {
                    DB::table('journal_items')->where('journal_entry_id', $journal->id)->delete();
                    DB::table('journal_items')->insert([
                        ['journal_entry_id' => $journal->id, 'account_code' => '1100', 'debit' => $invoice->total_amount, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()],
                        ['journal_entry_id' => $journal->id, 'account_code' => '4000', 'debit' => 0, 'credit' => ($subtotal - $discountTotal) + $shipping, 'created_at' => now(), 'updated_at' => now()],
                    ]);
                    if ($taxTotal > 0) {
                        DB::table('journal_items')->insert(['journal_entry_id' => $journal->id, 'account_code' => '2100', 'debit' => 0, 'credit' => $taxTotal, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
            }
        });
        return redirect()->route('invoices.index');
    }

    /**
     * Delete an invoice and its ledger history.
     */
    public function destroy($id)
    {
        DB::transaction(function () use ($id) {
            $journals = DB::table('journal_entries')->where('reference_type', 'Invoice')->where('reference_id', $id)->get();
            foreach ($journals as $j) {
                DB::table('journal_items')->where('journal_entry_id', $j->id)->delete();
                DB::table('journal_entries')->where('id', $j->id)->delete();
            }
            Invoice::findOrFail($id)->delete();
        });
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
            'invoice' => $invoice,
            'customer' => $invoice->customer,
            'company' => $company,
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
            'last_emailed_at' => now(),
            'last_emailed_error' => null,
            'last_emailed_to' => implode(',', $recipients),
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
            'amount' => 'required|numeric|min:0.01', 
            'payment_date' => 'required|date', 
            'bank_account_code' => 'required|string'
        ]);

        DB::transaction(function () use ($id, $request) {
            $invoice = Invoice::findOrFail($id);
            if ($invoice->status === 'draft' || $invoice->status === 'void') return;

            $paymentAmount = (float) $request->amount;
            $newAmountPaid = (float) $invoice->amount_paid + $paymentAmount;
            
            // Determine new status
            $status = ($newAmountPaid >= (float) $invoice->total_amount) ? 'paid' : 'partially paid';

            $invoice->update([
                'amount_paid' => min($newAmountPaid, $invoice->total_amount), 
                'status' => $status
            ]);

            // Ledger: Debit Bank, Credit AR
            $journalId = DB::table('journal_entries')->insertGetId([
                'date' => $request->payment_date, 
                'description' => "Payment for " . $invoice->invoice_number, 
                'reference_type' => 'Invoice Payment', 
                'reference_id' => $invoice->id, 
                'created_at' => now(),
                'updated_at' => now()
            ]);

            DB::table('journal_items')->insert([
                ['journal_entry_id' => $journalId, 'account_code' => $request->bank_account_code, 'debit' => $paymentAmount, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['journal_entry_id' => $journalId, 'account_code' => '1100', 'debit' => 0, 'credit' => $paymentAmount, 'created_at' => now(), 'updated_at' => now()],
            ]);
        });
        return redirect()->route('invoices.index');
    }
}