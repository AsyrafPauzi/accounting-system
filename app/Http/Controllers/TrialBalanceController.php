<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class TrialBalanceController extends Controller
{
    /**
     * Display the Trial Balance report.
     */
    public function index(Request $request): Response
    {
        $this->authorize('general-ledger.view');

        $asOfDate = $request->input('as_of_date', now()->format('Y-m-d'));

        // Calculate balances per account up to the specified date
        $balances = JournalItem::query()
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->whereDate('journal_entries.date', '<=', $asOfDate)
            ->select(
                'journal_items.account_id',
                'journal_items.account_code',
                DB::raw('SUM(journal_items.debit) as total_debit'),
                DB::raw('SUM(journal_items.credit) as total_credit')
            )
            ->groupBy('journal_items.account_id', 'journal_items.account_code')
            ->get()
            ->keyBy('account_id');

        $accounts = Account::orderBy('code')->get();

        $trialBalance = $accounts->map(function ($account) use ($balances) {
            $balance = $balances->get($account->id);
            
            $debit = $balance ? (float) $balance->total_debit : 0;
            $credit = $balance ? (float) $balance->total_credit : 0;
            
            // Net balance calculation based on account type
            // Assets & Expenses usually have debit balances
            // Liabilities, Equity & Revenue usually have credit balances
            $netBalance = $debit - $credit;

            return [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'debit' => $debit,
                'credit' => $credit,
                'net_balance' => $netBalance,
            ];
        })->filter(function ($item) {
            // Only show accounts with activity
            return $item['debit'] != 0 || $item['credit'] != 0;
        })->values();

        $totalDebit = $trialBalance->sum('debit');
        $totalCredit = $trialBalance->sum('credit');

        return Inertia::render('Reports/TrialBalance', [
            'trialBalance' => $trialBalance,
            'totals' => [
                'debit' => round($totalDebit, 2),
                'credit' => round($totalCredit, 2),
                'difference' => round(abs($totalDebit - $totalCredit), 2),
            ],
            'filters' => [
                'as_of_date' => $asOfDate,
            ],
        ]);
    }
}
