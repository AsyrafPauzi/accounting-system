<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    /**
     * Compute totals from a line-item array.
     */
    public function computeTotals(array $items, float $shipping = 0.0): array
    {
        $subtotal = collect($items)->sum(fn ($i) => $i['quantity'] * $i['unit_price']);
        $discountTotal = collect($items)->sum(fn ($i) => (float) ($i['discount_amount'] ?? 0));
        $taxTotal = collect($items)->sum(function ($i) {
            $itemAmount = ($i['quantity'] * $i['unit_price']) - (float) ($i['discount_amount'] ?? 0);
            return ($itemAmount * $i['tax_rate']) / 100;
        });

        $rawTotal = ($subtotal - $discountTotal) + $taxTotal + $shipping;
        $roundedTotal = round($rawTotal / 0.05) * 0.05;
        $roundingAdjustment = $roundedTotal - $rawTotal;

        return compact('subtotal', 'discountTotal', 'taxTotal', 'roundedTotal', 'roundingAdjustment');
    }

    /**
     * Create a draft invoice with line items inside a transaction.
     */
    public function create(array $data, array $items): Invoice
    {
        return DB::transaction(function () use ($data, $items) {
            $totals = $this->computeTotals($items, (float) ($data['shipping_amount'] ?? 0));

            $invoice = Invoice::create([
                'invoice_number'     => $data['invoice_number'],
                'msic_code'          => $data['msic_code'],
                'customer_id'        => $data['customer_id'],
                'issue_date'         => $data['issue_date'],
                'due_date'           => $data['due_date'] ?? null,
                'amount_before_tax'  => $totals['subtotal'],
                'discount_total'     => $totals['discountTotal'],
                'tax_amount'         => $totals['taxTotal'],
                'shipping_amount'    => $data['shipping_amount'] ?? 0,
                'rounding_adjustment'=> $totals['roundingAdjustment'],
                'total_amount'       => $totals['roundedTotal'],
                'customer_notes'     => $data['customer_notes'] ?? null,
                'status'             => 'draft',
                'lhdn_status'        => 'pending',
                'created_by'         => $data['created_by'] ?? null,
            ]);

            $this->syncItems($invoice, $items);

            return $invoice;
        });
    }

    /**
     * Update a draft or unpaid invoice and its line items.
     */
    public function update(Invoice $invoice, array $data, array $items): void
    {
        DB::transaction(function () use ($invoice, $data, $items) {
            $totals = $this->computeTotals($items, (float) ($data['shipping_amount'] ?? 0));

            $invoice->update([
                'customer_id'        => $data['customer_id'],
                'msic_code'          => $data['msic_code'],
                'issue_date'         => $data['issue_date'],
                'due_date'           => $data['due_date'] ?? null,
                'amount_before_tax'  => $totals['subtotal'],
                'discount_total'     => $totals['discountTotal'],
                'tax_amount'         => $totals['taxTotal'],
                'shipping_amount'    => $data['shipping_amount'] ?? 0,
                'rounding_adjustment'=> $totals['roundingAdjustment'],
                'total_amount'       => $totals['roundedTotal'],
                'customer_notes'     => $data['customer_notes'] ?? null,
            ]);

            $invoice->items()->delete();
            $this->syncItems($invoice, $items);

            if ($invoice->status !== 'draft') {
                $this->syncJournalEntry($invoice, $totals);
            }
        });
    }

    /**
     * Post a draft invoice to the General Ledger.
     */
    public function post(Invoice $invoice): void
    {
        if ($invoice->status !== 'draft') {
            throw new \LogicException('Invoice is already posted.');
        }

        $customer = $invoice->customer;
        if ($customer) {
            if ($customer->credit_hold) {
                throw new \LogicException('Customer is on credit hold. Cannot post invoice.');
            }
            $creditLimit = (float) ($customer->credit_limit ?? 0);
            if ($creditLimit > 0) {
                $balance = (float) $customer->balance;
                $projected = $balance + (float) $invoice->total_amount;
                if ($projected > $creditLimit) {
                    throw new \LogicException(
                        'Posting would exceed customer credit limit (RM ' . number_format($creditLimit, 2) . '). Current exposure: RM ' . number_format($balance, 2) . '.'
                    );
                }
            }
        }

        DB::transaction(function () use ($invoice) {
            $journalId = DB::table('journal_entries')->insertGetId([
                'date'           => now(),
                'description'    => 'Posted Sales Invoice: ' . $invoice->invoice_number,
                'reference_type' => 'Invoice',
                'reference_id'   => $invoice->id,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $revenueNet = (float) ($invoice->amount_before_tax - $invoice->discount_total)
                + (float) $invoice->shipping_amount
                + (float) $invoice->rounding_adjustment;

            $accountMap = DB::table('accounts')->whereIn('code', ['1100', '4000', '2100'])->pluck('id', 'code');

            $journalItems = [
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap['1100'] ?? null, 'account_code' => '1100', 'debit' => $invoice->total_amount, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap['4000'] ?? null, 'account_code' => '4000', 'debit' => 0, 'credit' => $revenueNet, 'created_at' => now(), 'updated_at' => now()],
            ];
            if ($invoice->tax_amount > 0) {
                $journalItems[] = ['journal_entry_id' => $journalId, 'account_id' => $accountMap['2100'] ?? null, 'account_code' => '2100', 'debit' => 0, 'credit' => $invoice->tax_amount, 'created_at' => now(), 'updated_at' => now()];
            }

            DB::table('journal_items')->insert($journalItems);
            $invoice->update(['status' => 'unpaid']);
        });
    }

    /**
     * Void a posted invoice and reverse GL entries.
     */
    public function void(Invoice $invoice): void
    {
        if (in_array($invoice->status, ['void', 'draft'], true)) {
            throw new \LogicException('Invoice cannot be voided from its current status.');
        }

        DB::transaction(function () use ($invoice) {
            $journalId = DB::table('journal_entries')->insertGetId([
                'date'           => now(),
                'description'    => 'VOID REVERSAL: ' . $invoice->invoice_number,
                'reference_type' => 'Invoice',
                'reference_id'   => $invoice->id,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $revenueNet = (float) ($invoice->amount_before_tax - $invoice->discount_total)
                + (float) $invoice->shipping_amount
                + (float) $invoice->rounding_adjustment;

            $accountMap = DB::table('accounts')->whereIn('code', ['1100', '4000', '2100'])->pluck('id', 'code');

            $reversals = [
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap['1100'] ?? null, 'account_code' => '1100', 'debit' => 0, 'credit' => $invoice->total_amount, 'created_at' => now(), 'updated_at' => now()],
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap['4000'] ?? null, 'account_code' => '4000', 'debit' => $revenueNet, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()],
            ];
            if ($invoice->tax_amount > 0) {
                $reversals[] = ['journal_entry_id' => $journalId, 'account_id' => $accountMap['2100'] ?? null, 'account_code' => '2100', 'debit' => $invoice->tax_amount, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()];
            }

            DB::table('journal_items')->insert($reversals);
            $invoice->update(['status' => 'void', 'amount_paid' => 0]);
        });
    }

    /**
     * Record a payment against an invoice.
     */
    public function recordPayment(Invoice $invoice, float $amount, string $paymentDate, string $bankAccountCode): void
    {
        if (in_array($invoice->status, ['draft', 'void'], true)) {
            throw new \LogicException('Cannot record payment for a draft or void invoice.');
        }

        DB::transaction(function () use ($invoice, $amount, $paymentDate, $bankAccountCode) {
            $newAmountPaid = (float) $invoice->amount_paid + $amount;
            $status = ($newAmountPaid >= (float) $invoice->total_amount) ? 'paid' : 'partially paid';

            $invoice->update([
                'amount_paid' => min($newAmountPaid, $invoice->total_amount),
                'status'      => $status,
            ]);

            $journalId = DB::table('journal_entries')->insertGetId([
                'date'           => $paymentDate,
                'description'    => 'Payment for ' . $invoice->invoice_number,
                'reference_type' => 'Invoice Payment',
                'reference_id'   => $invoice->id,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $accountMap = DB::table('accounts')->whereIn('code', [$bankAccountCode, '1100'])->pluck('id', 'code');

            DB::table('journal_items')->insert([
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap[$bankAccountCode] ?? null, 'account_code' => $bankAccountCode, 'debit' => $amount, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap['1100'] ?? null, 'account_code' => '1100', 'debit' => 0, 'credit' => $amount, 'created_at' => now(), 'updated_at' => now()],
            ]);
        });
    }

    public function recalculateStatus(Invoice $invoice): void
    {
        $totalCredits = DB::table('credit_notes')
            ->where('invoice_id', $invoice->id)
            ->whereNull('deleted_at')
            ->sum('total_amount');

        $effectiveBalance = (float)$invoice->total_amount - (float)$invoice->amount_paid - (float)$totalCredits;

        if ($effectiveBalance <= 0) {
            $invoice->status = 'paid';
        } elseif ($invoice->amount_paid > 0 || $totalCredits > 0) {
            $invoice->status = 'partially paid';
        } else {
            $invoice->status = 'unpaid';
        }

        $invoice->save();
    }

    private function syncItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $item) {
            $invoice->items()->create([
                'description'         => $item['description'],
                'quantity'            => $item['quantity'],
                'unit_price'          => $item['unit_price'],
                'tax_rate'            => $item['tax_rate'],
                'item_classification' => $item['item_classification'],
                'discount_amount'     => $item['discount_amount'] ?? 0,
                'amount'              => ($item['quantity'] * $item['unit_price']) - (float) ($item['discount_amount'] ?? 0),
            ]);
        }
    }

    private function syncJournalEntry(Invoice $invoice, array $totals): void
    {
        $journal = DB::table('journal_entries')
            ->where('reference_type', 'Invoice')
            ->where('reference_id', $invoice->id)
            ->latest()
            ->first();

        if (!$journal) {
            return;
        }

        DB::table('journal_items')->where('journal_entry_id', $journal->id)->delete();

        $revenueNet = (float) ($totals['subtotal'] - $totals['discountTotal'])
            + (float) ($invoice->shipping_amount ?? 0)
            + $totals['roundingAdjustment'];

        $accountMap = DB::table('accounts')->whereIn('code', ['1100', '4000', '2100'])->pluck('id', 'code');

        $journalItems = [
            ['journal_entry_id' => $journal->id, 'account_id' => $accountMap['1100'] ?? null, 'account_code' => '1100', 'debit' => $totals['roundedTotal'], 'credit' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $journal->id, 'account_id' => $accountMap['4000'] ?? null, 'account_code' => '4000', 'debit' => 0, 'credit' => $revenueNet, 'created_at' => now(), 'updated_at' => now()],
        ];
        if ($totals['taxTotal'] > 0) {
            $journalItems[] = ['journal_entry_id' => $journal->id, 'account_id' => $accountMap['2100'] ?? null, 'account_code' => '2100', 'debit' => 0, 'credit' => $totals['taxTotal'], 'created_at' => now(), 'updated_at' => now()];
        }

        DB::table('journal_items')->insert($journalItems);
    }
}
