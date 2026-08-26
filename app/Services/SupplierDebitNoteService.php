<?php

namespace App\Services;

use App\Models\SupplierDebitNote;
use App\Support\DocumentNumber;
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
                $items = DB::table('journal_items')->where('journal_entry_id', $journal->id)->get();
                $reversalId = DB::table('journal_entries')->insertGetId([
                    'date'           => now(),
                    'description'    => 'VOID REVERSAL: '.$dn->sdn_number,
                    'reference_type' => 'Supplier Debit Note',
                    'reference_id'   => $dn->id,
                'type'           => 'system',
                'status'         => 'posted',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $now = now();
                $rows = [];
                foreach ($items as $item) {
                    $rows[] = [
                        'journal_entry_id' => $reversalId,
                        'account_id'       => $item->account_id,
                        'account_code'     => $item->account_code,
                        'debit'            => $item->credit,
                        'credit'           => $item->debit,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ];
                }
                if ($rows !== []) {
                    DB::table('journal_items')->insert($rows);
                }
            }
            $dn->update(['status' => 'void']);
        });
    }

    private function postJournal(SupplierDebitNote $dn): void
    {
        $codes = ['2110', '5000', '2100'];
        foreach ($dn->items as $item) {
            if ($item->account_code) {
                $codes[] = $item->account_code;
            }
        }
        $accountMap = DB::table('accounts')->whereIn('code', array_unique($codes))->pluck('id', 'code');
        $journalId = DB::table('journal_entries')->insertGetId([
            'date'           => $dn->issue_date,
            'description'    => 'Supplier Debit Note: '.$dn->sdn_number,
            'reference_type' => 'Supplier Debit Note',
            'reference_id'   => $dn->id,
            'type'           => 'system',
            'status'         => 'posted',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $now = now();
        $rows = [];
        $byCode = [];
        foreach ($dn->items as $item) {
            $code = $item->account_code ?: '5000';
            $line = ((float) $item->quantity * (float) $item->unit_price) - (float) $item->discount_amount;
            $byCode[$code] = ($byCode[$code] ?? 0) + $line;
        }
        foreach ($byCode as $code => $debit) {
            $rows[] = [
                'journal_entry_id' => $journalId,
                'account_id'       => $accountMap[$code] ?? $accountMap['5000'] ?? null,
                'account_code'     => $code,
                'debit'            => $debit,
                'credit'           => 0,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }
        if ((float) $dn->tax_amount > 0) {
            $rows[] = [
                'journal_entry_id' => $journalId,
                'account_id'       => $accountMap['2100'] ?? null,
                'account_code'     => '2100',
                'debit'            => (float) $dn->tax_amount,
                'credit'           => 0,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        }
        $rows[] = [
            'journal_entry_id' => $journalId,
            'account_id'       => $accountMap['2110'] ?? null,
            'account_code'     => '2110',
            'debit'            => 0,
            'credit'           => (float) $dn->total_amount,
            'created_at'       => $now,
            'updated_at'       => $now,
        ];
        DB::table('journal_items')->insert($rows);
    }
}
