<?php

namespace App\Services;

use App\Models\Bill;
use Illuminate\Support\Facades\DB;

class BillService
{
    protected const AP_ACCOUNT = '2110';

    public function computeTotals(array $items, float $taxAmount = 0.0): array
    {
        $subtotal = collect($items)->sum(fn ($i) => (float) $i['amount']);
        $total = $subtotal + $taxAmount;

        return compact('subtotal', 'taxAmount', 'total');
    }

    public function create(array $data, array $items): Bill
    {
        return DB::transaction(function () use ($data, $items) {
            $totals = $this->computeTotals($items, (float) ($data['tax_amount'] ?? 0));

            $bill = Bill::create([
                'bill_number'   => $data['bill_number'],
                'supplier_id'   => $data['supplier_id'] ?? null,
                'bill_date'     => $data['bill_date'],
                'due_date'      => $data['due_date'] ?? null,
                'status'        => 'draft',
                'total_amount'  => $totals['total'],
                'amount_paid'   => 0,
                'tax_amount'    => $totals['taxAmount'],
                'currency'      => 'MYR',
                'private_notes' => $data['private_notes'] ?? null,
                'reference'     => $data['reference'] ?? null,
                'created_by'    => $data['created_by'] ?? null,
            ]);

            $this->syncItems($bill, $items);

            return $bill;
        });
    }

    public function update(Bill $bill, array $data, array $items): void
    {
        DB::transaction(function () use ($bill, $data, $items) {
            $totals = $this->computeTotals($items, (float) ($data['tax_amount'] ?? 0));

            $bill->update([
                'bill_number'   => $data['bill_number'],
                'supplier_id'   => $data['supplier_id'] ?? null,
                'bill_date'     => $data['bill_date'],
                'due_date'      => $data['due_date'] ?? null,
                'total_amount'  => $totals['total'],
                'tax_amount'    => $totals['taxAmount'],
                'private_notes' => $data['private_notes'] ?? null,
                'reference'     => $data['reference'] ?? null,
            ]);

            $bill->items()->delete();
            $this->syncItems($bill, $items);
        });
    }

    public function post(Bill $bill): void
    {
        if ($bill->status !== 'draft') {
            throw new \LogicException('Bill is already posted.');
        }

        DB::transaction(function () use ($bill) {
            $bill->load('items');

            $journalId = DB::table('journal_entries')->insertGetId([
                'date'           => $bill->bill_date,
                'description'    => 'Posted Bill: ' . $bill->bill_number,
                'reference_type' => 'Bill',
                'reference_id'   => $bill->id,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $codes = $bill->items->pluck('account_code')->push('2100')->unique()->toArray();
            $accountMap = DB::table('accounts')->whereIn('code', $codes)->pluck('id', 'code');

            $debitRows = [];
            foreach ($bill->items as $item) {
                $debitRows[] = [
                    'journal_entry_id' => $journalId,
                    'account_id'       => $accountMap[$item->account_code] ?? null,
                    'account_code'     => $item->account_code,
                    'debit'            => $item->amount,
                    'credit'           => 0,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }
            if ($bill->tax_amount > 0) {
                $debitRows[] = [
                    'journal_entry_id' => $journalId,
                    'account_id'       => $accountMap['2100'] ?? null,
                    'account_code'     => '2100',
                    'debit'            => $bill->tax_amount,
                    'credit'           => 0,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }
            DB::table('journal_items')->insert($debitRows);

            $apAccountId = DB::table('accounts')->where('code', self::AP_ACCOUNT)->value('id');

            DB::table('journal_items')->insert([
                'journal_entry_id' => $journalId,
                'account_id'       => $apAccountId,
                'account_code'     => self::AP_ACCOUNT,
                'debit'            => 0,
                'credit'           => $bill->total_amount,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            $bill->update(['status' => 'unpaid']);
        });
    }

    public function void(Bill $bill): void
    {
        if (in_array($bill->status, ['draft', 'void'], true)) {
            throw new \LogicException('Only posted bills can be voided.');
        }

        DB::transaction(function () use ($bill) {
            $bill->load('items');

            $journalId = DB::table('journal_entries')->insertGetId([
                'date'           => now(),
                'description'    => 'VOID REVERSAL: ' . $bill->bill_number,
                'reference_type' => 'Bill',
                'reference_id'   => $bill->id,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $codes = $bill->items->pluck('account_code')->push('2100')->unique()->toArray();
            $accountMap = DB::table('accounts')->whereIn('code', $codes)->pluck('id', 'code');

            $creditRows = [];
            foreach ($bill->items as $item) {
                $creditRows[] = [
                    'journal_entry_id' => $journalId,
                    'account_id'       => $accountMap[$item->account_code] ?? null,
                    'account_code'     => $item->account_code,
                    'debit'            => 0,
                    'credit'           => $item->amount,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }
            if ($bill->tax_amount > 0) {
                $creditRows[] = [
                    'journal_entry_id' => $journalId,
                    'account_id'       => $accountMap['2100'] ?? null,
                    'account_code'     => '2100',
                    'debit'            => 0,
                    'credit'           => $bill->tax_amount,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ];
            }
            DB::table('journal_items')->insert($creditRows);

            $apAccountId = DB::table('accounts')->where('code', self::AP_ACCOUNT)->value('id');

            DB::table('journal_items')->insert([
                'journal_entry_id' => $journalId,
                'account_id'       => $apAccountId,
                'account_code'     => self::AP_ACCOUNT,
                'debit'            => $bill->total_amount,
                'credit'           => 0,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            $bill->update(['status' => 'void', 'amount_paid' => 0]);
        });
    }

    public function recordPayment(Bill $bill, float $amount, string $paymentDate, string $bankAccountCode): void
    {
        if (in_array($bill->status, ['draft', 'void'], true)) {
            throw new \LogicException('Cannot record payment for a draft or void bill.');
        }

        DB::transaction(function () use ($bill, $amount, $paymentDate, $bankAccountCode) {
            $newPaid = (float) $bill->amount_paid + $amount;
            $total   = (float) $bill->total_amount;
            $status  = $newPaid >= $total ? 'paid' : 'partially paid';

            $bill->update([
                'amount_paid' => min($newPaid, $total),
                'status'      => $status,
            ]);

            $journalId = DB::table('journal_entries')->insertGetId([
                'date'           => $paymentDate,
                'description'    => 'Payment for Bill ' . $bill->bill_number,
                'reference_type' => 'Bill Payment',
                'reference_id'   => $bill->id,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $accountMap = DB::table('accounts')->whereIn('code', [self::AP_ACCOUNT, $bankAccountCode])->pluck('id', 'code');

            DB::table('journal_items')->insert([
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap[self::AP_ACCOUNT] ?? null, 'account_code' => self::AP_ACCOUNT, 'debit' => $amount, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap[$bankAccountCode] ?? null, 'account_code' => $bankAccountCode, 'debit' => 0, 'credit' => $amount, 'created_at' => now(), 'updated_at' => now()],
            ]);
        });
    }

    private function syncItems(Bill $bill, array $items): void
    {
        foreach ($items as $idx => $item) {
            $bill->items()->create([
                'account_code' => $item['account_code'],
                'description'  => $item['description'] ?? '',
                'quantity'     => (float) ($item['quantity'] ?? 1),
                'unit_amount'  => (float) ($item['unit_amount'] ?? $item['amount']),
                'amount'       => (float) $item['amount'],
                'sort_order'   => $idx,
            ]);
        }
    }
}
