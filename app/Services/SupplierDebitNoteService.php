<?php

namespace App\Services;

use App\Models\SupplierDebitNote;
use App\Support\DocumentNumber;
use App\Support\JournalWriter;
use Illuminate\Support\Facades\DB;

class SupplierDebitNoteService
{
    public function nextNumber(): string
    {
        return DocumentNumber::next('supplier_debit_notes', 'sdn_number', 'SDN');
    }

    /**
     * Extra AP: Dr expense / 2100, Cr 2110 (same shape as a bill).
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function issue(array $data, array $items): SupplierDebitNote
    {
        return DB::transaction(function () use ($data, $items) {
            $totals = CreditNoteService::computeLineTotals($items);
            $dn = SupplierDebitNote::create([
                'bill_id'            => $data['bill_id'] ?? null,
                'supplier_id'        => $data['supplier_id'],
                'sdn_number'         => $data['sdn_number'] ?? $this->nextNumber(),
                'issue_date'         => $data['issue_date'] ?? now()->toDateString(),
                'reason_code'        => $data['reason_code'] ?? null,
                'reason_description' => $data['reason_description'] ?? null,
                'amount_before_tax'  => $totals['net'],
                'tax_amount'         => $totals['tax'],
                'total_amount'       => $totals['total'],
                'currency'           => strtoupper((string) ($data['currency'] ?? 'MYR')),
                'status'             => 'posted',
                'notes'              => $data['notes'] ?? null,
                'created_by'         => $data['created_by'] ?? null,
            ]);

            foreach ($items as $item) {
                $line = ((float) $item['quantity'] * (float) $item['unit_price']) - (float) ($item['discount_amount'] ?? 0);
                $dn->items()->create([
                    'account_code'    => $item['account_code'] ?? '5000',
                    'description'     => $item['description'],
                    'quantity'        => $item['quantity'],
                    'unit_price'      => $item['unit_price'],
                    'tax_rate'        => $item['tax_rate'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'amount'          => $line,
                ]);
            }

            $this->postJournal($dn->load('items'));

            return $dn;
        });
    }

    public function void(SupplierDebitNote $dn): void
    {
        if ($dn->status === 'void') {
            throw new \LogicException('Supplier debit note is already voided.');
        }

        DB::transaction(function () use ($dn) {
            $journal = DB::table('journal_entries')
                ->where('reference_type', 'Supplier Debit Note')
                ->where('reference_id', $dn->id)
                ->latest('id')
                ->first();
            if ($journal) {
                try {
                    JournalWriter::postReversalFromJournal(
                        (int) $journal->id,
                        'VOID REVERSAL: '.$dn->sdn_number,
                        now()->toDateString(),
                        'Supplier Debit Note',
                        $dn->id,
                    );
                } catch (\LogicException) {
                    // no-op
                }
            }
            $dn->update(['status' => 'void']);
        });
    }

    private function postJournal(SupplierDebitNote $dn): void
    {
        $lines = [];
        $byCode = [];
        foreach ($dn->items as $item) {
            $code = $item->account_code ?: '5000';
            $line = ((float) $item->quantity * (float) $item->unit_price) - (float) $item->discount_amount;
            $byCode[$code] = ($byCode[$code] ?? 0) + $line;
        }
        foreach ($byCode as $code => $debit) {
            $lines[] = ['account_code' => $code, 'debit' => $debit, 'credit' => 0];
        }
        if ((float) $dn->tax_amount > 0) {
            $lines[] = [
                'account_code' => '2100',
                'debit'        => (float) $dn->tax_amount,
                'credit'       => 0,
            ];
        }
        $lines[] = [
            'account_code' => '2110',
            'debit'        => 0,
            'credit'       => (float) $dn->total_amount,
        ];

        JournalWriter::postSystem([
            'date'           => $dn->issue_date,
            'description'    => 'Supplier Debit Note: '.$dn->sdn_number,
            'reference_type' => 'Supplier Debit Note',
            'reference_id'   => $dn->id,
        ], $lines);
    }
}
