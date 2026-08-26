<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\JournalItem;
use App\Support\PostedJournalScope;
use App\Support\ReportCompare;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

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
        $compare = $this->resolveCompare($request);
        $data = $this->buildComparedTrialBalanceData($asOfDate, $compare);

        return Inertia::render('Reports/TrialBalance', [
            ...$data,
            'filters' => [
                'preset' => $resolved['preset'],
                'as_of_date' => $asOfDate,
                'compare' => $compare,
            ],
        ]);
    }

    /**
     * @return array{trialBalance: \Illuminate\Support\Collection, totals: array{debit: float, credit: float, difference: float}}
     */
    protected function buildTrialBalanceData(string $asOfDate): array
    {
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

        $trialBalance = $accounts->map(function ($account) use ($balances) {
            $balance = $balances->get($account->code);

            $totalDebit = $balance ? (float) $balance->total_debit : 0.0;
            $totalCredit = $balance ? (float) $balance->total_credit : 0.0;

            $net = round($totalDebit - $totalCredit, 2);

            return [
                'id'     => $account->id,
                'code'   => $account->code,
                'name'   => $account->name,
                'type'   => $account->type,
                'debit'  => $net > 0 ? $net : 0.0,
                'credit' => $net < 0 ? abs($net) : 0.0,
            ];
        })->filter(function ($item) {
            return $item['debit'] > 0 || $item['credit'] > 0;
        })->values();

        $totalDebit = $trialBalance->sum('debit');
        $totalCredit = $trialBalance->sum('credit');

        return [
            'trialBalance' => $trialBalance,
            'totals' => [
                'debit'      => round($totalDebit, 2),
                'credit'     => round($totalCredit, 2),
                'difference' => round(abs($totalDebit - $totalCredit), 2),
            ],
        ];
    }

    protected function buildComparedTrialBalanceData(string $asOfDate, string $compare): array
    {
        $current = $this->buildTrialBalanceData($asOfDate);
        $current['compare'] = $compare;
        $current['compare_label'] = null;
        $current['compare_as_of_date'] = null;
        $current['compare_totals'] = null;

        if ($compare === 'none') {
            return $current;
        }

        $compareDate = ReportPeriod::compareAsOfDate($asOfDate, $compare);
        if (! $compareDate) {
            return $current;
        }

        $prior = $this->buildTrialBalanceData($compareDate);
        $merged = ReportCompare::mergeTrialBalance(
            $current['trialBalance']->all(),
            $prior['trialBalance']->all(),
        );

        $current['trialBalance'] = collect($merged);
        $current['compare_label'] = $compare === 'last_year' ? 'Same date last year' : 'Prior month end';
        $current['compare_as_of_date'] = $compareDate;
        $current['compare_totals'] = $prior['totals'];

        return $current;
    }

    protected function resolveCompare(Request $request): string
    {
        $compare = $request->input('compare', 'previous');

        return in_array($compare, ['previous', 'last_year', 'none'], true) ? $compare : 'previous';
    }
}
