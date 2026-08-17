<?php

namespace App\Services;

use App\Models\Customer;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builds a Balance Forward customer statement.
 *
 *   Opening balance     — what the customer owed at the start of the period
 * + period charges      — invoices issued in the period
 * - period payments     — payments received in the period
 * - period credit notes — credits applied in the period
 * = Closing balance     — what they owe at the end of the period
 *
 * Read-only. No new tables; everything is computed from existing invoices,
 * journal entries (where reference_type = 'Invoice Payment') and credit notes.
 */
class CustomerStatementService
{
    /**
     * Build a full statement for one customer between two dates.
     *
     * @return array{
     *   customer: \App\Models\Customer,
     *   from: string, to: string,
     *   opening_balance: float,
     *   closing_balance: float,
     *   total_charges: float,
     *   total_payments: float,
     *   total_credits: float,
     *   activity: array<int, array<string, mixed>>
     * }
     */
    public function build(Customer $customer, CarbonInterface $from, CarbonInterface $to): array
    {
        $fromDate = $from->copy()->startOfDay();
        $toDate = $to->copy()->endOfDay();

        $opening = $this->openingBalance($customer, $fromDate);
        $activity = $this->activity($customer, $fromDate, $toDate);

        $totalCharges = 0.0;
        $totalPayments = 0.0;
        $totalCredits = 0.0;

        $running = $opening;
        foreach ($activity as $idx => $event) {
            $charge = (float) ($event['charge'] ?? 0);
            $payment = (float) ($event['payment'] ?? 0);
            $credit = (float) ($event['credit'] ?? 0);

            $totalCharges += $charge;
            $totalPayments += $payment;
            $totalCredits += $credit;

            $running += $charge - $payment - $credit;
            $activity[$idx]['running_balance'] = round($running, 2);
        }

        return [
            'customer'        => $customer,
            'from'            => $fromDate->toDateString(),
            'to'              => $toDate->toDateString(),
            'opening_balance' => round($opening, 2),
            'closing_balance' => round($running, 2),
            'total_charges'   => round($totalCharges, 2),
            'total_payments'  => round($totalPayments, 2),
            'total_credits'   => round($totalCredits, 2),
            'activity'        => $activity,
        ];
    }

    /**
     * Sum of (invoices issued < from) - (payments before from) - (credit notes before from).
     */
    private function openingBalance(Customer $customer, CarbonInterface $from): float
    {
        $invoicesBefore = (float) DB::table('invoices')
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['draft', 'void'])
            ->where('issue_date', '<', $from->toDateString())
            ->whereNull('deleted_at')
            ->sum('total_amount');

        $paymentsBefore = (float) DB::table('journal_entries as je')
            ->join('journal_items as ji', 'ji.journal_entry_id', '=', 'je.id')
            ->join('invoices as i', 'i.id', '=', 'je.reference_id')
            ->where('je.reference_type', 'Invoice Payment')
            ->where('i.customer_id', $customer->id)
            ->where('ji.account_code', '1100')
            ->where('je.date', '<', $from->toDateString())
            ->whereNull('je.deleted_at')
            ->sum('ji.credit');

        $creditsBefore = (float) DB::table('credit_notes')
            ->where('customer_id', $customer->id)
            ->where('status', '!=', 'void')
            ->where('issue_date', '<', $from->toDateString())
            ->whereNull('deleted_at')
            ->sum('total_amount');

        $debitNotesBefore = 0.0;
        if (Schema::hasTable('debit_notes')) {
            $debitNotesBefore = (float) DB::table('debit_notes')
                ->where('customer_id', $customer->id)
                ->where('status', '!=', 'void')
                ->whereDate('issue_date', '<', $from->toDateString())
                ->whereNull('deleted_at')
                ->sum('total_amount');
        }

        $depositAppsBefore = $this->journalArMovement($customer, 'AR Deposit Application', 'credit', '<', $from->toDateString());
        $refundsBefore = $this->journalArMovement($customer, 'Credit Note Refund', 'debit', '<', $from->toDateString());

