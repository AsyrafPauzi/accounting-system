<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\CreditNoteApplication;
use App\Models\CreditNoteRefund;
use App\Models\Invoice;
use App\Support\DocumentNumber;
use App\Support\JournalWriter;
use App\Support\TaxCodeResolver;
use Illuminate\Support\Facades\DB;

class CreditNoteService
{
    public function __construct(private InvoiceService $invoices) {}

    /**
     * Net / SST / gross from credit-note lines (no shipping or rounding).
     *
     * @param  list<array<string, mixed>>  $items
     * @return array{net: float, tax: float, total: float}
     */
    public static function computeLineTotals(array $items): array
    {
        $net = 0.0;
        $tax = 0.0;
        foreach ($items as $item) {
            $line = ((float) $item['quantity'] * (float) $item['unit_price'])
                - (float) ($item['discount_amount'] ?? 0);
            $net += $line;
            $rate = ! empty($item['tax_code_id'])
                ? TaxCodeResolver::normalizeLineItem($item)['tax_rate']
                : (float) ($item['tax_rate'] ?? 0);
            $tax += ($line * $rate) / 100;
        }

        return [
            'net'   => round($net, 2),
            'tax'   => round($tax, 2),
            'total' => round($net + $tax, 2),
        ];
    }

    public static function unappliedAmount(float $total, float $applied, float $refunded = 0): float
    {
        return round($total - $applied - $refunded, 2);
    }

    /**
     * Fields safe to copy when duplicating an invoice. Payments and LHDN
     * identifiers stay on the original document.
     *
     * @return array<string, mixed>
     */
    public static function duplicateSafeInvoiceFields(object $invoice): array
    {
        return [
            'customer_id'     => $invoice->customer_id,
            'msic_code'       => $invoice->msic_code ?? '00000',
            'currency'        => $invoice->currency ?? 'MYR',
            'exchange_rate'   => (float) ($invoice->exchange_rate ?? 1),
            'shipping_amount' => (float) ($invoice->shipping_amount ?? 0),
            'customer_notes'  => $invoice->customer_notes ?? null,
            'show_signature'  => (bool) ($invoice->show_signature ?? true),
        ];
    }

    public function nextNumber(): string
    {
        return DocumentNumber::next('credit_notes', 'cn_number', 'CN');
    }

