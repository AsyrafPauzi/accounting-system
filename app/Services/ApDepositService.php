<?php

namespace App\Services;

use App\Models\ApDeposit;
use App\Models\ApDepositApplication;
use App\Models\Bill;
use Illuminate\Support\Facades\DB;

class ApDepositService
{
    public function __construct(private BillService $bills) {}

    /**
     * Pay a supplier deposit: Dr 1300, Cr Bank.
     *
     * @param  array<string, mixed>  $data
     */
    public function receive(array $data): ApDeposit
    {
        return DB::transaction(function () use ($data) {
            $deposit = ApDeposit::create([
                'supplier_id'       => $data['supplier_id'],
                'amount'            => $data['amount'],
                'applied_amount'    => 0,
                'payment_date'      => $data['payment_date'],
                'bank_account_code' => $data['bank_account_code'],
                'reference'         => $data['reference'] ?? null,
                'status'            => 'open',
                'notes'             => $data['notes'] ?? null,
                'created_by'        => $data['created_by'] ?? null,
            ]);

            $accountMap = DB::table('accounts')
                ->whereIn('code', [$data['bank_account_code'], '1300'])
                ->pluck('id', 'code');
            $journalId = DB::table('journal_entries')->insertGetId([
                'date'           => $data['payment_date'],
                'description'    => 'Supplier deposit '.$deposit->id.(! empty($data['reference']) ? ' '.$data['reference'] : ''),
                'reference_type' => 'AP Deposit',
                'reference_id'   => $deposit->id,
                'type'           => 'system',
                'status'         => 'posted',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $now = now();
            $amount = (float) $data['amount'];
            DB::table('journal_items')->insert([
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap['1300'] ?? null, 'account_code' => '1300', 'debit' => $amount, 'credit' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap[$data['bank_account_code']] ?? null, 'account_code' => $data['bank_account_code'], 'debit' => 0, 'credit' => $amount, 'created_at' => $now, 'updated_at' => $now],
            ]);

            return $deposit;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{bill_id?: int, amount?: float|int|string}>  $allocations
     */
    public function receiveAndAllocate(array $data, array $allocations): ApDeposit
    {
        $sum = round(array_sum(array_map(fn ($row) => (float) ($row['amount'] ?? 0), $allocations)), 2);
        $amount = round((float) $data['amount'], 2);
        if ($sum > $amount + 0.001) {
            throw new \LogicException('Allocated amount exceeds the payment.');
        }

        return DB::transaction(function () use ($data, $allocations) {
            $deposit = $this->receive($data);
            foreach ($allocations as $row) {
                $apply = round((float) ($row['amount'] ?? 0), 2);
                if ($apply <= 0) {
                    continue;
                }
                $bill = Bill::find($row['bill_id'] ?? null);
                if (! $bill) {
                    continue;
                }
                $this->applyToBill($deposit->fresh(), $bill, $apply);
            }

            return $deposit->fresh(['applications.bill', 'supplier']);
        });
    }

    public function applyToBill(ApDeposit $deposit, Bill $bill, float $amount): void
    {
        if ((int) $bill->supplier_id !== (int) $deposit->supplier_id) {
            throw new \LogicException('Deposit and bill belong to different suppliers.');
        }
        $apply = round(min($amount, $deposit->openAmount(), $this->bills->remainingBalance($bill)), 2);
        if ($apply <= 0) {
            throw new \LogicException('Nothing left to apply.');
        }

        DB::transaction(function () use ($deposit, $bill, $apply) {
            ApDepositApplication::create([
                'ap_deposit_id' => $deposit->id,
                'bill_id'       => $bill->id,
                'amount'        => $apply,
            ]);
            $deposit->applied_amount = round((float) $deposit->applied_amount + $apply, 2);
            $deposit->status = $deposit->openAmount() <= 0 ? 'applied' : 'open';
            $deposit->save();

            $accountMap = DB::table('accounts')->whereIn('code', ['1300', '2110'])->pluck('id', 'code');
            $journalId = DB::table('journal_entries')->insertGetId([
                'date'           => now(),
                'description'    => 'Apply supplier deposit to '.$bill->bill_number,
                'reference_type' => 'AP Deposit Application',
                'reference_id'   => $deposit->id,
                'type'           => 'system',
                'status'         => 'posted',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $now = now();
            DB::table('journal_items')->insert([
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap['2110'] ?? null, 'account_code' => '2110', 'debit' => $apply, 'credit' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap['1300'] ?? null, 'account_code' => '1300', 'debit' => 0, 'credit' => $apply, 'created_at' => $now, 'updated_at' => $now],
            ]);

            $this->bills->recalculateStatus($bill->fresh());
        });
    }
}
