<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\SupplierCreditNote;
use App\Models\SupplierCreditNoteApplication;
use App\Models\SupplierCreditNoteRefund;
use App\Support\DocumentNumber;
use App\Support\JournalWriter;
use Illuminate\Support\Facades\DB;

class SupplierCreditNoteService
{
    public function __construct(private BillService $bills) {}

    public function nextNumber(): string
    {
        return DocumentNumber::next('supplier_credit_notes', 'scn_number', 'SCN');
    }

    /**
     * Issue: Dr 2110, Cr expense / 2100. Apply to a bill does not post again.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function issue(array $data, array $items): SupplierCreditNote
    {
        return DB::transaction(function () use ($data, $items) {
            $totals = CreditNoteService::computeLineTotals($items);
            $cn = SupplierCreditNote::create([
                'bill_id'            => $data['bill_id'] ?? null,
                'supplier_id'        => $data['supplier_id'],
                'scn_number'         => $data['scn_number'] ?? $this->nextNumber(),
                'issue_date'         => $data['issue_date'] ?? now()->toDateString(),
                'reason_code'        => $data['reason_code'] ?? null,
                'reason_description' => $data['reason_description'] ?? null,
                'amount_before_tax'  => $totals['net'],
                'tax_amount'         => $totals['tax'],
                'total_amount'       => $totals['total'],
                'applied_amount'     => 0,
                'currency'           => strtoupper((string) ($data['currency'] ?? 'MYR')),
                'status'             => 'posted',
                'notes'              => $data['notes'] ?? null,
                'created_by'         => $data['created_by'] ?? null,
            ]);

            foreach ($items as $item) {
                $line = ((float) $item['quantity'] * (float) $item['unit_price']) - (float) ($item['discount_amount'] ?? 0);
                $cn->items()->create([
                    'account_code'    => $item['account_code'] ?? '5000',
                    'description'     => $item['description'],
                    'quantity'        => $item['quantity'],
                    'unit_price'      => $item['unit_price'],
                    'tax_rate'        => $item['tax_rate'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'amount'          => $line,
                ]);
            }

            $this->postJournal($cn->load('items'));

            if (! empty($data['bill_id'])) {
                $this->applyToBill($cn, Bill::findOrFail($data['bill_id']), $totals['total']);
            }

            return $cn->load('items');
        });
    }

    public function applyToBill(SupplierCreditNote $cn, Bill $bill, float $amount): void
    {
        if ($cn->status === 'void') {
            throw new \LogicException('Cannot apply a voided supplier credit note.');
        }
        if ((int) $bill->supplier_id !== (int) $cn->supplier_id) {
            throw new \LogicException('Credit note and bill belong to different suppliers.');
        }
        if (in_array($bill->status, ['draft', 'void'], true)) {
            throw new \LogicException('Cannot apply credit to a draft or void bill.');
        }

        $apply = round(min($amount, $cn->openAmount(), $this->bills->remainingBalance($bill)), 2);
        if ($apply <= 0) {
            throw new \LogicException('Nothing left to apply.');
        }

        SupplierCreditNoteApplication::create([
            'supplier_credit_note_id' => $cn->id,
            'bill_id'                 => $bill->id,
            'amount'                  => $apply,
        ]);
        $cn->applied_amount = round((float) $cn->applied_amount + $apply, 2);
        $cn->save();
        $this->bills->recalculateStatus($bill->fresh());
    }

    public function refund(
        SupplierCreditNote $cn,
        float $amount,
        string $bankAccountCode,
        string $paymentDate,
        ?string $reference = null,
        ?int $createdBy = null
    ): SupplierCreditNoteRefund {
        if ($cn->status === 'void') {
            throw new \LogicException('Cannot refund a voided supplier credit note.');
        }
        $amount = round(min($amount, $cn->openAmount()), 2);
        if ($amount <= 0) {
            throw new \LogicException('No unapplied supplier credit left to refund.');
        }

        return DB::transaction(function () use ($cn, $amount, $bankAccountCode, $paymentDate, $reference, $createdBy) {
            $refund = SupplierCreditNoteRefund::create([
                'supplier_credit_note_id' => $cn->id,
                'amount'                  => $amount,
                'payment_date'            => $paymentDate,
                'bank_account_code'       => $bankAccountCode,
                'reference'               => $reference,
                'created_by'              => $createdBy,
            ]);
            $cn->refunded_amount = round((float) ($cn->refunded_amount ?? 0) + $amount, 2);
            $cn->save();

            JournalWriter::postSystem([
                'date'           => $paymentDate,
                'description'    => 'Refund of supplier credit '.$cn->scn_number,
                'reference_type' => 'Supplier Credit Note Refund',
                'reference_id'   => $refund->id,
            ], [
                ['account_code' => $bankAccountCode, 'debit' => $amount, 'credit' => 0],
                ['account_code' => '2110', 'debit' => 0, 'credit' => $amount],
            ]);

            return $refund;
        });
    }

    public function void(SupplierCreditNote $cn): void
    {
        if ($cn->status === 'void') {
            throw new \LogicException('Supplier credit note is already voided.');
        }

        DB::transaction(function () use ($cn) {
            \App\Support\AccountingPeriodResolver::assertOpenForDate(
                \Carbon\Carbon::parse($cn->issue_date)->toDateString()
            );
            foreach (SupplierCreditNoteRefund::query()->where('supplier_credit_note_id', $cn->id)->get() as $refund) {
                $this->reverseJournal('Supplier Credit Note Refund', $refund->id, 'VOID REFUND: '.$cn->scn_number);
            }
            $this->reverseJournal('Supplier Credit Note', $cn->id, 'VOID REVERSAL: '.$cn->scn_number);

            $billIds = SupplierCreditNoteApplication::query()
                ->where('supplier_credit_note_id', $cn->id)
                ->pluck('bill_id')
                ->unique()
                ->filter();
            SupplierCreditNoteApplication::query()->where('supplier_credit_note_id', $cn->id)->delete();
            $cn->update(['status' => 'void', 'applied_amount' => 0, 'refunded_amount' => 0]);

            foreach ($billIds as $billId) {
                $bill = Bill::find($billId);
                if ($bill) {
                    $this->bills->recalculateStatus($bill);
                }
            }
        });
    }

    private function postJournal(SupplierCreditNote $cn): void
    {
        $lines = [[
            'account_code' => '2110',
            'debit'        => (float) $cn->total_amount,
            'credit'       => 0,
        ]];
        $byCode = [];
        foreach ($cn->items as $item) {
            $code = $item->account_code ?: '5000';
            $line = ((float) $item->quantity * (float) $item->unit_price) - (float) $item->discount_amount;
            $byCode[$code] = ($byCode[$code] ?? 0) + $line;
        }
        foreach ($byCode as $code => $credit) {
            $lines[] = ['account_code' => $code, 'debit' => 0, 'credit' => $credit];
        }
        if ((float) $cn->tax_amount > 0) {
            $lines[] = [
                'account_code' => '2100',
                'debit'        => 0,
                'credit'       => (float) $cn->tax_amount,
            ];
        }

        JournalWriter::postSystem([
            'date'           => $cn->issue_date,
            'description'    => 'Supplier Credit Note: '.$cn->scn_number,
            'reference_type' => 'Supplier Credit Note',
            'reference_id'   => $cn->id,
        ], $lines);
    }

    private function reverseJournal(string $type, int $referenceId, string $description): void
    {
        JournalWriter::postReversalByReference(
            $type,
            $referenceId,
            $description,
            now()->toDateString(),
        );
    }
}
