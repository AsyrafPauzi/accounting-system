<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\CreditNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CreditNoteController extends Controller
{
    public function index()
    {
        // Fetch all Credit Notes with Customer names
        $creditNotes = DB::table('credit_notes')
            ->join('customers', 'credit_notes.customer_id', '=', 'customers.id')
            ->select('credit_notes.*', 'customers.name as customer_name')
            ->orderBy('credit_notes.created_at', 'desc')
            ->get();

        return Inertia::render('CreditNotes/Index', [
            'creditNotes' => $creditNotes
        ]);
    }

    public function create($invoice_id)
    {
        // Load invoice with its items for the CN creation form
        $invoice = Invoice::with(['items', 'customer'])->findOrFail($invoice_id);
        
        return Inertia::render('CreditNotes/Create', [
            'invoice' => $invoice,
            'lhdn_reasons' => [
                ['id' => '01', 'name' => '01 - Return of Goods'],
                ['id' => '02', 'name' => '02 - Pricing Error'],
                ['id' => '03', 'name' => '03 - Discount/Rebate'],
                ['id' => '04', 'name' => '04 - Cancellation of Service']
            ]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'cn_number' => 'required|unique:credit_notes',
            'reason_code' => 'required|string',
            'items' => 'required|array'
        ]);

        DB::transaction(function () use ($request) {
            $totalAmount = collect($request->items)->sum('amount');
            
            $cn = CreditNote::create([
                'invoice_id' => $request->invoice_id,
                'customer_id' => $request->customer_id,
                'cn_number' => $request->cn_number,
                'issue_date' => now(),
                'reason_code' => $request->reason_code,
                'reason_description' => $request->reason_description,
                'total_amount' => $totalAmount,
                'status' => 'posted'
            ]);

            foreach ($request->items as $item) {
                DB::table('credit_note_items')->insert([
                    'credit_note_id' => $cn->id,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'] ?? 0,
                    'amount' => $item['amount'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // ACCOUNTING: Reverse the Ledger (Decrease Revenue and Decrease AR)
            $journalId = DB::table('journal_entries')->insertGetId([
                'date' => now(),
                'description' => "Credit Note Issued: " . $cn->cn_number,
                'reference_type' => 'Credit Note',
                'reference_id' => $cn->id,
                'created_at' => now(),
            ]);

            // DEBIT Sales Revenue (4000) - Decrease income
            DB::table('journal_items')->insert([
                'journal_entry_id' => $journalId,
                'account_code' => '4000',
                'debit' => $totalAmount,
                'credit' => 0,
                'created_at' => now()
            ]);

            // CREDIT Accounts Receivable (1100) - Decrease what customer owes
            DB::table('journal_items')->insert([
                'journal_entry_id' => $journalId,
                'account_code' => '1100',
                'debit' => 0,
                'credit' => $totalAmount,
                'created_at' => now()
            ]);
        });

        return redirect()->route('credit-notes.index')->with('success', 'Credit Note issued and ledger updated successfully.');
    }
}