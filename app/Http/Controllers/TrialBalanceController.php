<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalItem;
use App\Support\PostedJournalScope;
use App\Support\ReportPeriod;
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

        $resolved = ReportPeriod::asOf(
            $request->input('preset'),
            $request->input('as_of_date')
        );
        $asOfDate = $resolved['as_of'];

        // Calculate balances per account up to the specified date
        $balances = JournalItem::query()
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.date', '<=', $asOfDate);
        PostedJournalScope::apply($balances, 'journal_entries');
        $balances = $balances->select(
                'journal_items.account_code',
                DB::raw('SUM(journal_items.debit) as total_debit'),
                DB::raw('SUM(journal_items.credit) as total_credit')
            )
            ->groupBy('journal_items.account_code')
            ->get()
            ->keyBy('account_code');

        $accounts = Account::orderBy('code')->get();

        // Standard Trial Balance presentation: each account shows ONE balance,
        // not the cumulative debit and credit movements. Net = total debits –
        // total credits across the lifetime of the account up to `as_of_date`.
        //
        //   net > 0  → Debit column (typical for Assets & Expenses)
        //   net < 0  → Credit column (typical for Liabilities, Equity, Income)
        //   net = 0  → account is fully cleared and is hidden from the report
        //
        // Showing both Dr and Cr movements per account (the previous behaviour)
        // was confusing — readers couldn't tell at a glance who owed who. The
        // grand totals still tie out because Σ(net debits) = Σ(net credits) in
        // a balanced ledger.
        $trialBalance = $accounts->map(function ($account) use ($balances) {
            $balance = $balances->get($account->code);

            $totalDebit  = $balance ? (float) $balance->total_debit  : 0.0;
            $totalCredit = $balance ? (float) $balance->total_credit : 0.0;

            $net = round($totalDebit - $totalCredit, 2);

            return [
                'id'     => $account->id,
                'code'   => $account->code,
                'name'   => $account->name,
                'type'   => $account->type,
                'debit'  => $net > 0 ? $net      : 0.0,
                'credit' => $net < 0 ? abs($net) : 0.0,
            ];
        })->filter(function ($item) {
            // Hide cleared accounts (a paid-off Accounts Receivable, a fully
            // remitted EPF Payable, etc.) — they add nothing to the report.
            return $item['debit'] > 0 || $item['credit'] > 0;
        })->values();

        $totalDebit  = $trialBalance->sum('debit');
        $totalCredit = $trialBalance->sum('credit');

        return Inertia::render('Reports/TrialBalance', [
            'trialBalance' => $trialBalance,
            'totals' => [
                'debit' => round($totalDebit, 2),
                'credit' => round($totalCredit, 2),
                'difference' => round(abs($totalDebit - $totalCredit), 2),
            ],
            'filters' => [
                'preset' => $resolved['preset'],
                'as_of_date' => $asOfDate,
            ],
        ]);
    }
}