    /**
     * Issue a posted credit note. When `invoice_id` is set, the note is
     * applied against that invoice (capped at remaining AR). Leftover
     * stays as unapplied customer credit.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function issue(array $data, array $items): CreditNote
    {
        return DB::transaction(function () use ($data, $items) {
            $totals = self::computeLineTotals($items);
            $currency = strtoupper((string) ($data['currency'] ?? 'MYR'));

            $cn = CreditNote::create([
                'invoice_id'          => $data['invoice_id'] ?? null,
                'customer_id'         => $data['customer_id'],
                'cn_number'           => $data['cn_number'] ?? $this->nextNumber(),
                'issue_date'          => $data['issue_date'] ?? now()->toDateString(),
                'reason_code'         => $data['reason_code'],
                'reason_description'  => $data['reason_description'] ?? null,
                'amount_before_tax'   => $totals['net'],
                'tax_amount'          => $totals['tax'],
                'total_amount'        => $totals['total'],
                'applied_amount'      => 0,
                'currency'            => $currency,
                'exchange_rate'       => (float) ($data['exchange_rate'] ?? 1),
                'customer_notes'      => $data['customer_notes'] ?? null,
                'status'              => 'posted',
                'lhdn_status'         => 'pending',
            ]);

            $this->syncItems($cn, $items);
            $this->postJournal($cn);

            if (! empty($data['invoice_id'])) {
                $invoice = Invoice::findOrFail($data['invoice_id']);
                $this->applyToInvoice($cn, $invoice, $totals['total']);
            }

            return $cn->load('items');
        });
    }

    public function applyToInvoice(CreditNote $cn, Invoice $invoice, float $amount): void
    {
        if ($cn->status === 'void') {
            throw new \LogicException('Cannot apply a voided credit note.');
        }
        if ((int) $invoice->customer_id !== (int) $cn->customer_id) {
            throw new \LogicException('Credit note and invoice belong to different customers.');
        }
        if (in_array($invoice->status, ['draft', 'void'], true)) {
            throw new \LogicException('Cannot apply credit to a draft or void invoice.');
        }

        $open = $cn->openAmount();
        $remaining = $this->invoices->remainingBalance($invoice);
        $apply = round(min($amount, $open, $remaining), 2);
        if ($apply <= 0) {
            throw new \LogicException('Nothing left to apply.');
        }

        CreditNoteApplication::create([
            'credit_note_id' => $cn->id,
            'invoice_id'     => $invoice->id,
            'amount'         => $apply,
        ]);

        $cn->applied_amount = round((float) $cn->applied_amount + $apply, 2);
        $cn->save();

        $this->invoices->recalculateStatus($invoice->fresh());
    }

    public function void(CreditNote $cn): void
    {
        if ($cn->status === 'void') {
            throw new \LogicException('Credit note is already voided.');
        }

        DB::transaction(function () use ($cn) {
            \App\Support\AccountingPeriodResolver::assertOpenForDate(
                \Carbon\Carbon::parse($cn->issue_date)->toDateString()
            );
            $this->reverseRefunds($cn);
            $this->reverseJournal($cn);

            $invoiceIds = CreditNoteApplication::query()
                ->where('credit_note_id', $cn->id)
                ->pluck('invoice_id')
                ->unique()
                ->filter();

            CreditNoteApplication::query()->where('credit_note_id', $cn->id)->delete();

            $cn->update([
                'status'          => 'void',
                'applied_amount'  => 0,
                'refunded_amount' => 0,
            ]);

            foreach ($invoiceIds as $invoiceId) {
                $invoice = Invoice::find($invoiceId);
                if ($invoice) {
                    $this->invoices->recalculateStatus($invoice);
                }
            }
        });
    }

    public function assertEditable(CreditNote $cn): void
    {
        if ($cn->status === 'void') {
            throw new \LogicException('Cannot edit a voided credit note.');
        }
        if (filled($cn->lhdn_uuid) && in_array($cn->lhdn_status, ['submitted', 'valid'], true)) {
            throw new \LogicException('Cannot edit a credit note locked by MyInvois.');
        }
    }

    /**
     * Update header/lines when unapplied. Notes-only always allowed when editable.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>|null  $items
     */
    public function update(CreditNote $cn, array $data, ?array $items = null): CreditNote
    {
        $this->assertEditable($cn);

        return DB::transaction(function () use ($cn, $data, $items) {
            $notesOnly = $items === null;
            if (! $notesOnly) {
                if ((float) $cn->applied_amount > 0 || (float) ($cn->refunded_amount ?? 0) > 0) {
                    throw new \LogicException('Cannot change lines after credit has been applied or refunded.');
                }
                $this->reverseJournal($cn);
                $cn->items()->delete();
                $totals = self::computeLineTotals($items);
                $cn->update([
                    'issue_date'         => $data['issue_date'] ?? $cn->issue_date,
                    'reason_code'        => $data['reason_code'] ?? $cn->reason_code,
                    'reason_description' => array_key_exists('reason_description', $data) ? $data['reason_description'] : $cn->reason_description,
                    'customer_notes'     => array_key_exists('customer_notes', $data) ? $data['customer_notes'] : $cn->customer_notes,
                    'amount_before_tax'  => $totals['net'],
                    'tax_amount'         => $totals['tax'],
                    'total_amount'       => $totals['total'],
                ]);
                $this->syncItems($cn, $items);
                $this->postJournal($cn->fresh('items'));
            } else {
                $cn->update([
                    'issue_date'         => $data['issue_date'] ?? $cn->issue_date,
                    'reason_code'        => $data['reason_code'] ?? $cn->reason_code,
                    'reason_description' => array_key_exists('reason_description', $data) ? $data['reason_description'] : $cn->reason_description,
                    'customer_notes'     => array_key_exists('customer_notes', $data) ? $data['customer_notes'] : $cn->customer_notes,
                ]);
            }

            return $cn->fresh('items');
        });
    }

    /**
     * Cash refund of unapplied credit. CN already credited AR (1100);
     * paying cash out is Dr 1100, Cr Bank.
     */
    public function refund(
        CreditNote $cn,
        float $amount,
        string $bankAccountCode,
        string $paymentDate,
        ?string $reference = null,
        ?int $createdBy = null
    ): CreditNoteRefund {
        if ($cn->status === 'void') {
            throw new \LogicException('Cannot refund a voided credit note.');
        }

        $amount = round(min($amount, $cn->openAmount()), 2);
        if ($amount <= 0) {
            throw new \LogicException('No unapplied credit left to refund.');
        }

        return DB::transaction(function () use ($cn, $amount, $bankAccountCode, $paymentDate, $reference, $createdBy) {
            $refund = CreditNoteRefund::create([
                'credit_note_id'    => $cn->id,
                'amount'            => $amount,
                'payment_date'      => $paymentDate,
                'bank_account_code' => $bankAccountCode,
                'reference'         => $reference,
                'created_by'        => $createdBy,
            ]);

            $cn->refunded_amount = round((float) ($cn->refunded_amount ?? 0) + $amount, 2);
            $cn->save();

            $base = round($amount * $this->ledgerMultiplier($cn), 2);

            JournalWriter::postSystem([
                'date'           => $paymentDate,
                'description'    => 'Refund of credit note '.$cn->cn_number,
                'reference_type' => 'Credit Note Refund',
                'reference_id'   => $refund->id,
            ], [
                ['account_code' => '1100', 'debit' => $base, 'credit' => 0],
                ['account_code' => $bankAccountCode, 'debit' => 0, 'credit' => $base],
            ]);

            return $refund;
        });
    }

