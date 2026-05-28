<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgedReceivablesController extends Controller
{
    /**
     * Aged Receivables: Who hasn't paid you — 30, 60, 90+ days overdue.
     */
    public function index(Request $request): Response
    {
        $invoices = Invoice::with('customer')
            ->whereIn('status', ['unpaid', 'partially paid'])
            ->orderBy('due_date')
            ->get();

        $today = now()->startOfDay();
        $agingBuckets = [
            'current' => ['label' => 'Current (not yet due)', 'amount' => 0, 'count' => 0],
            '1-30' => ['label' => '1–30 days overdue', 'amount' => 0, 'count' => 0],
            '31-60' => ['label' => '31–60 days overdue', 'amount' => 0, 'count' => 0],
            '61-90' => ['label' => '61–90 days overdue', 'amount' => 0, 'count' => 0],
            '90+' => ['label' => '90+ days overdue', 'amount' => 0, 'count' => 0],
        ];

        $totalReceivable = 0;
        $overdueCount = 0;

        foreach ($invoices as $invoice) {
            $balanceDue = (float) $invoice->total_amount - (float) $invoice->amount_paid;
            if ($balanceDue <= 0) {
                continue;
            }
            $totalReceivable += $balanceDue;

            $dueDate = $invoice->due_date
                ? Carbon::parse($invoice->due_date)->startOfDay()
                : $today;
            $daysOverdue = (int) $today->diffInDays($dueDate, false);
            if ($daysOverdue < 0) {
                $daysOverdue = (int) abs($daysOverdue);
                $overdueCount++;
            } else {
                $daysOverdue = 0;
            }

            if ($daysOverdue === 0) {
                $bucket = 'current';
            } elseif ($daysOverdue <= 30) {
                $bucket = '1-30';
            } elseif ($daysOverdue <= 60) {
                $bucket = '31-60';
            } elseif ($daysOverdue <= 90) {
                $bucket = '61-90';
            } else {
                $bucket = '90+';
            }

            $agingBuckets[$bucket]['amount'] += $balanceDue;
            $agingBuckets[$bucket]['count']++;

            $invoice->balance_due = round($balanceDue, 2);
            $invoice->customer_name = $invoice->customer?->name ?? '—';
            $invoice->customer_email = $invoice->customer?->email ?? null;
            $invoice->aging_bucket = $bucket;
            $invoice->aging_label = $agingBuckets[$bucket]['label'];
            $invoice->days_overdue = $daysOverdue;
        }

        foreach ($agingBuckets as $key => $bucket) {
            $agingBuckets[$key]['amount'] = round($bucket['amount'], 2);
        }

        $bankAccounts = Account::bankOrCash()->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->name} ({$a->code})"])->values()->all();

        return Inertia::render('AgedReceivables/Index', [
            'invoices' => $invoices,
            'summary' => [
                'total_receivable' => round($totalReceivable, 2),
                'overdue_count' => $overdueCount,
                'aging_breakdown' => $agingBuckets,
            ],
            'bankAccounts' => $bankAccounts,
        ]);
    }
}
