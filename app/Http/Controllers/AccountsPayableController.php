<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Bill;
use App\Services\BillService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountsPayableController extends Controller
{
    public function __construct(private BillService $billService) {}

    /**
     * AP dashboard: unpaid/partially paid bills with aging and totals.
     */
    public function index(Request $request): Response
    {
        $bills = Bill::with('supplier')
            ->whereIn('status', ['unpaid', 'partially paid'])
            ->orderBy('due_date')
            ->get();

        $today = now()->startOfDay();
        $agingBuckets = [
            'current' => ['label' => 'Current', 'amount' => 0, 'count' => 0],
            '1-30' => ['label' => '1–30 days overdue', 'amount' => 0, 'count' => 0],
            '31-60' => ['label' => '31–60 days overdue', 'amount' => 0, 'count' => 0],
            '61-90' => ['label' => '61–90 days overdue', 'amount' => 0, 'count' => 0],
            '90+' => ['label' => '90+ days overdue', 'amount' => 0, 'count' => 0],
        ];

        $totalPayable = 0;
        $overdueCount = 0;

        foreach ($bills as $bill) {
            $balanceDue = $this->billService->remainingBalance($bill);
            if ($balanceDue <= 0) {
                continue;
            }
            $totalPayable += $balanceDue;
            $dueDate = $bill->due_date ? $bill->due_date->startOfDay() : $today;
            $daysOverdue = $today->diffInDays($dueDate, false);
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
            $bill->balance_due = round($balanceDue, 2);
            $bill->supplier_name = $bill->supplier?->name ?? '—';
            $bill->aging_bucket = $bucket;
            $bill->aging_label = $agingBuckets[$bucket]['label'];
        }

        foreach ($agingBuckets as $key => $bucket) {
            $agingBuckets[$key]['amount'] = round($bucket['amount'], 2);
        }

        $bankAccounts = Account::bankOrCash()->active()->orderBy('code')->get(['code', 'name'])->map(fn ($a) => ['value' => $a->code, 'label' => "{$a->name} ({$a->code})"])->values()->all();

        return Inertia::render('AccountsPayable/Index', [
            'bills' => $bills,
            'summary' => [
                'total_payable' => round($totalPayable, 2),
                'overdue_count' => $overdueCount,
                'aging_breakdown' => $agingBuckets,
            ],
            'bankAccounts' => $bankAccounts,
        ]);
    }
}
