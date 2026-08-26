<?php

namespace App\Services;

use App\Models\DebitNote;
use App\Models\Invoice;
use App\Support\DocumentNumber;
use App\Support\JournalWriter;
use Illuminate\Support\Facades\DB;

class DebitNoteService
{
    public function nextNumber(): string
    {
        return DocumentNumber::next('debit_notes', 'dn_number', 'DN');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function issue(array $data, array $items): DebitNote
    {
        return DB::transaction(function () use ($data, $items) {
            $totals = CreditNoteService::computeLineTotals($items);
            $dn = DebitNote::create([
                'invoice_id'         => $data['invoice_id'] ?? null,
                'customer_id'        => $data['customer_id'],
                'dn_number'          => $data['dn_number'] ?? $this->nextNumber(),
                'issue_date'         => $data['issue_date'] ?? now()->toDateString(),
                'reason_code'        => $data['reason_code'] ?? null,
                'reason_description' => $data['reason_description'] ?? null,
                'amount_before_tax'  => $totals['net'],
                'tax_amount'         => $totals['tax'],
                'total_amount'       => $totals['total'],
                'currency'           => strtoupper((string) ($data['currency'] ?? 'MYR')),
                'exchange_rate'      => (float) ($data['exchange_rate'] ?? 1),
                'customer_notes'     => $data['customer_notes'] ?? null,
                'status'             => 'posted',
                'lhdn_status'        => 'pending',
            ]);

            foreach ($items as $item) {
                $line = ((float) $item['quantity'] * (float) $item['unit_price']) - (float) ($item['discount_amount'] ?? 0);
                $dn->items()->create([
                    'product_id'          => $item['product_id'] ?? null,
                    'account_code'        => $item['account_code'] ?? null,
                    'description'         => $item['description'],
                    'quantity'            => $item['quantity'],
                    'unit_price'          => $item['unit_price'],
                    'tax_rate'            => $item['tax_rate'] ?? 0,
                    'discount_amount'     => $item['discount_amount'] ?? 0,
                    'item_classification' => $item['item_classification'] ?? null,
                    'amount'              => $line,
                ]);
            }

            $this->postJournal($dn->load('items'));

            return $dn;
        });
    }

    public function fromInvoice(Invoice $invoice, array $overrides = []): DebitNote
    {
        $invoice->loadMissing('items');
        $items = $invoice->items->map(fn ($i) => [
            'product_id'          => $i->product_id,
            'account_code'        => $i->account_code,
            'description'         => $i->description,
            'quantity'            => (float) $i->quantity,
            'unit_price'          => (float) $i->unit_price,
            'tax_rate'            => (float) $i->tax_rate,
            'discount_amount'     => (float) ($i->discount_amount ?? 0),
            'item_classification' => $i->item_classification,
        ])->all();

        return $this->issue(array_merge([
            'invoice_id'  => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'currency'    => $invoice->currency,
            'exchange_rate' => $invoice->exchange_rate,
        ], $overrides), $items);
    }

    public function void(DebitNote $dn): void
    {
        if ($dn->status === 'void') {
            throw new \LogicException('Debit note is already voided.');
        }

        DB::transaction(function () use ($dn) {
            \App\Support\AccountingPeriodResolver::assertOpenForDate(
                \Carbon\Carbon::parse($dn->issue_date)->toDateString()
            );
            $journal = DB::table('journal_entries')
                ->where('reference_type', 'Debit Note')
                ->where('reference_id', $dn->id)
                ->latest('id')
                ->first();
            if ($journal) {
                try {
                    JournalWriter::postReversalFromJournal(
                        (int) $journal->id,
                        'VOID REVERSAL: '.$dn->dn_number,
                        now()->toDateString(),
                        'Debit Note',
                        $dn->id,
                    );
                } catch (\LogicException) {
                    // no-op
                }
            }
            $dn->update(['status' => 'void']);
        });
    }

    public function assertEditable(DebitNote $dn): void
    {
        if ($dn->status === 'void') {
            throw new \LogicException('Cannot edit a voided debit note.');
        }
        if (filled($dn->lhdn_uuid) && in_array($dn->lhdn_status, ['submitted', 'valid'], true)) {
            throw new \LogicException('Cannot edit a debit note locked by MyInvois.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>|null  $items
     */
    public function update(DebitNote $dn, array $data, ?array $items = null): DebitNote
    {
        $this->assertEditable($dn);

        return DB::transaction(function () use ($dn, $data, $items) {
            if ($items !== null) {
                $this->reverseIssueJournal($dn);
                $dn->items()->delete();
                $totals = CreditNoteService::computeLineTotals($items);
                $dn->update([
                    'issue_date'         => $data['issue_date'] ?? $dn->issue_date,
                    'reason_code'        => $data['reason_code'] ?? $dn->reason_code,
                    'reason_description' => array_key_exists('reason_description', $data) ? $data['reason_description'] : $dn->reason_description,
                    'customer_notes'     => array_key_exists('customer_notes', $data) ? $data['customer_notes'] : $dn->customer_notes,
                    'amount_before_tax'  => $totals['net'],
                    'tax_amount'         => $totals['tax'],
                    'total_amount'       => $totals['total'],
                ]);
                foreach ($items as $item) {
                    $line = ((float) $item['quantity'] * (float) $item['unit_price']) - (float) ($item['discount_amount'] ?? 0);
                    $dn->items()->create([
                        'product_id'          => $item['product_id'] ?? null,
                        'account_code'        => $item['account_code'] ?? null,
                        'description'         => $item['description'],
                        'quantity'            => $item['quantity'],
                        'unit_price'          => $item['unit_price'],
                        'tax_rate'            => $item['tax_rate'] ?? 0,
                        'discount_amount'     => $item['discount_amount'] ?? 0,
                        'item_classification' => $item['item_classification'] ?? null,
                        'amount'              => $line,
                    ]);
                }
                $this->postJournal($dn->fresh('items'));
            } else {
                $dn->update([
                    'issue_date'         => $data['issue_date'] ?? $dn->issue_date,
                    'reason_code'        => $data['reason_code'] ?? $dn->reason_code,
                    'reason_description' => array_key_exists('reason_description', $data) ? $data['reason_description'] : $dn->reason_description,
                    'customer_notes'     => array_key_exists('customer_notes', $data) ? $data['customer_notes'] : $dn->customer_notes,
                ]);
            }

            return $dn->fresh('items');
        });
    }

    private function reverseIssueJournal(DebitNote $dn): void
    {
        $journal = DB::table('journal_entries')
            ->where('reference_type', 'Debit Note')
            ->where('reference_id', $dn->id)
            ->where('description', 'like', 'Debit Note Issued:%')
            ->latest('id')
            ->first();
        if (! $journal) {
            $journal = DB::table('journal_entries')
                ->where('reference_type', 'Debit Note')
                ->where('reference_id', $dn->id)
                ->latest('id')
                ->first();
        }
        if (! $journal) {
            return;
        }
        try {
            JournalWriter::postReversalFromJournal(
                (int) $journal->id,
                'EDIT REVERSAL: '.$dn->dn_number,
                now()->toDateString(),
                'Debit Note',
                $dn->id,
            );
        } catch (\LogicException) {
            return;
        }
    }

    private function postJournal(DebitNote $dn): void
    {
        $m = 1.0;
        $currency = strtoupper((string) ($dn->currency ?: 'MYR'));
        $base = function_exists('tenant') && tenant()
            ? strtoupper((string) (tenant()->base_currency ?? 'MYR'))
            : 'MYR';
        if ($currency !== $base) {
            $rate = (float) ($dn->exchange_rate ?? 0);
            $m = $rate > 0 ? $rate : 1.0;
        }

        $lines = [[
            'account_code' => '1100',
            'debit'        => (float) $dn->total_amount * $m,
            'credit'       => 0,
        ]];
        $byCode = [];
        foreach ($dn->items as $item) {
            $code = $item->account_code ?: '4000';
            $line = ((float) $item->quantity * (float) $item->unit_price) - (float) $item->discount_amount;
            $byCode[$code] = ($byCode[$code] ?? 0) + $line * $m;
        }
        foreach ($byCode as $code => $credit) {
            $lines[] = ['account_code' => $code, 'debit' => 0, 'credit' => $credit];
        }
        if ((float) $dn->tax_amount > 0) {
            $lines[] = [
                'account_code' => '2100',
                'debit'        => 0,
                'credit'       => (float) $dn->tax_amount * $m,
            ];
        }

        JournalWriter::postSystem([
            'date'           => $dn->issue_date,
            'description'    => 'Debit Note Issued: '.$dn->dn_number,
            'reference_type' => 'Debit Note',
            'reference_id'   => $dn->id,
        ], $lines);
    }
}