    private function reverseRefunds(CreditNote $cn): void
    {
        $refunds = CreditNoteRefund::query()->where('credit_note_id', $cn->id)->get();
        foreach ($refunds as $refund) {
            $journal = DB::table('journal_entries')
                ->where('reference_type', 'Credit Note Refund')
                ->where('reference_id', $refund->id)
                ->latest('id')
                ->first();
            if (! $journal) {
                continue;
            }
            try {
                JournalWriter::postReversalFromJournal(
                    (int) $journal->id,
                    'VOID REFUND REVERSAL: '.$cn->cn_number,
                    now()->toDateString(),
                    'Credit Note Refund',
                    $refund->id,
                );
            } catch (\LogicException) {
                continue;
            }
            $refund->delete();
        }
    }

    private function syncItems(CreditNote $cn, array $items): void
    {
        foreach (array_values($items) as $item) {
            $tax = TaxCodeResolver::normalizeLineItem($item);
            $line = ((float) $item['quantity'] * (float) $item['unit_price'])
                - (float) ($item['discount_amount'] ?? 0);
            $payload = [
                'product_id'          => $item['product_id'] ?? null,
                'account_code'        => $item['account_code'] ?? null,
                'description'         => $item['description'],
                'quantity'            => $item['quantity'],
                'unit_price'          => $item['unit_price'],
                'tax_rate'            => $tax['tax_rate'],
                'discount_amount'     => $item['discount_amount'] ?? 0,
                'item_classification' => $item['item_classification'] ?? null,
                'amount'              => $line,
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('credit_note_items', 'tax_code_id')) {
                $payload['tax_code_id'] = $tax['tax_code_id'];
            }
            $cn->items()->create($payload);
        }
    }

    private function postJournal(CreditNote $cn): void
    {
        $m = $this->ledgerMultiplier($cn);
        $netBase = (float) $cn->amount_before_tax * $m;
        $taxBase = (float) $cn->tax_amount * $m;
        $totalBase = (float) $cn->total_amount * $m;

        $cn->loadMissing('items');

        $lines = [
            ['account_code' => '1100', 'debit' => 0, 'credit' => $totalBase],
        ];

        $byCode = [];
        foreach ($cn->items as $item) {
            $code = $item->account_code ?: '4000';
            $line = ((float) $item->quantity * (float) $item->unit_price) - (float) $item->discount_amount;
            $byCode[$code] = ($byCode[$code] ?? 0) + $line * $m;
        }
        if ($byCode === []) {
            $byCode['4000'] = $netBase;
        }
        foreach ($byCode as $code => $debit) {
            if (round($debit, 2) == 0.0) {
                continue;
            }
            $lines[] = ['account_code' => $code, 'debit' => $debit, 'credit' => 0];
        }
        $taxByAccount = [];
        foreach ($cn->items as $item) {
            $line = ((float) $item->quantity * (float) $item->unit_price) - (float) $item->discount_amount;
            $rate = (float) ($item->tax_rate ?? 0);
            if ($rate <= 0 || $line <= 0) {
                continue;
            }
            $tax = round($line * $rate / 100 * $m, 2);
            if ($tax <= 0) {
                continue;
            }
            $code = TaxCodeResolver::resolve(
                \Illuminate\Support\Facades\Schema::hasColumn('credit_note_items', 'tax_code_id')
                    ? (int) ($item->tax_code_id ?? 0) ?: null
                    : null,
                $rate,
            );
            $account = TaxCodeResolver::outputAccount($code);
            $taxByAccount[$account] = ($taxByAccount[$account] ?? 0) + $tax;
        }
        if ($taxByAccount === [] && $taxBase > 0) {
            $taxByAccount['2100'] = $taxBase;
        }
        foreach ($taxByAccount as $accountCode => $taxDebit) {
            if (round($taxDebit, 2) == 0.0) {
                continue;
            }
            $lines[] = ['account_code' => $accountCode, 'debit' => round($taxDebit, 2), 'credit' => 0];
        }

        JournalWriter::postSystem([
            'date'           => $cn->issue_date,
            'description'    => 'Credit Note Issued: '.$cn->cn_number,
            'reference_type' => 'Credit Note',
            'reference_id'   => $cn->id,
        ], $lines);
    }

    private function reverseJournal(CreditNote $cn): void
    {
        $journal = DB::table('journal_entries')
            ->where('reference_type', 'Credit Note')
            ->where('reference_id', $cn->id)
            ->latest('id')
            ->first();

        if (! $journal) {
            return;
        }

        try {
            JournalWriter::postReversalFromJournal(
                (int) $journal->id,
                'VOID REVERSAL: '.$cn->cn_number,
                now()->toDateString(),
                'Credit Note',
                $cn->id,
            );
        } catch (\LogicException) {
            return;
        }
    }

    private function ledgerMultiplier(CreditNote $cn): float
    {
        $currency = strtoupper((string) ($cn->currency ?: 'MYR'));
        $base = 'MYR';
        if (function_exists('tenant') && tenant()) {
            $base = strtoupper((string) (tenant()->base_currency ?? 'MYR'));
        }
        if ($currency === $base) {
            return 1.0;
        }
        $rate = (float) ($cn->exchange_rate ?? 0);

        return $rate > 0 ? $rate : 1.0;
    }
}
