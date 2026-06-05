<?php

namespace App\Services;

use App\Models\Customer;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

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
            ->whereDate('issue_date', '<', $from->toDateString())
            ->whereNull('deleted_at')
            ->sum('total_amount');

        $paymentsBefore = (float) DB::table('journal_entries as je')
            ->join('journal_items as ji', 'ji.journal_entry_id', '=', 'je.id')
            ->join('invoices as i', 'i.id', '=', 'je.reference_id')
            ->where('je.reference_type', 'Invoice Payment')
            ->where('i.customer_id', $customer->id)
            ->where('ji.account_code', '1100')
            ->whereDate('je.date', '<', $from->toDateString())
            ->whereNull('je.deleted_at')
            ->sum('ji.credit');

        $creditsBefore = (float) DB::table('credit_notes')
            ->where('customer_id', $customer->id)
            ->where('status', '!=', 'void')
            ->whereDate('issue_date', '<', $from->toDateString())
            ->whereNull('deleted_at')
            ->sum('total_amount');

        return $invoicesBefore - $paymentsBefore - $creditsBefore;
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
        $sortKey = ['invoice' => 0, 'credit_note' => 1, 'payment' => 2];

        return $invoices->concat($payments)->concat($credits)
            ->sortBy(fn ($e) => Carbon::parse($e['date'])->timestamp . '-' . ($sortKey[$e['type']] ?? 9))
            ->values()
            ->all();
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
