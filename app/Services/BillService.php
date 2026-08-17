<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillPayment;
use App\Support\DocumentNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BillService
{
    protected const AP_ACCOUNT = '2110';

    public function nextNumber(): string
    {
        return DocumentNumber::next('bills', 'bill_number', 'BILL');
    }

    public function remainingBalance(Bill $bill): float
    {
        $credits = 0.0;
        if (Schema::hasTable('supplier_credit_note_applications')) {
            $credits = (float) DB::table('supplier_credit_note_applications')
                ->where('bill_id', $bill->id)
                ->sum('amount');
        }

        $deposits = 0.0;
        if (Schema::hasTable('ap_deposit_applications')) {
            $deposits = (float) DB::table('ap_deposit_applications')
                ->where('bill_id', $bill->id)
                ->sum('amount');
        }

        return round(
            (float) ($bill->getAttributes()['total_amount'] ?? 0)
            - (float) ($bill->getAttributes()['amount_paid'] ?? 0)
            - $credits
            - $deposits,
            2
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function openBillsForSupplier(int $supplierId): array
    {
        return Bill::query()
            ->where('supplier_id', $supplierId)
            ->whereNotIn('status', ['draft', 'void', 'paid'])
            ->orderBy('due_date')
            ->get()
            ->map(fn (Bill $b) => [
                'id'          => $b->id,
                'bill_number' => $b->bill_number,
                'bill_date'   => optional($b->bill_date)->toDateString(),
                'due_date'    => optional($b->due_date)->toDateString(),
                'total_amount'=> (float) $b->total_amount,
                'balance'     => $this->remainingBalance($b),
                'currency'    => $b->currency ?? 'MYR',
                'status'      => $b->status,
            ])
            ->filter(fn ($row) => $row['balance'] > 0)
            ->values()
            ->all();
    }

    public function recalculateStatus(Bill $bill): void
    {
        if (in_array($bill->status, ['draft', 'void'], true)) {
            return;
        }

        $balance = $this->remainingBalance($bill);
        if ($balance <= 0) {
            $bill->status = 'paid';
        } elseif ((float) $bill->amount_paid > 0 || $balance < (float) $bill->total_amount) {
            $bill->status = 'partially paid';
        } else {
            $bill->status = 'unpaid';
        }
        $bill->save();
    }

    public function computeTotals(array $items, float $taxAmount = 0.0): array
    {
        $subtotal = collect($items)->sum(fn ($i) => (float) $i['amount']);
        $total = $subtotal + $taxAmount;

        return compact('subtotal', 'taxAmount', 'total');
    }

    public function create(array $data, array $items): Bill
    {
        return DB::transaction(function () use ($data, $items) {
            $kind = $this->normalizeKind($data['purchase_kind'] ?? 'credit');
            $notes = $data['private_notes'] ?? null;
            if ($kind === 'claim') {
                $prefix = 'Expense claim — reimburse via bill payment.';
                $notes = $notes ? $prefix.' '.$notes : $prefix;
            }
            if ($kind === 'cash' && empty($data['bank_account_code'])) {
                throw new \LogicException('Cash purchase requires a bank or cash account.');
            }

            $totals = $this->computeTotals($items, (float) ($data['tax_amount'] ?? 0));
            $dueDate = $data['due_date'] ?? null;
            if ($kind === 'cash' && ! $dueDate) {
                $dueDate = $data['bill_date'];
            }

            $payload = [
                'bill_number'        => $data['bill_number'] ?? $this->nextNumber(),
                'supplier_id'        => $data['supplier_id'] ?? null,
                'purchase_order_id'  => $data['purchase_order_id'] ?? null,
                'goods_receipt_id'   => $data['goods_receipt_id'] ?? null,
                'bill_date'          => $data['bill_date'],
                'due_date'           => $dueDate,
                'status'             => 'draft',
                'total_amount'       => $totals['total'],
                'amount_paid'        => 0,
                'tax_amount'         => $totals['taxAmount'],
                'currency'           => 'MYR',
                'private_notes'      => $notes,
                'reference'          => $data['reference'] ?? null,
                'created_by'         => $data['created_by'] ?? null,
                'receipt_path'       => $data['receipt_path'] ?? null,
                'ocr_status'         => $data['ocr_status'] ?? 'none',
                'ocr_data'           => $data['ocr_data'] ?? null,
                'audit_status'       => $data['audit_status'] ?? 'unaudited',
                'audited_at'         => $data['audited_at'] ?? null,
                'audited_by'         => $data['audited_by'] ?? null,
            ];
            if (Schema::hasColumn('bills', 'purchase_kind')) {
                $payload['purchase_kind'] = $kind;
            }

            $bill = Bill::create($payload);
            $this->syncItems($bill, $items);

            if ($kind === 'cash') {
                $this->post($bill->fresh('items'));
                $this->recordPayment(
                    $bill->fresh(),
                    (float) $totals['total'],
                    (string) $data['bill_date'],
                    (string) $data['bank_account_code'],
                    $data['reference'] ?? 'Cash purchase',
                    $data['created_by'] ?? null
                );

                return $bill->fresh(['items', 'payments']);
            }

            return $bill;
        });
    }

    public function normalizeKind(mixed $kind): string
    {
        $kind = strtolower(trim((string) $kind));

        return in_array($kind, ['credit', 'cash', 'claim'], true) ? $kind : 'credit';
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
                'receipt_path'  => $data['receipt_path'] ?? $bill->receipt_path,
                'ocr_status'    => $data['ocr_status'] ?? $bill->ocr_status,
                'ocr_data'      => $data['ocr_data'] ?? $bill->ocr_data,
                'audit_status'  => $data['audit_status'] ?? $bill->audit_status,
                'audited_at'    => $data['audited_at'] ?? $bill->audited_at,
                'audited_by'    => $data['audited_by'] ?? $bill->audited_by,
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

    public function recordPayment(Bill $bill, float $amount, string $paymentDate, string $bankAccountCode, ?string $reference = null, ?int $createdBy = null): ?BillPayment
    {
        if (in_array($bill->status, ['draft', 'void'], true)) {
            throw new \LogicException('Cannot record payment for a draft or void bill.');
        }

        return DB::transaction(function () use ($bill, $amount, $paymentDate, $bankAccountCode, $reference, $createdBy) {
            $apply = round(min($amount, $this->remainingBalance($bill)), 2);
            if ($apply <= 0) {
                throw new \LogicException('Nothing left to pay on this bill.');
            }

            $payment = null;
            if (Schema::hasTable('bill_payments')) {
                $payment = BillPayment::create([
                    'bill_id'           => $bill->id,
                    'amount'            => $apply,
                    'payment_date'      => $paymentDate,
                    'bank_account_code' => $bankAccountCode,
                    'reference'         => $reference,
                    'created_by'        => $createdBy,
                ]);
            }

            $bill->amount_paid = round((float) $bill->amount_paid + $apply, 2);
            $bill->save();

            $journalId = DB::table('journal_entries')->insertGetId([
                'date'           => $paymentDate,
                'description'    => 'Payment for Bill '.$bill->bill_number,
                'reference_type' => 'Bill Payment',
                'reference_id'   => $bill->id,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $accountMap = DB::table('accounts')->whereIn('code', [self::AP_ACCOUNT, $bankAccountCode])->pluck('id', 'code');
            $now = now();
            DB::table('journal_items')->insert([
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap[self::AP_ACCOUNT] ?? null, 'account_code' => self::AP_ACCOUNT, 'debit' => $apply, 'credit' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap[$bankAccountCode] ?? null, 'account_code' => $bankAccountCode, 'debit' => 0, 'credit' => $apply, 'created_at' => $now, 'updated_at' => $now],
            ]);

            $this->recalculateStatus($bill->fresh());

            return $payment;
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