        return $invoicesBefore + $debitNotesBefore + $refundsBefore - $paymentsBefore - $creditsBefore - $depositAppsBefore;
    }

    /**
     * Chronological list of events in the period:
     *   - one entry per invoice issued in the range
     *   - one entry per payment received in the range
     *   - one entry per credit note issued in the range
     *
     * Each entry has type, date, reference, description, charge, payment, credit.
     * Running balance is filled in by the caller.
     *
     * @return array<int, array<string, mixed>>
     */
    private function activity(Customer $customer, CarbonInterface $from, CarbonInterface $to): array
    {
        // 1. Invoices issued in range
        $invoices = DB::table('invoices')
            ->select(['id', 'invoice_number', 'issue_date', 'due_date', 'total_amount', 'currency', 'status'])
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['draft', 'void'])
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('deleted_at')
            ->get()
            ->map(fn ($i) => [
                'type'        => 'invoice',
                'date'        => $i->issue_date,
                'reference'   => $i->invoice_number,
                'description' => 'Invoice ' . $i->invoice_number . ($i->due_date ? ' (due ' . $i->due_date . ')' : ''),
                'charge'      => (float) $i->total_amount,
                'payment'     => 0.0,
                'credit'      => 0.0,
                'currency'    => $i->currency ?? 'MYR',
                'invoice_id'  => $i->id,
                'status'      => $i->status,
            ]);

        // 2. Payments received in range — derive amount from the credit on AR (1100).
        $payments = DB::table('journal_entries as je')
            ->select([
                'je.id as journal_entry_id',
                'je.date',
                'je.description',
                'je.reference_id as invoice_id',
                'i.invoice_number',
                DB::raw('SUM(ji.credit) as payment_amount'),
            ])
            ->join('journal_items as ji', 'ji.journal_entry_id', '=', 'je.id')
            ->join('invoices as i', 'i.id', '=', 'je.reference_id')
            ->where('je.reference_type', 'Invoice Payment')
            ->where('i.customer_id', $customer->id)
            ->where('ji.account_code', '1100')
            ->whereBetween('je.date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('je.deleted_at')
            ->groupBy('je.id', 'je.date', 'je.description', 'je.reference_id', 'i.invoice_number')
            ->get()
            ->map(fn ($p) => [
                'type'        => 'payment',
                'date'        => $p->date,
                'reference'   => 'Payment',
                'description' => 'Payment received for ' . $p->invoice_number,
                'charge'      => 0.0,
                'payment'     => (float) $p->payment_amount,
                'credit'      => 0.0,
                'currency'    => null,
                'invoice_id'  => $p->invoice_id,
                'status'      => null,
            ]);

        // 3. Credit notes issued in range
        $credits = DB::table('credit_notes as cn')
            ->select(['cn.id', 'cn.cn_number', 'cn.issue_date', 'cn.invoice_id', 'cn.total_amount', 'cn.reason_description', 'i.invoice_number'])
            ->leftJoin('invoices as i', 'i.id', '=', 'cn.invoice_id')
            ->where('cn.customer_id', $customer->id)
            ->where('cn.status', '!=', 'void')
            ->whereBetween('cn.issue_date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('cn.deleted_at')
            ->get()
            ->map(fn ($c) => [
                'type'        => 'credit_note',
                'date'        => $c->issue_date,
                'reference'   => $c->cn_number,
                'description' => 'Credit note ' . $c->cn_number . ($c->invoice_number ? ' against ' . $c->invoice_number : '') . ($c->reason_description ? ' — ' . $c->reason_description : ''),
                'charge'      => 0.0,
                'payment'     => 0.0,
                'credit'      => (float) $c->total_amount,
                'currency'    => null,
                'invoice_id'  => $c->invoice_id,
                'status'      => null,
            ]);

        // Sort all events by date, then put charges before payments on the same day.
        // (Conventional accounting style: invoices listed first, then payments / credits applied.)
        $sortKey = [
            'invoice' => 0,
            'debit_note' => 1,
            'credit_note' => 2,
            'payment' => 3,
            'deposit_application' => 4,
            'credit_note_refund' => 5,
        ];

        return $invoices
            ->concat($this->debitNoteActivity($customer, $from, $to))
            ->concat($payments)
            ->concat($this->depositApplicationActivity($customer, $from, $to))
            ->concat($credits)
            ->concat($this->creditRefundActivity($customer, $from, $to))
            ->sortBy(fn ($e) => Carbon::parse($e['date'])->timestamp . '-' . ($sortKey[$e['type']] ?? 9))
            ->values()
            ->all();
    }

    private function debitNoteActivity(Customer $customer, CarbonInterface $from, CarbonInterface $to)
    {
        if (! Schema::hasTable('debit_notes')) {
            return collect();
        }

        return DB::table('debit_notes')
            ->select(['id', 'dn_number', 'issue_date', 'invoice_id', 'total_amount'])
            ->where('customer_id', $customer->id)
            ->where('status', '!=', 'void')
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('deleted_at')
            ->get()
            ->map(fn ($d) => [
                'type'        => 'debit_note',
                'date'        => $d->issue_date,
                'reference'   => $d->dn_number,
                'description' => 'Debit note '.$d->dn_number,
                'charge'      => (float) $d->total_amount,
                'payment'     => 0.0,
                'credit'      => 0.0,
                'currency'    => null,
                'invoice_id'  => $d->invoice_id,
                'status'      => null,
            ]);
    }

    private function depositApplicationActivity(Customer $customer, CarbonInterface $from, CarbonInterface $to)
    {
        return $this->journalEvents($customer, 'AR Deposit Application', $from, $to, 'credit')
            ->map(fn ($p) => [
                'type'        => 'deposit_application',
                'date'        => $p['date'],
                'reference'   => 'Knock-off',
                'description' => $p['description'] ?: 'Deposit applied',
                'charge'      => 0.0,
                'payment'     => $p['amount'],
                'credit'      => 0.0,
                'currency'    => null,
                'invoice_id'  => $p['invoice_id'],
                'status'      => null,
            ]);
    }

    private function creditRefundActivity(Customer $customer, CarbonInterface $from, CarbonInterface $to)
    {
        return $this->journalEvents($customer, 'Credit Note Refund', $from, $to, 'debit')
            ->map(fn ($p) => [
                'type'        => 'credit_note_refund',
                'date'        => $p['date'],
                'reference'   => 'Refund',
                'description' => $p['description'] ?: 'Credit note refund',
                'charge'      => $p['amount'],
                'payment'     => 0.0,
                'credit'      => 0.0,
                'currency'    => null,
                'invoice_id'  => $p['invoice_id'],
                'status'      => null,
            ]);
    }

    /**
     * AR (1100) movement from a journal type. Refunds debit AR; payments credit it.
     */
    private function journalArMovement(Customer $customer, string $referenceType, string $side, string $operator, string $date): float
    {
        if ($referenceType === 'AR Deposit Application' && ! Schema::hasTable('ar_deposits')) {
            return 0.0;
        }
        if ($referenceType === 'Credit Note Refund' && ! Schema::hasTable('credit_note_refunds')) {
            return 0.0;
        }

        $query = DB::table('journal_entries as je')
            ->join('journal_items as ji', 'ji.journal_entry_id', '=', 'je.id')
            ->where('je.reference_type', $referenceType)
            ->where('ji.account_code', '1100')
            ->whereDate('je.date', $operator, $date)
            ->whereNull('je.deleted_at');

        $query = $this->constrainJournalToCustomer($query, $customer, $referenceType);

        return (float) $query->sum($side === 'debit' ? 'ji.debit' : 'ji.credit');
    }

    private function journalEvents(Customer $customer, string $referenceType, CarbonInterface $from, CarbonInterface $to, string $side)
    {
        if ($referenceType === 'AR Deposit Application' && ! Schema::hasTable('ar_deposits')) {
            return collect();
        }
        if ($referenceType === 'Credit Note Refund' && ! Schema::hasTable('credit_note_refunds')) {
            return collect();
        }

        $amountExpr = $side === 'debit' ? 'SUM(ji.debit) as amount' : 'SUM(ji.credit) as amount';
        $query = DB::table('journal_entries as je')
            ->select([
                'je.id as journal_entry_id',
                'je.date',
                'je.description',
                'je.reference_id',
                DB::raw($amountExpr),
            ])
            ->join('journal_items as ji', 'ji.journal_entry_id', '=', 'je.id')
            ->where('je.reference_type', $referenceType)
            ->where('ji.account_code', '1100')
            ->whereBetween('je.date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('je.deleted_at')
            ->groupBy('je.id', 'je.date', 'je.description', 'je.reference_id');

        $query = $this->constrainJournalToCustomer($query, $customer, $referenceType);

        return $query->get()->map(fn ($row) => [
            'date'        => $row->date,
            'description' => $row->description,
            'amount'      => (float) $row->amount,
            'invoice_id'  => $referenceType === 'AR Deposit Application' ? null : $row->reference_id,
        ]);
    }

    private function constrainJournalToCustomer($query, Customer $customer, string $referenceType)
    {
        if ($referenceType === 'AR Deposit Application' && Schema::hasTable('ar_deposits')) {
            return $query
                ->join('ar_deposits as d', 'd.id', '=', 'je.reference_id')
                ->where('d.customer_id', $customer->id);
        }
        if ($referenceType === 'Credit Note Refund' && Schema::hasTable('credit_note_refunds')) {
            return $query
                ->join('credit_note_refunds as r', 'r.id', '=', 'je.reference_id')
                ->join('credit_notes as cn', 'cn.id', '=', 'r.credit_note_id')
                ->where('cn.customer_id', $customer->id);
        }

        return $query;
    }

    /**
     * Default 30-day window ending today (handy for the controller).
     */
    public function defaultWindow(): array
    {
        return [
            'from' => now()->startOfMonth()->toDateString(),
            'to'   => now()->toDateString(),
        ];
    }
}
