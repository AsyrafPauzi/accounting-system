<?php

namespace App\Services;

use App\Models\Supplier;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SupplierStatementService
{
    public function build(Supplier $supplier, CarbonInterface $from, CarbonInterface $to): array
    {
        $fromDate = $from->copy()->startOfDay();
        $toDate = $to->copy()->endOfDay();
        $opening = $this->openingBalance($supplier, $fromDate);
        $activity = $this->activity($supplier, $fromDate, $toDate);

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
            'supplier'        => $supplier,
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

    private function openingBalance(Supplier $supplier, CarbonInterface $from): float
    {
        $bills = (float) DB::table('bills')
            ->where('supplier_id', $supplier->id)
            ->whereNotIn('status', ['draft', 'void'])
            ->whereDate('bill_date', '<', $from->toDateString())
            ->whereNull('deleted_at')
            ->sum('total_amount');

        $payments = Schema::hasTable('bill_payments')
            ? (float) DB::table('bill_payments as bp')
                ->join('bills as b', 'b.id', '=', 'bp.bill_id')
                ->where('b.supplier_id', $supplier->id)
                ->whereDate('bp.payment_date', '<', $from->toDateString())
                ->whereNull('bp.deleted_at')
                ->sum('bp.amount')
            : (float) DB::table('bills')
                ->where('supplier_id', $supplier->id)
                ->whereNotIn('status', ['draft', 'void'])
                ->whereDate('bill_date', '<', $from->toDateString())
                ->whereNull('deleted_at')
                ->sum('amount_paid');

        $credits = Schema::hasTable('supplier_credit_notes')
            ? (float) DB::table('supplier_credit_notes')
                ->where('supplier_id', $supplier->id)
                ->where('status', '!=', 'void')
                ->whereDate('issue_date', '<', $from->toDateString())
                ->whereNull('deleted_at')
                ->sum('total_amount')
            : 0.0;

        $debits = Schema::hasTable('supplier_debit_notes')
            ? (float) DB::table('supplier_debit_notes')
                ->where('supplier_id', $supplier->id)
                ->where('status', '!=', 'void')
                ->whereDate('issue_date', '<', $from->toDateString())
                ->whereNull('deleted_at')
                ->sum('total_amount')
            : 0.0;

        $deposits = Schema::hasTable('ap_deposit_applications')
            ? (float) DB::table('ap_deposit_applications as a')
                ->join('ap_deposits as d', 'd.id', '=', 'a.ap_deposit_id')
                ->where('d.supplier_id', $supplier->id)
                ->whereDate('d.payment_date', '<', $from->toDateString())
                ->sum('a.amount')
            : 0.0;

        $refunds = Schema::hasTable('supplier_credit_note_refunds')
            ? (float) DB::table('supplier_credit_note_refunds as r')
                ->join('supplier_credit_notes as cn', 'cn.id', '=', 'r.supplier_credit_note_id')
                ->where('cn.supplier_id', $supplier->id)
                ->whereDate('r.payment_date', '<', $from->toDateString())
                ->sum('r.amount')
            : 0.0;

        return $bills + $debits + $refunds - $payments - $credits - $deposits;
    }

    private function activity(Supplier $supplier, CarbonInterface $from, CarbonInterface $to): array
    {
        $bills = DB::table('bills')
            ->where('supplier_id', $supplier->id)
            ->whereNotIn('status', ['draft', 'void'])
            ->whereBetween('bill_date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('deleted_at')
            ->get()
            ->map(fn ($b) => [
                'type' => 'bill', 'date' => $b->bill_date, 'reference' => $b->bill_number,
                'description' => 'Bill '.$b->bill_number, 'charge' => (float) $b->total_amount, 'payment' => 0.0, 'credit' => 0.0,
            ]);

        $payments = Schema::hasTable('bill_payments')
            ? DB::table('bill_payments as bp')
                ->join('bills as b', 'b.id', '=', 'bp.bill_id')
                ->where('b.supplier_id', $supplier->id)
                ->whereBetween('bp.payment_date', [$from->toDateString(), $to->toDateString()])
                ->whereNull('bp.deleted_at')
                ->get(['bp.payment_date', 'bp.amount', 'b.bill_number'])
                ->map(fn ($p) => [
                    'type' => 'payment', 'date' => $p->payment_date, 'reference' => 'Payment',
                    'description' => 'Payment for '.$p->bill_number, 'charge' => 0.0, 'payment' => (float) $p->amount, 'credit' => 0.0,
                ])
            : collect();

        $credits = Schema::hasTable('supplier_credit_notes')
            ? DB::table('supplier_credit_notes')
                ->where('supplier_id', $supplier->id)
                ->where('status', '!=', 'void')
                ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
                ->whereNull('deleted_at')
                ->get()
                ->map(fn ($c) => [
                    'type' => 'credit_note', 'date' => $c->issue_date, 'reference' => $c->scn_number,
                    'description' => 'Supplier credit '.$c->scn_number, 'charge' => 0.0, 'payment' => 0.0, 'credit' => (float) $c->total_amount,
                ])
            : collect();

        $debits = Schema::hasTable('supplier_debit_notes')
            ? DB::table('supplier_debit_notes')
                ->where('supplier_id', $supplier->id)
                ->where('status', '!=', 'void')
                ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
                ->whereNull('deleted_at')
                ->get()
                ->map(fn ($d) => [
                    'type' => 'debit_note', 'date' => $d->issue_date, 'reference' => $d->sdn_number,
                    'description' => 'Supplier debit '.$d->sdn_number, 'charge' => (float) $d->total_amount, 'payment' => 0.0, 'credit' => 0.0,
                ])
            : collect();

        $knockoff = Schema::hasTable('ap_deposit_applications')
            ? DB::table('ap_deposit_applications as a')
                ->join('ap_deposits as d', 'd.id', '=', 'a.ap_deposit_id')
                ->join('bills as b', 'b.id', '=', 'a.bill_id')
                ->where('d.supplier_id', $supplier->id)
                ->whereBetween('d.payment_date', [$from->toDateString(), $to->toDateString()])
                ->get(['d.payment_date', 'a.amount', 'b.bill_number'])
                ->map(fn ($p) => [
                    'type' => 'deposit_application', 'date' => $p->payment_date, 'reference' => 'Knock-off',
                    'description' => 'Deposit applied to '.$p->bill_number, 'charge' => 0.0, 'payment' => (float) $p->amount, 'credit' => 0.0,
                ])
            : collect();

        $sort = ['bill' => 0, 'debit_note' => 1, 'credit_note' => 2, 'payment' => 3, 'deposit_application' => 4];

        return $bills->concat($debits)->concat($credits)->concat($payments)->concat($knockoff)
            ->sortBy(fn ($e) => Carbon::parse($e['date'])->timestamp.'-'.($sort[$e['type']] ?? 9))
            ->values()
            ->all();
    }
}
