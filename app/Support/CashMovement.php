<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class CashMovement
{
    /**
     * @param  list<array{month:string,debit:float,credit:float}>  $lines
     * @return list<array{month:string,month_label:string,money_in:float,money_out:float,net:float}>
     */
    public static function chartByMonth(array $lines): array
    {
        $months = [];

        foreach ($lines as $line) {
            $month = $line['month'];
            $months[$month] ??= ['money_in' => 0.0, 'money_out' => 0.0];
            $months[$month]['money_in'] += (float) $line['debit'];
            $months[$month]['money_out'] += (float) $line['credit'];
        }

        ksort($months);

        return array_map(
            static fn (string $month, array $amounts): array => [
                'month' => $month,
                'month_label' => Carbon::createFromFormat('!Y-m', $month)->format('M Y'),
                'money_in' => round($amounts['money_in'], 2),
                'money_out' => round($amounts['money_out'], 2),
                'net' => round($amounts['money_in'] - $amounts['money_out'], 2),
            ],
            array_keys($months),
            array_values($months),
        );
    }

    /**
     * @param  list<array{month:string,month_label:string,money_in:float,money_out:float,net:float}>  $chart
     * @return array{money_in:float,money_out:float,net:float}
     */
    public static function totals(array $chart): array
    {
        $moneyIn = array_sum(array_column($chart, 'money_in'));
        $moneyOut = array_sum(array_column($chart, 'money_out'));

        return [
            'money_in' => round($moneyIn, 2),
            'money_out' => round($moneyOut, 2),
            'net' => round($moneyIn - $moneyOut, 2),
        ];
    }

    /**
     * @return list<array{month:string,month_label:string,money_in:float,money_out:float,net:float}>
     */
    public static function chartForPeriod(string $dateFrom, string $dateTo): array
    {
        $lines = self::bankOrCashItems()
            ->whereBetween('je.date', [$dateFrom, $dateTo])
            ->groupBy('month')
            ->get([
                DB::raw("DATE_FORMAT(je.date, '%Y-%m') as month"),
                DB::raw('SUM(ji.debit) as debit'),
                DB::raw('SUM(ji.credit) as credit'),
            ])
            ->map(static fn ($line): array => [
                'month' => (string) $line->month,
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
            ])
            ->all();

        return self::chartByMonth($lines);
    }

    public static function netAsOf(string $asOf): float
    {
        return round((float) self::bankOrCashItems()
            ->whereDate('je.date', '<=', $asOf)
            ->selectRaw('COALESCE(SUM(ji.debit - ji.credit), 0) as balance')
            ->value('balance'), 2);
    }

    private static function bankOrCashItems(): Builder
    {
        return DB::table('journal_items as ji')
            ->join('journal_entries as je', 'je.id', '=', 'ji.journal_entry_id')
            ->join('accounts as a', 'a.code', '=', 'ji.account_code')
            ->where('a.type', 'asset')
            ->whereIn('a.sub_type', ['bank', 'cash'])
            ->where('je.status', 'posted')
            ->whereNull('ji.deleted_at')
            ->whereNull('je.deleted_at');
    }
}
