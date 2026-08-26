<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class CurrentYearEarnings
{
    /**
     * Net income (revenue − expenses) from fiscal year start through as-at date, posted journals only.
     */
    public static function amountAsOf(string $asAtDate, int $financialYearStartMonth = 1): float
    {
        $asAt = Carbon::parse($asAtDate)->startOfDay();
        $fyStart = self::fiscalYearStart($asAt, $financialYearStartMonth);

        $rows = DB::table('journal_items')
            ->join('journal_entries', 'journal_items.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_items.account_code', '=', 'accounts.code')
            ->whereIn('accounts.type', ['income', 'expense'])
            ->where('journal_entries.date', '>=', $fyStart->toDateString())
            ->where('journal_entries.date', '<=', $asAt->toDateString())
            ->where('journal_entries.status', 'posted')
            ->select(
                'accounts.type',
                DB::raw('SUM(journal_items.debit) as total_debit'),
                DB::raw('SUM(journal_items.credit) as total_credit')
            )
            ->groupBy('accounts.type')
            ->get();

        $revenue = 0.0;
        $expenses = 0.0;

        foreach ($rows as $row) {
            $debit = (float) $row->total_debit;
            $credit = (float) $row->total_credit;
            if ($row->type === 'income') {
                $revenue += ($credit - $debit);
            } else {
                $expenses += ($debit - $credit);
            }
        }

        return round($revenue - $expenses, 2);
    }

    private static function fiscalYearStart(Carbon $asAt, int $startMonth): Carbon
    {
        $startMonth = max(1, min(12, $startMonth));
        $year = (int) $asAt->year;
        if ((int) $asAt->month < $startMonth) {
            $year--;
        }

        return Carbon::create($year, $startMonth, 1)->startOfDay();
    }
}
