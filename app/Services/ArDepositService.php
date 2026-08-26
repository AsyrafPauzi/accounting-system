<?php

namespace App\Services;

use App\Models\ArDeposit;
use App\Models\ArDepositApplication;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class ArDepositService
{
    public function __construct(private InvoiceService $invoices) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function receive(array $data): ArDeposit
    {
        return DB::transaction(function () use ($data) {
            $deposit = ArDeposit::create([
                'customer_id'       => $data['customer_id'],
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
                ->whereIn('code', [$data['bank_account_code'], '2250'])
                ->pluck('id', 'code');

            $journalId = DB::table('journal_entries')->insertGetId([
                'date'           => $data['payment_date'],
                'description'    => 'Customer receipt '.$deposit->id.(! empty($data['reference']) ? ' '.$data['reference'] : ''),
                'reference_type' => 'AR Deposit',
                'reference_id'   => $deposit->id,
                'type'           => 'system',
                'status'         => 'posted',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $now = now();
            $amount = (float) $data['amount'];
            DB::table('journal_items')->insert([
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap[$data['bank_account_code']] ?? null, 'account_code' => $data['bank_account_code'], 'debit' => $amount, 'credit' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap['2250'] ?? null, 'account_code' => '2250', 'debit' => 0, 'credit' => $amount, 'created_at' => $now, 'updated_at' => $now],
            ]);

            return $deposit;
        });
    }

    /**
     * One bank receipt allocated across invoices. Leftover stays on 2250.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{invoice_id?: int, amount?: float|int|string}>  $allocations
     */
    public function receiveAndAllocate(array $data, array $allocations): ArDeposit
    {
        $sum = round(array_sum(array_map(fn ($row) => (float) ($row['amount'] ?? 0), $allocations)), 2);
        $amount = round((float) $data['amount'], 2);
        if ($sum > $amount + 0.001) {
            throw new \LogicException('Allocated amount exceeds the receipt.');
        }

        return DB::transaction(function () use ($data, $allocations) {
            $deposit = $this->receive($data);
            foreach ($allocations as $row) {
                $apply = round((float) ($row['amount'] ?? 0), 2);
                if ($apply <= 0) {
                    continue;
                }
                $invoice = Invoice::find($row['invoice_id'] ?? null);
                if (! $invoice) {
                    continue;
                }
                $this->applyToInvoice($deposit->fresh(), $invoice, $apply);
            }

            return $deposit->fresh(['applications.invoice', 'customer']);
        });
    }

    public function applyToInvoice(ArDeposit $deposit, Invoice $invoice, float $amount): void
    {
        if ((int) $invoice->customer_id !== (int) $deposit->customer_id) {
            throw new \LogicException('Deposit and invoice belong to different customers.');
        }
        $apply = round(min($amount, $deposit->openAmount(), $this->invoices->remainingBalance($invoice)), 2);
        if ($apply <= 0) {
            throw new \LogicException('Nothing left to apply.');
        }

        DB::transaction(function () use ($deposit, $invoice, $apply) {
            ArDepositApplication::create([
                'ar_deposit_id' => $deposit->id,
                'invoice_id'    => $invoice->id,
                'amount'        => $apply,
            ]);
            $deposit->applied_amount = round((float) $deposit->applied_amount + $apply, 2);
            $deposit->status = $deposit->openAmount() <= 0 ? 'applied' : 'open';
            $deposit->save();

            $accountMap = DB::table('accounts')->whereIn('code', ['2250', '1100'])->pluck('id', 'code');
            $journalId = DB::table('journal_entries')->insertGetId([
                'date'           => now(),
                'description'    => 'Apply deposit to '.$invoice->invoice_number,
                'reference_type' => 'AR Deposit Application',
                'reference_id'   => $deposit->id,
                'type'           => 'system',
                'status'         => 'posted',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $now = now();
            DB::table('journal_items')->insert([
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap['2250'] ?? null, 'account_code' => '2250', 'debit' => $apply, 'credit' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap['1100'] ?? null, 'account_code' => '1100', 'debit' => 0, 'credit' => $apply, 'created_at' => $now, 'updated_at' => $now],
            ]);

            $this->invoices->recalculateStatus($invoice->fresh());
        });
    }

    public function refundLeftover(ArDeposit $deposit, string $paymentDate, ?string $reference = null): void
    {
        $amount = $deposit->openAmount();
        if ($amount <= 0) {
            throw new \LogicException('No leftover deposit to refund.');
        }

        DB::transaction(function () use ($deposit, $amount, $paymentDate, $reference) {
            $accountMap = DB::table('accounts')
                ->whereIn('code', [$deposit->bank_account_code, '2250'])
                ->pluck('id', 'code');
            $journalId = DB::table('journal_entries')->insertGetId([
                'date'           => $paymentDate,
                'description'    => 'Refund leftover deposit '.$deposit->id,
                'reference_type' => 'AR Deposit Refund',
                'reference_id'   => $deposit->id,
                'type'           => 'system',
                'status'         => 'posted',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $now = now();
            DB::table('journal_items')->insert([
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap['2250'] ?? null, 'account_code' => '2250', 'debit' => $amount, 'credit' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap[$deposit->bank_account_code] ?? null, 'account_code' => $deposit->bank_account_code, 'debit' => 0, 'credit' => $amount, 'created_at' => $now, 'updated_at' => $now],
            ]);
            $deposit->refunded_amount = round((float) ($deposit->refunded_amount ?? 0) + $amount, 2);
            $this->refreshStatus($deposit);
            $deposit->notes = trim(($deposit->notes ? $deposit->notes."\n" : '').'Refunded leftover'.($reference ? ' '.$reference : ''));
            $deposit->save();
        });
    }

    public function forfeitLeftover(ArDeposit $deposit, string $date): void
    {
        $amount = $deposit->openAmount();
        if ($amount <= 0) {
            throw new \LogicException('No leftover deposit to forfeit.');
        }

        DB::transaction(function () use ($deposit, $amount, $date) {
            $accountMap = DB::table('accounts')->whereIn('code', ['2250', '4000'])->pluck('id', 'code');
            $journalId = DB::table('journal_entries')->insertGetId([
                'date'           => $date,
                'description'    => 'Forfeit leftover deposit '.$deposit->id,
                'reference_type' => 'AR Deposit Forfeit',
                'reference_id'   => $deposit->id,
                'type'           => 'system',
                'status'         => 'posted',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
            $now = now();
            DB::table('journal_items')->insert([
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap['2250'] ?? null, 'account_code' => '2250', 'debit' => $amount, 'credit' => 0, 'created_at' => $now, 'updated_at' => $now],
                ['journal_entry_id' => $journalId, 'account_id' => $accountMap['4000'] ?? null, 'account_code' => '4000', 'debit' => 0, 'credit' => $amount, 'created_at' => $now, 'updated_at' => $now],
            ]);
            $deposit->forfeited_amount = round((float) ($deposit->forfeited_amount ?? 0) + $amount, 2);
            $this->refreshStatus($deposit);
            $deposit->notes = trim(($deposit->notes ? $deposit->notes."\n" : '').'Forfeited leftover as income');
            $deposit->save();
        });
    }

    private function refreshStatus(ArDeposit $deposit): void
    {
        if ($deposit->openAmount() > 0) {
            $deposit->status = 'open';
        } elseif ((float) ($deposit->forfeited_amount ?? 0) > 0) {
            $deposit->status = 'forfeited';
        } elseif ((float) ($deposit->refunded_amount ?? 0) > 0) {
            $deposit->status = 'refunded';
        } else {
            $deposit->status = 'applied';
        }
    }

    public function assertEditable(ArDeposit $deposit): void
    {
        if ($deposit->status !== 'open') {
            throw new \LogicException("Deposit with status '{$deposit->status}' cannot be edited.");
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ArDeposit $deposit, array $data): ArDeposit
    {
        $this->assertEditable($deposit);
        $hasApps = (float) $deposit->applied_amount > 0
            || (float) ($deposit->refunded_amount ?? 0) > 0
            || (float) ($deposit->forfeited_amount ?? 0) > 0
            || $deposit->applications()->exists();

        return DB::transaction(function () use ($deposit, $data, $hasApps) {
            $payload = [
                'payment_date' => $data['payment_date'] ?? $deposit->payment_date,
                'reference'    => array_key_exists('reference', $data) ? $data['reference'] : $deposit->reference,
                'notes'        => array_key_exists('notes', $data) ? $data['notes'] : $deposit->notes,
            ];

            if (array_key_exists('amount', $data) || array_key_exists('bank_account_code', $data)) {
                if ($hasApps) {
                    throw new \LogicException('Cannot change amount or bank account after applications.');
                }
                $newAmount = round((float) ($data['amount'] ?? $deposit->amount), 2);
                $newBank = $data['bank_account_code'] ?? $deposit->bank_account_code;
                if ($newAmount < 0.01) {
                    throw new \LogicException('Amount must be at least 0.01.');
                }
                $this->reverseReceiveJournal($deposit);
                $payload['amount'] = $newAmount;
                $payload['bank_account_code'] = $newBank;
                $deposit->update($payload);
                $this->repostReceiveJournal($deposit->fresh());
            } else {
                $deposit->update($payload);
            }

            return $deposit->fresh(['customer', 'applications']);
        });
    }

    private function reverseReceiveJournal(ArDeposit $deposit): void
    {
        $journal = DB::table('journal_entries')
            ->where('reference_type', 'AR Deposit')
            ->where('reference_id', $deposit->id)
            ->latest('id')
            ->first();
        if (! $journal) {
            return;
        }
        $items = DB::table('journal_items')->where('journal_entry_id', $journal->id)->get();
        if ($items->isEmpty()) {
            return;
        }
        $reversalId = DB::table('journal_entries')->insertGetId([
            'date'           => now(),
            'description'    => 'EDIT REVERSAL deposit '.$deposit->id,
            'reference_type' => 'AR Deposit',
            'reference_id'   => $deposit->id,
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
        DB::table('journal_items')->insert($rows);
    }

    private function repostReceiveJournal(ArDeposit $deposit): void
    {
        $accountMap = DB::table('accounts')
            ->whereIn('code', [$deposit->bank_account_code, '2250'])
            ->pluck('id', 'code');
        $journalId = DB::table('journal_entries')->insertGetId([
            'date'           => $deposit->payment_date,
            'description'    => 'Customer receipt '.$deposit->id.(! empty($deposit->reference) ? ' '.$deposit->reference : ''),
            'reference_type' => 'AR Deposit',
            'reference_id'   => $deposit->id,
            'type'           => 'system',
            'status'         => 'posted',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
        $now = now();
        $amount = (float) $deposit->amount;
        DB::table('journal_items')->insert([
            ['journal_entry_id' => $journalId, 'account_id' => $accountMap[$deposit->bank_account_code] ?? null, 'account_code' => $deposit->bank_account_code, 'debit' => $amount, 'credit' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['journal_entry_id' => $journalId, 'account_id' => $accountMap['2250'] ?? null, 'account_code' => '2250', 'debit' => 0, 'credit' => $amount, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
