<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function __construct(
        protected InvoicePdfStorageService $invoicePdfStorage,
    ) {}
    /**
     * Smallest currency unit for invoice total rounding.
     *   MYR — 5 sen (0.05)
     *   JPY — 1 yen (no fractional unit in everyday use)
     *   others (e.g. USD) — 1 cent (0.01)
     */
    public static function roundingStep(string $currency): float
    {
        return match (strtoupper($currency)) {
            'MYR' => 0.05,
            'JPY' => 1.0,
            default => 0.01,
        };
    }

    /**
     * Multiplier to express invoice amounts in the tenant's base currency for the GL.
     * When invoice currency matches base, returns 1. Otherwise uses stored exchange_rate
     * as "base currency per 1 unit of invoice currency" (e.g. MYR per 1 USD).
     */
    public function ledgerBaseMultiplier(Invoice $invoice): float
    {
        $invoiceCurrency = strtoupper((string) ($invoice->currency ?: 'MYR'));
        $base = $this->tenantBaseCurrency();
        if ($invoiceCurrency === $base) {
            return 1.0;
        }
        $rate = (float) ($invoice->exchange_rate ?? 0);

        return $rate > 0 ? $rate : 1.0;
    }

    private function tenantBaseCurrency(): string
    {
        if (function_exists('tenant') && tenant()) {
            return strtoupper((string) (tenant()->base_currency ?? 'MYR'));
        }

        return 'MYR';
    }

    /**
     * Compute totals from a line-item array.
     */
    public function computeTotals(array $items, float $shipping = 0.0, string $currency = 'MYR'): array
    {
        $subtotal = collect($items)->sum(fn ($i) => $i['quantity'] * $i['unit_price']);
        $discountTotal = collect($items)->sum(fn ($i) => (float) ($i['discount_amount'] ?? 0));
        $taxTotal = collect($items)->sum(function ($i) {
            $itemAmount = ($i['quantity'] * $i['unit_price']) - (float) ($i['discount_amount'] ?? 0);
            return ($itemAmount * $i['tax_rate']) / 100;
        });

        $rawTotal = ($subtotal - $discountTotal) + $taxTotal + $shipping;
        $step = self::roundingStep($currency);
        $roundedTotal = round($rawTotal / $step) * $step;
        $roundingAdjustment = $roundedTotal - $rawTotal;

        return compact('subtotal', 'discountTotal', 'taxTotal', 'roundedTotal', 'roundingAdjustment');
    }

    /**
     * Create a draft invoice with line items inside a transaction.
     */
    public function create(array $data, array $items): Invoice
    {
        return DB::transaction(function () use ($data, $items) {
            $currency = strtoupper((string) ($data['currency'] ?? 'MYR'));
            $totals = $this->computeTotals($items, (float) ($data['shipping_amount'] ?? 0), $currency);
            $exchangeRate = $this->normalizedExchangeRate($currency, $data['exchange_rate'] ?? null);

            $invoice = Invoice::create([
                'invoice_number'     => $data['invoice_number'],
                'msic_code'          => $data['msic_code'],
                'customer_id'        => $data['customer_id'],
                'issue_date'         => $data['issue_date'],
                'due_date'           => $data['due_date'] ?? null,
                'currency'           => $currency,
                'exchange_rate'      => $exchangeRate,
                'amount_before_tax'  => $totals['subtotal'],
                'discount_total'     => $totals['discountTotal'],
                'tax_amount'         => $totals['taxTotal'],
                'shipping_amount'    => $data['shipping_amount'] ?? 0,
                'rounding_adjustment'=> $totals['roundingAdjustment'],
                'total_amount'       => $totals['roundedTotal'],
                'customer_notes'     => $data['customer_notes'] ?? null,
                'show_signature'     => filter_var($data['show_signature'] ?? true, FILTER_VALIDATE_BOOLEAN),
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
            $currency = strtoupper((string) ($data['currency'] ?? $invoice->currency ?? 'MYR'));
            $totals = $this->computeTotals($items, (float) ($data['shipping_amount'] ?? 0), $currency);
            $exchangeRate = $this->normalizedExchangeRate($currency, $data['exchange_rate'] ?? $invoice->exchange_rate);

            $invoice->update([
                'invoice_number'     => $data['invoice_number'],
                'customer_id'        => $data['customer_id'],
                'msic_code'          => $data['msic_code'],
                'issue_date'         => $data['issue_date'],
                'due_date'           => $data['due_date'] ?? null,
                'currency'           => $currency,
                'exchange_rate'      => $exchangeRate,
                'amount_before_tax'  => $totals['subtotal'],
                'discount_total'     => $totals['discountTotal'],
                'tax_amount'         => $totals['taxTotal'],
                'shipping_amount'    => $data['shipping_amount'] ?? 0,
                'rounding_adjustment'=> $totals['roundingAdjustment'],
                'total_amount'       => $totals['roundedTotal'],
                'customer_notes'     => $data['customer_notes'] ?? null,
                'show_signature'     => filter_var($data['show_signature'] ?? $invoice->show_signature ?? true, FILTER_VALIDATE_BOOLEAN),
            ]);

            $invoice->items()->delete();
            $this->syncItems($invoice, $items);

            if ($invoice->status !== 'draft') {
                $this->syncJournalEntry($invoice, $totals);
            }

            $this->invoicePdfStorage->forget($invoice);
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

            $m = $this->ledgerBaseMultiplier($invoice);

            $revenueNet = ((float) ($invoice->amount_before_tax - $invoice->discount_total)
                + (float) $invoice->shipping_amount
                + (float) $invoice->rounding_adjustment) * $m;

            $accountMap = DB::table('accounts')->whereIn('code', ['1100', '4000', '2100'])->pluck('id', 'code');

            $totalBase = (float) $invoice->total_amount * $m;
            $taxBase = (float) $invoice->tax_amount * $m;

            $journalItems = [
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap['1100'] ?? null, 'account_code' => '1100', 'debit' => $totalBase, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap['4000'] ?? null, 'account_code' => '4000', 'debit' => 0, 'credit' => $revenueNet, 'created_at' => now(), 'updated_at' => now()],
            ];
            if ($invoice->tax_amount > 0) {
                $journalItems[] = ['journal_entry_id' => $journalId, 'account_id' => $accountMap['2100'] ?? null, 'account_code' => '2100', 'debit' => 0, 'credit' => $taxBase, 'created_at' => now(), 'updated_at' => now()];
            }

            DB::table('journal_items')->insert($journalItems);
            $invoice->update(['status' => 'unpaid']);
            $this->invoicePdfStorage->forget($invoice);
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

            $m = $this->ledgerBaseMultiplier($invoice);

            $revenueNet = ((float) ($invoice->amount_before_tax - $invoice->discount_total)
                + (float) $invoice->shipping_amount
                + (float) $invoice->rounding_adjustment) * $m;

            $accountMap = DB::table('accounts')->whereIn('code', ['1100', '4000', '2100'])->pluck('id', 'code');

            $totalBase = (float) $invoice->total_amount * $m;
            $taxBase = (float) $invoice->tax_amount * $m;

            $reversals = [
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap['1100'] ?? null, 'account_code' => '1100', 'debit' => 0, 'credit' => $totalBase, 'created_at' => now(), 'updated_at' => now()],
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap['4000'] ?? null, 'account_code' => '4000', 'debit' => $revenueNet, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()],
            ];
            if ($invoice->tax_amount > 0) {
                $reversals[] = ['journal_entry_id' => $journalId, 'account_id' => $accountMap['2100'] ?? null, 'account_code' => '2100', 'debit' => $taxBase, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()];
            }

            DB::table('journal_items')->insert($reversals);
            $invoice->update(['status' => 'void', 'amount_paid' => 0]);
            $this->invoicePdfStorage->forget($invoice);
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

            $m = $this->ledgerBaseMultiplier($invoice);
            $amountBase = $amount * $m;

            DB::table('journal_items')->insert([
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap[$bankAccountCode] ?? null, 'account_code' => $bankAccountCode, 'debit' => $amountBase, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap['1100'] ?? null, 'account_code' => '1100', 'debit' => 0, 'credit' => $amountBase, 'created_at' => now(), 'updated_at' => now()],
            ]);

            $this->invoicePdfStorage->forget($invoice);
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

        $m = $this->ledgerBaseMultiplier($invoice);

        $revenueNet = ((float) ($totals['subtotal'] - $totals['discountTotal'])
            + (float) ($invoice->shipping_amount ?? 0)
            + $totals['roundingAdjustment']) * $m;

        $accountMap = DB::table('accounts')->whereIn('code', ['1100', '4000', '2100'])->pluck('id', 'code');

        $totalBase = $totals['roundedTotal'] * $m;
        $taxBase = $totals['taxTotal'] * $m;

        $journalItems = [
            ['journal_entry_id' => $journal->id, 'account_id' => $accountMap['1100'] ?? null, 'account_code' => '1100', 'debit' => $totalBase, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['journal_entry_id' => $journal->id, 'account_id' => $accountMap['4000'] ?? null, 'account_code' => '4000', 'debit' => 0, 'credit' => $revenueNet, 'created_at' => now(), 'updated_at' => now()],
        ];
        if ($totals['taxTotal'] > 0) {
            $journalItems[] = ['journal_entry_id' => $journal->id, 'account_id' => $accountMap['2100'] ?? null, 'account_code' => '2100', 'debit' => 0, 'credit' => $taxBase, 'created_at' => now(), 'updated_at' => now()];
        }

        DB::table('journal_items')->insert($journalItems);
    }

    private function normalizedExchangeRate(string $currency, mixed $rate): float
    {
        $currency = strtoupper($currency);
        if ($currency === $this->tenantBaseCurrency()) {
            return 1.0;
        }
        $r = (float) $rate;

        return $r > 0 ? $r : 1.0;
    }
}
