<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class BillController extends Controller
{
    protected const AP_ACCOUNT = '2110';

    public function index(Request $request): Response
    {
        $query = Bill::with('supplier')->orderByDesc('bill_date');

        $statusFilter = $request->input('status');
        $supplierId = $request->input('supplier_id');
        if ($statusFilter && in_array($statusFilter, ['draft', 'unpaid', 'partially paid', 'paid', 'void'], true)) {
            $query->where('status', $statusFilter);
        }
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $bills = $query->get()->map(function (Bill $bill) {
            $bill->supplier_name = $bill->supplier?->name ?? '—';
            $bill->balance_due = $bill->balance_due;
            return $bill;
        });

        $totalOutstanding = $bills->whereIn('status', ['unpaid', 'partially paid'])->sum(fn (Bill $b) => (float) $b->total_amount - (float) $b->amount_paid);
        $totalPaidPeriod = $bills->where('status', 'paid')->sum('amount_paid');
        $assetAccounts = Account::where('type', 'asset')->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->code} — {$a->name}"])->values()->all();

        return Inertia::render('Bills/Index', [
            'bills' => $bills,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name', 'code']),
            'assetAccounts' => $assetAccounts,
            'totalOutstanding' => round($totalOutstanding, 2),
            'totalPaidPeriod' => round($totalPaidPeriod, 2),
        ]);
    }

    public function create(Request $request): Response
    {
        $supplierId = $request->query('supplier_id');
        $lastBill = Bill::where('bill_number', 'like', 'BILL-%')->orderBy('id', 'desc')->first();
        $nextNumber = 'BILL-1';
        if ($lastBill && preg_match('/^BILL-(\d+)$/', $lastBill->bill_number, $m)) {
            $nextNumber = 'BILL-' . ((int) $m[1] + 1);
        }

        $expenseAccounts = Account::where('type', 'expense')->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->code} — {$a->name}"])->values()->all();
        $assetAccounts = Account::where('type', 'asset')->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->code} — {$a->name}"])->values()->all();

        return Inertia::render('Bills/Create', [
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'expenseAccounts' => $expenseAccounts,
            'assetAccounts' => $assetAccounts,
            'nextBillNumber' => $nextNumber,
            'preselectedSupplierId' => $supplierId ? (int) $supplierId : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bill_number' => 'required|string|max:50|unique:bills,bill_number',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'bill_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:bill_date',
            'tax_amount' => 'nullable|numeric|min:0',
            'reference' => 'nullable|string|max:100',
            'private_notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.account_code' => 'required|string|exists:accounts,code',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.unit_amount' => 'nullable|numeric|min:0',
            'items.*.amount' => 'required|numeric|min:0',
        ]);

        $totalAmount = collect($validated['items'])->sum(fn ($i) => (float) $i['amount']);
        $taxAmount = (float) ($validated['tax_amount'] ?? 0);
        $totalAmount += $taxAmount;

        $bill = DB::transaction(function () use ($request, $validated, $totalAmount, $taxAmount) {
            $bill = Bill::create([
                'bill_number' => $validated['bill_number'],
                'supplier_id' => $validated['supplier_id'] ?? null,
                'bill_date' => $validated['bill_date'],
                'due_date' => $validated['due_date'] ?? null,
                'status' => 'draft',
                'total_amount' => $totalAmount,
                'amount_paid' => 0,
                'tax_amount' => $taxAmount,
                'currency' => 'MYR',
                'private_notes' => $validated['private_notes'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'created_by' => $request->user()?->id,
            ]);

            foreach ($validated['items'] as $idx => $item) {
                $bill->items()->create([
                    'account_code' => $item['account_code'],
                    'description' => $item['description'] ?? '',
                    'quantity' => (float) ($item['quantity'] ?? 1),
                    'unit_amount' => (float) ($item['unit_amount'] ?? $item['amount']),
                    'amount' => (float) $item['amount'],
                    'sort_order' => $idx,
                ]);
            }
            return $bill;
        });

        return redirect()->route('bills.edit', $bill->id)->with('success', 'Bill created as draft.');
    }

    public function edit(int $id): Response|RedirectResponse
    {
        $bill = Bill::with(['supplier', 'items'])->findOrFail($id);
        $expenseAccounts = Account::where('type', 'expense')->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->code} — {$a->name}"])->values()->all();
        $assetAccounts = Account::where('type', 'asset')->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->code} — {$a->name}"])->values()->all();

        return Inertia::render('Bills/Edit', [
            'bill' => $bill,
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name', 'code']),
            'expenseAccounts' => $expenseAccounts,
            'assetAccounts' => $assetAccounts,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $bill = Bill::with('items')->findOrFail($id);
        if ($bill->status !== 'draft') {
            return redirect()->route('bills.edit', $id)->with('error', 'Only draft bills can be edited.');
        }

        $validated = $request->validate([
            'bill_number' => 'required|string|max:50|unique:bills,bill_number,' . $id,
            'supplier_id' => 'nullable|exists:suppliers,id',
            'bill_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:bill_date',
            'tax_amount' => 'nullable|numeric|min:0',
            'reference' => 'nullable|string|max:100',
            'private_notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|integer',
            'items.*.account_code' => 'required|string|exists:accounts,code',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.unit_amount' => 'nullable|numeric|min:0',
            'items.*.amount' => 'required|numeric|min:0',
        ]);

        $totalAmount = collect($validated['items'])->sum(fn ($i) => (float) $i['amount']);
        $taxAmount = (float) ($validated['tax_amount'] ?? 0);
        $totalAmount += $taxAmount;

        DB::transaction(function () use ($bill, $validated, $totalAmount, $taxAmount) {
            $bill->update([
                'bill_number' => $validated['bill_number'],
                'supplier_id' => $validated['supplier_id'] ?? null,
                'bill_date' => $validated['bill_date'],
                'due_date' => $validated['due_date'] ?? null,
                'total_amount' => $totalAmount,
                'tax_amount' => $taxAmount,
                'private_notes' => $validated['private_notes'] ?? null,
                'reference' => $validated['reference'] ?? null,
            ]);
            $bill->items()->delete();
            foreach ($validated['items'] as $idx => $item) {
                $bill->items()->create([
                    'account_code' => $item['account_code'],
                    'description' => $item['description'] ?? '',
                    'quantity' => (float) ($item['quantity'] ?? 1),
                    'unit_amount' => (float) ($item['unit_amount'] ?? $item['amount']),
                    'amount' => (float) $item['amount'],
                    'sort_order' => $idx,
                ]);
            }
        });

        return redirect()->route('bills.edit', $id)->with('success', 'Bill updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $bill = Bill::findOrFail($id);
        if ($bill->status !== 'draft') {
            return redirect()->route('bills.index')->with('error', 'Only draft bills can be deleted.');
        }
        $bill->items()->delete();
        $bill->delete();
        return redirect()->route('bills.index')->with('success', 'Bill deleted.');
    }

    public function postBill(int $id): RedirectResponse
    {
        return DB::transaction(function () use ($id) {
            $bill = Bill::with('items')->findOrFail($id);
            if ($bill->status !== 'draft') {
                return redirect()->back()->with('error', 'Bill is already posted.');
            }

            $journalId = DB::table('journal_entries')->insertGetId([
                'date' => $bill->bill_date,
                'description' => 'Posted Bill: ' . $bill->bill_number,
                'reference_type' => 'Bill',
                'reference_id' => $bill->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($bill->items as $item) {
                DB::table('journal_items')->insert([
                    'journal_entry_id' => $journalId,
                    'account_code' => $item->account_code,
                    'debit' => $item->amount,
                    'credit' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if ($bill->tax_amount > 0) {
                DB::table('journal_items')->insert([
                    'journal_entry_id' => $journalId,
                    'account_code' => '2100',
                    'debit' => $bill->tax_amount,
                    'credit' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('journal_items')->insert([
                'journal_entry_id' => $journalId,
                'account_code' => self::AP_ACCOUNT,
                'debit' => 0,
                'credit' => $bill->total_amount,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $bill->update(['status' => 'unpaid']);
            return redirect()->back()->with('success', 'Bill posted to ledger.');
        });
    }

    public function voidBill(int $id): RedirectResponse
    {
        $bill = Bill::findOrFail($id);
        if (in_array($bill->status, ['draft', 'void'], true)) {
            return redirect()->back()->with('error', 'Only posted bills can be voided.');
        }

        DB::transaction(function () use ($id) {
            $bill = Bill::with('items')->findOrFail($id);

            $journalId = DB::table('journal_entries')->insertGetId([
                'date' => now(),
                'description' => 'VOID REVERSAL: ' . $bill->bill_number,
                'reference_type' => 'Bill',
                'reference_id' => $bill->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($bill->items as $item) {
                DB::table('journal_items')->insert([
                    'journal_entry_id' => $journalId,
                    'account_code' => $item->account_code,
                    'debit' => 0,
                    'credit' => $item->amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if ($bill->tax_amount > 0) {
                DB::table('journal_items')->insert([
                    'journal_entry_id' => $journalId,
                    'account_code' => '2100',
                    'debit' => 0,
                    'credit' => $bill->tax_amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('journal_items')->insert([
                'journal_entry_id' => $journalId,
                'account_code' => self::AP_ACCOUNT,
                'debit' => $bill->total_amount,
                'credit' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $bill->update(['status' => 'void', 'amount_paid' => 0]);
        });
        return redirect()->back()->with('success', 'Bill voided.');
    }

    public function recordPayment(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'bank_account_code' => 'required|string|exists:accounts,code',
        ]);

        $bill = Bill::findOrFail($id);
        if (in_array($bill->status, ['draft', 'void'], true)) {
            return redirect()->back()->with('error', 'Cannot record payment for draft or void bills.');
        }

        DB::transaction(function () use ($id, $request) {
            $bill = Bill::findOrFail($id);
            $paymentAmount = (float) $request->amount;
            $newPaid = (float) $bill->amount_paid + $paymentAmount;
            $total = (float) $bill->total_amount;
            $status = $newPaid >= $total ? 'paid' : 'partially paid';

            $bill->update([
                'amount_paid' => min($newPaid, $total),
                'status' => $status,
            ]);

            $journalId = DB::table('journal_entries')->insertGetId([
                'date' => $request->payment_date,
                'description' => 'Payment for Bill ' . $bill->bill_number,
                'reference_type' => 'Bill Payment',
                'reference_id' => $bill->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('journal_items')->insert([
                ['journal_entry_id' => $journalId, 'account_code' => self::AP_ACCOUNT, 'debit' => $paymentAmount, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['journal_entry_id' => $journalId, 'account_code' => $request->bank_account_code, 'debit' => 0, 'credit' => $paymentAmount, 'created_at' => now(), 'updated_at' => now()],
            ]);
        });

        return redirect()->route('bills.index')->with('success', 'Payment recorded.');
    }
}
