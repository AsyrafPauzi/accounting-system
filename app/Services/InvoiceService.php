<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Support\DocumentNumber;
use App\Support\JournalWriter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
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

    public function nextNumber(): string
    {
        return DocumentNumber::next('invoices', 'invoice_number', 'INV');
    }

    /**
     * Open AR on an invoice after payments and applied credit notes.
     */
    public function remainingBalance(Invoice $invoice): float
    {
        $credits = 0.0;
        if (\Illuminate\Support\Facades\Schema::hasTable('credit_note_applications')) {
            $credits = (float) DB::table('credit_note_applications')
                ->where('invoice_id', $invoice->id)
                ->sum('amount');
        } else {
            $credits = (float) DB::table('credit_notes')
                ->where('invoice_id', $invoice->id)
                ->whereNull('deleted_at')
                ->where('status', '!=', 'void')
                ->sum('total_amount');
        }

        $deposits = 0.0;
        if (\Illuminate\Support\Facades\Schema::hasTable('ar_deposit_applications')) {
            $deposits = (float) DB::table('ar_deposit_applications')
                ->where('invoice_id', $invoice->id)
                ->sum('amount');
        }

        return round(
            (float) $invoice->total_amount - (float) $invoice->amount_paid - $credits - $deposits,
            2
        );
    }

    /**
     * Sum open balances for invoices matching the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation|null  $query
     */
    public function sumOutstanding(
        \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation|null $query = null
    ): float {
        if ($query instanceof \Illuminate\Database\Eloquent\Relations\Relation) {
            $invoices = $query->get();
        } else {
            $query ??= Invoice::query()->whereNotIn('status', ['draft', 'void']);
            $invoices = $query->get();
        }
        $total = 0.0;
        foreach ($invoices as $invoice) {
            $balance = $this->remainingBalance($invoice);
            if ($balance > 0) {
                $total += $balance;
            }
        }

        return round($total, 2);
    }

    /**
     * @return array<int, float> customer_id => outstanding
     */
    public function outstandingByCustomer(?\Illuminate\Database\Eloquent\Builder $query = null): array
    {
        $query ??= Invoice::query()->whereNotIn('status', ['draft', 'void']);
        $map = [];
        foreach ($query->get() as $invoice) {
            $balance = max(0, $this->remainingBalance($invoice));
            if ($balance > 0 && $invoice->customer_id) {
                $map[$invoice->customer_id] = ($map[$invoice->customer_id] ?? 0) + $balance;
            }
        }

        return array_map(fn ($v) => round($v, 2), $map);
    }

    /**
     * @return array<int, int> customer_id => open invoice count
     */
    public function openInvoiceCountByCustomer(?\Illuminate\Database\Eloquent\Builder $query = null): array
    {
        $query ??= Invoice::query()->whereNotIn('status', ['draft', 'void']);
        $map = [];
        foreach ($query->get() as $invoice) {
            if ($this->remainingBalance($invoice) > 0 && $invoice->customer_id) {
                $map[$invoice->customer_id] = ($map[$invoice->customer_id] ?? 0) + 1;
            }
        }

        return $map;
    }

    public static function lateFeeAmount(float $balance, float $percent): float
    {
        return round($balance * $percent / 100, 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openInvoicesForCustomer(int $customerId): array
    {
        return Invoice::query()
            ->where('customer_id', $customerId)
            ->whereNotIn('status', ['draft', 'void', 'paid'])
            ->orderBy('due_date')
            ->get()
            ->map(fn (Invoice $i) => [
                'id'             => $i->id,
                'invoice_number' => $i->invoice_number,
                'issue_date'     => optional($i->issue_date)->toDateString(),
                'due_date'       => optional($i->due_date)->toDateString(),
                'total_amount'   => (float) $i->total_amount,
                'balance'        => $this->remainingBalance($i),
                'currency'       => $i->currency ?? 'MYR',
                'status'         => $i->status,
            ])
            ->filter(fn ($row) => $row['balance'] > 0)
            ->values()
            ->all();
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
            $items = $this->hydrateItemAccounts($items);

            $invoice = Invoice::create([
                'invoice_number'      => $data['invoice_number'],
                'msic_code'           => $data['msic_code'],
                'customer_id'         => $data['customer_id'],
                'issue_date'          => $data['issue_date'],
                'due_date'            => $data['due_date'] ?? null,
                'currency'            => $currency,
                'exchange_rate'       => $exchangeRate,
                'amount_before_tax'   => $totals['subtotal'],
                'discount_total'      => $totals['discountTotal'],
                'tax_amount'          => $totals['taxTotal'],
                'shipping_amount'     => $data['shipping_amount'] ?? 0,
                'rounding_adjustment' => $totals['roundingAdjustment'],
                'total_amount'        => $totals['roundedTotal'],
                'customer_notes'      => $data['customer_notes'] ?? null,
                'show_signature'      => filter_var($data['show_signature'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'status'              => 'draft',
                'lhdn_status'         => 'pending',
                'created_by'          => $data['created_by'] ?? null,
                'is_cash_sale'        => (bool) ($data['is_cash_sale'] ?? false),
                'payment_terms_days'  => $data['payment_terms_days'] ?? null,
                'sales_order_id'      => $data['sales_order_id'] ?? null,
                'delivery_order_id'   => $data['delivery_order_id'] ?? null,
                'estimate_id'         => $data['estimate_id'] ?? null,
                'source_invoice_id'   => $data['source_invoice_id'] ?? null,
                'is_late_fee'         => (bool) ($data['is_late_fee'] ?? false),
                'reminder_overrides'  => $data['reminder_overrides'] ?? null,
            ]);

            $this->syncItems($invoice, $items);

            return $invoice;
        });
    }

    /**
     * Duplicate into a new draft: same customer/lines/tax/notes/currency;
     * new number; dates = today / payment terms; no payments or LHDN ids.
     */
    public function duplicate(Invoice $source, ?int $createdBy = null): Invoice
    {
        $source->loadMissing('items');
        $header = CreditNoteService::duplicateSafeInvoiceFields($source);
        $today = now()->toDateString();
        $issue = \Carbon\Carbon::parse($source->issue_date);
        $due = $source->due_date ? \Carbon\Carbon::parse($source->due_date) : $issue->copy()->addDays(30);
        $terms = (int) ($source->payment_terms_days ?: max(0, $issue->diffInDays($due)));
        if ($terms <= 0) {
            $terms = 30;
        }

        $items = $source->items->map(fn ($i) => [
            'product_id'          => $i->product_id,
            'account_code'        => $i->account_code,
            'description'         => $i->description,
            'quantity'            => (float) $i->quantity,
            'unit_price'          => (float) $i->unit_price,
            'tax_rate'            => (float) $i->tax_rate,
            'discount_amount'     => (float) ($i->discount_amount ?? 0),
            'item_classification' => $i->item_classification ?: '022',
        ])->all();

        return $this->create(array_merge($header, [
            'invoice_number'     => $this->nextNumber(),
            'issue_date'         => $today,
            'due_date'           => now()->addDays($terms)->toDateString(),
            'payment_terms_days' => $terms,
            'created_by'         => $createdBy,
            'source_invoice_id'  => $source->id,
        ]), $items);
    }

    /**
     * Invoice + full receipt in one save (AutoCount cash sale).
     */
    public function cashSale(array $data, array $items, string $bankAccountCode, string $paymentDate): Invoice
    {
        return DB::transaction(function () use ($data, $items, $bankAccountCode, $paymentDate) {
            $data['is_cash_sale'] = true;
            $invoice = $this->create($data, $items);
            $this->post($invoice->fresh('items'));
            $this->recordPayment($invoice->fresh(), (float) $invoice->total_amount, $paymentDate, $bankAccountCode);

            return $invoice->fresh(['items', 'customer']);
        });
    }

    /**
     * Draft interest invoice on overdue outstanding AR.
     */
    public function issueLateFee(Invoice $invoice, ?float $percent = null, ?int $createdBy = null): Invoice
    {
        if (in_array($invoice->status, ['draft', 'void', 'paid'], true)) {
            throw new \LogicException('Late fees only apply to unpaid posted invoices.');
        }
        if (! $invoice->due_date || $invoice->due_date->copy()->startOfDay()->gte(now()->startOfDay())) {
            throw new \LogicException('Invoice is not overdue.');
        }

        $percent ??= (float) (function_exists('tenant') && tenant() ? (tenant()->late_fee_percent ?? 1.5) : 1.5);
        if ($percent <= 0) {
            throw new \LogicException('Set a late fee percent under Settings → Company.');
        }

        $balance = $this->remainingBalance($invoice);
        $fee = self::lateFeeAmount($balance, $percent);
        if ($fee < 0.01) {
            throw new \LogicException('Late fee would be zero.');
        }

        return $this->create([
            'invoice_number'     => $this->nextNumber(),
            'msic_code'          => $invoice->msic_code ?: '00000',
            'customer_id'        => $invoice->customer_id,
            'issue_date'         => now()->toDateString(),
            'due_date'           => now()->toDateString(),
            'currency'           => $invoice->currency ?? 'MYR',
            'exchange_rate'      => $invoice->exchange_rate ?? 1,
            'shipping_amount'    => 0,
            'customer_notes'     => 'Late interest '.$percent.'% on '.$invoice->invoice_number,
            'show_signature'     => $invoice->show_signature ?? true,
            'created_by'         => $createdBy,
            'source_invoice_id'  => $invoice->id,
            'is_late_fee'        => true,
            'payment_terms_days' => 0,
        ], [[
            'description'         => 'Late interest '.$percent.'% on outstanding '.$invoice->invoice_number,
            'quantity'            => 1,
            'unit_price'          => $fee,
            'tax_rate'            => 0,
            'discount_amount'     => 0,
            'item_classification' => '022',
        ]]);
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
            $items = $this->hydrateItemAccounts($items);

            $invoice->update([
                'invoice_number'      => $data['invoice_number'],
                'customer_id'         => $data['customer_id'],
                'msic_code'           => $data['msic_code'],
                'issue_date'          => $data['issue_date'],
                'due_date'            => $data['due_date'] ?? null,
                'currency'            => $currency,
                'exchange_rate'       => $exchangeRate,
                'amount_before_tax'   => $totals['subtotal'],
                'discount_total'      => $totals['discountTotal'],
                'tax_amount'          => $totals['taxTotal'],
                'shipping_amount'     => $data['shipping_amount'] ?? 0,
                'rounding_adjustment' => $totals['roundingAdjustment'],
                'total_amount'        => $totals['roundedTotal'],
                'customer_notes'      => $data['customer_notes'] ?? null,
                'show_signature'      => filter_var($data['show_signature'] ?? $invoice->show_signature ?? true, FILTER_VALIDATE_BOOLEAN),
                'payment_terms_days'  => $data['payment_terms_days'] ?? $invoice->payment_terms_days,
                'reminder_overrides'  => $data['reminder_overrides'] ?? $invoice->reminder_overrides,
            ]);

            $invoice->items()->delete();
            $this->syncItems($invoice, $items);

            if ($invoice->status !== 'draft') {
                $this->syncJournalEntry($invoice->fresh('items'), $totals);
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
                        'Posting would exceed customer credit limit (RM '.number_format($creditLimit, 2).'). Current exposure: RM '.number_format($balance, 2).'.'
                    );
                }
            }
        }

        DB::transaction(function () use ($invoice) {
            $invoice->loadMissing('items');
            \App\Support\AccountingPeriodResolver::assertOpenForDate(
                Carbon::parse($invoice->issue_date)->toDateString()
            );
            JournalWriter::postSystem([
                'date'           => Carbon::parse($invoice->issue_date)->toDateString(),
                'description'    => 'Posted Sales Invoice: '.$invoice->invoice_number,
                'reference_type' => 'Invoice',
                'reference_id'   => $invoice->id,
            ], $this->buildPostingLineRows($invoice));
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
            $this->reverseLatestJournal($invoice, 'Invoice', 'VOID REVERSAL: '.$invoice->invoice_number);
            $invoice->update(['status' => 'void', 'amount_paid' => 0]);
        });
    }

    /**
     * Record a payment against an invoice.
     */
    public function recordPayment(Invoice $invoice, float $amount, string $paymentDate, string $bankAccountCode, ?string $reference = null, ?int $createdBy = null): InvoicePayment
    {
        if (in_array($invoice->status, ['draft', 'void'], true)) {
            throw new \LogicException('Cannot record payment for a draft or void invoice.');
        }

        return DB::transaction(function () use ($invoice, $amount, $paymentDate, $bankAccountCode, $reference, $createdBy) {
            \App\Support\AccountingPeriodResolver::assertOpenForDate($paymentDate);
            $newAmountPaid = (float) $invoice->amount_paid + $amount;
            $status = ($newAmountPaid >= (float) $invoice->total_amount) ? 'paid' : 'partially paid';

            $invoice->update([
                'amount_paid' => min($newAmountPaid, $invoice->total_amount),
                'status'      => $status,
            ]);

            $payment = InvoicePayment::create([
                'invoice_id'        => $invoice->id,
                'amount'            => $amount,
                'payment_date'      => $paymentDate,
                'bank_account_code' => $bankAccountCode,
                'reference'         => $reference,
                'created_by'        => $createdBy,
            ]);

            $m = $this->ledgerBaseMultiplier($invoice);
            $amountBase = $amount * $m;

            JournalWriter::postSystem([
                'date'           => $paymentDate,
                'description'    => 'Payment for '.$invoice->invoice_number,
                'reference_type' => 'Invoice Payment',
                'reference_id'   => $payment->id,
            ], [
                ['account_code' => $bankAccountCode, 'debit' => $amountBase, 'credit' => 0],
                ['account_code' => '1100', 'debit' => 0, 'credit' => $amountBase],
            ]);

            $this->recalculateStatus($invoice->fresh());

            return $payment;
        });
    }

    public function reversePayment(InvoicePayment $payment, ?int $reversedBy = null): void
    {
        if ($payment->reversed_at) {
            throw new \LogicException('This payment is already reversed.');
        }

        DB::transaction(function () use ($payment, $reversedBy) {
            $invoice = Invoice::query()->findOrFail($payment->invoice_id);
            if (! $this->reverseLatestJournalByReference('Invoice Payment', (int) $payment->id, 'REVERSE PAYMENT: '.$invoice->invoice_number)) {
                $legacy = DB::table('journal_entries as j')
                    ->join('journal_items as i', 'i.journal_entry_id', '=', 'j.id')
                    ->where('j.reference_type', 'Invoice Payment')
                    ->where('j.reference_id', $invoice->id)
                    ->where('i.account_code', '1100')
                    ->where('i.credit', $payment->amount)
                    ->orderByDesc('j.id')
                    ->value('j.id');
                if ($legacy) {
                    $this->reverseJournalId((int) $legacy, 'Invoice Payment', (int) $invoice->id, 'REVERSE PAYMENT: '.$invoice->invoice_number);
                }
            }

            $payment->forceFill([
                'reversed_at' => now(),
                'reversed_by' => $reversedBy,
            ])->save();

            $invoice->amount_paid = max(0, round((float) $invoice->amount_paid - (float) $payment->amount, 2));
            $invoice->save();
            $this->recalculateStatus($invoice->fresh());
        });
    }

    public function recalculateStatus(Invoice $invoice): void
    {
        if (in_array($invoice->status, ['draft', 'void'], true)) {
            return;
        }

        $balance = $this->remainingBalance($invoice);

        if ($balance <= 0) {
            $invoice->status = 'paid';
        } elseif ((float) $invoice->amount_paid > 0 || $balance < (float) $invoice->total_amount) {
            $invoice->status = 'partially paid';
        } else {
            $invoice->status = 'unpaid';
        }

        $invoice->save();
    }

    public function markViewed(Invoice $invoice): void
    {
        $invoice->forceFill([
            'last_viewed_at' => now(),
            'view_count'     => (int) $invoice->view_count + 1,
        ])->save();
    }

    private function syncItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $item) {
            $tax = \App\Support\TaxCodeResolver::normalizeLineItem($item);
            $invoice->items()->create([
                'product_id'          => $item['product_id'] ?? null,
                'account_code'        => $item['account_code'] ?? null,
                'description'         => $item['description'],
                'quantity'            => $item['quantity'],
                'unit_price'          => $item['unit_price'],
                'tax_code_id'         => $tax['tax_code_id'],
                'tax_rate'            => $tax['tax_rate'],
                'item_classification' => $item['item_classification'],
                'discount_amount'     => $item['discount_amount'] ?? 0,
                'amount'              => ($item['quantity'] * $item['unit_price']) - (float) ($item['discount_amount'] ?? 0),
            ]);
        }
    }

    /**
     * Copy product.account_code onto lines that picked a catalogue product.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function hydrateItemAccounts(array $items): array
    {
        $ids = collect($items)->pluck('product_id')->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return $items;
        }
        $codes = Product::query()->whereIn('id', $ids)->pluck('account_code', 'id');
        foreach ($items as &$item) {
            if (! empty($item['product_id']) && empty($item['account_code'])) {
                $item['account_code'] = $codes[$item['product_id']] ?? null;
            }
        }

        return $items;
    }

    /**
     * @return list<array{account_code: string, debit: float, credit: float, account_id?: ?int}>
     */
    private function buildPostingLineRows(Invoice $invoice): array
    {
        $m = $this->ledgerBaseMultiplier($invoice);
        $codes = ['1100', '4000', '2100'];
        foreach ($invoice->items as $item) {
            if ($item->account_code) {
                $codes[] = $item->account_code;
            }
        }
        $accountMap = DB::table('accounts')->whereIn('code', array_unique($codes))->pluck('id', 'code');

        $totalBase = (float) $invoice->total_amount * $m;
        $taxBase = (float) $invoice->tax_amount * $m;

        $rows = [
            [
                'account_code' => '1100',
                'account_id'   => $accountMap['1100'] ?? null,
                'debit'        => $totalBase,
                'credit'       => 0,
            ],
        ];

        $byCode = [];
        foreach ($invoice->items as $item) {
            $code = $item->account_code ?: '4000';
            $net = ((float) $item->quantity * (float) $item->unit_price) - (float) ($item->discount_amount ?? 0);
            $byCode[$code] = ($byCode[$code] ?? 0) + $net * $m;
        }
        $shippingRounding = ((float) $invoice->shipping_amount + (float) $invoice->rounding_adjustment) * $m;
        $byCode['4000'] = ($byCode['4000'] ?? 0) + $shippingRounding;

        foreach ($byCode as $code => $credit) {
            if (round($credit, 2) == 0.0) {
                continue;
            }
            $rows[] = [
                'account_code' => $code,
                'account_id'   => $accountMap[$code] ?? $accountMap['4000'] ?? null,
                'debit'        => 0,
                'credit'       => $credit,
            ];
        }

        if ($taxBase > 0) {
            $rows[] = [
                'account_code' => '2100',
                'account_id'   => $accountMap['2100'] ?? null,
                'debit'        => 0,
                'credit'       => $taxBase,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildPostingLines(Invoice $invoice, int $journalId): array
    {
        $now = now();

        return array_map(static fn (array $line): array => [
            'journal_entry_id' => $journalId,
            'account_id'       => $line['account_id'] ?? null,
            'account_code'     => $line['account_code'],
            'debit'            => $line['debit'],
            'credit'           => $line['credit'],
            'created_at'       => $now,
            'updated_at'       => $now,
        ], $this->buildPostingLineRows($invoice));
    }

    private function syncJournalEntry(Invoice $invoice, array $totals): void
    {
        $journal = DB::table('journal_entries')
            ->where('reference_type', 'Invoice')
            ->where('reference_id', $invoice->id)
            ->latest()
            ->first();

        if (! $journal) {
            return;
        }

        DB::table('journal_items')->where('journal_entry_id', $journal->id)->delete();
        DB::table('journal_items')->insert($this->buildPostingLines($invoice, (int) $journal->id));
    }

    private function reverseLatestJournal(Invoice $invoice, string $referenceType, string $description): void
    {
        $this->reverseLatestJournalByReference($referenceType, (int) $invoice->id, $description);
    }

    private function reverseLatestJournalByReference(string $referenceType, int $referenceId, string $description): bool
    {
        $journal = DB::table('journal_entries')
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->latest('id')
            ->first();

        if (! $journal) {
            return false;
        }

        return $this->reverseJournalId((int) $journal->id, $referenceType, $referenceId, $description);
    }

    private function reverseJournalId(int $journalId, string $referenceType, int $referenceId, string $description): bool
    {
        try {
            JournalWriter::postReversalFromJournal(
                $journalId,
                $description,
                now()->toDateString(),
                $referenceType,
                $referenceId,
            );

            return true;
        } catch (\LogicException) {
            return false;
        }
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
