<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

final class ReportPeriod
{
    public const PRESETS = ['this_month', 'last_month', 'this_quarter', 'ytd', 'this_sst_period'];

    /**
     * @return array{preset:string,date_from:string,date_to:string}
     */
    public static function range(?string $preset, ?string $from, ?string $to, ?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now ?? now())->startOfDay();

        if (in_array($preset, self::PRESETS, true)) {
            if ($preset === 'this_sst_period') {
                $sst = self::sstPeriod($now);

                return ['preset' => $preset, 'date_from' => $sst['date_from'], 'date_to' => $sst['date_to']];
            }
            [$start, $end] = self::presetRange($preset, $now);

            return ['preset' => $preset, 'date_from' => $start->toDateString(), 'date_to' => $end->toDateString()];
        }

        $dateFrom = $from ?: $now->copy()->startOfMonth()->toDateString();
        $dateTo = $to ?: $now->toDateString();
        $detected = self::detectPreset($dateFrom, $dateTo, $now);

        return ['preset' => $detected, 'date_from' => $dateFrom, 'date_to' => $dateTo];
    }

    /**
     * @return array{preset:string,as_of:string}
     */
    public static function asOf(?string $preset, ?string $date, ?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now ?? now())->startOfDay();
        if ($preset === 'last_month') {
            return ['preset' => $preset, 'as_of' => $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()];
        }
        if (in_array($preset, ['this_month', 'this_quarter', 'ytd'], true)) {
            return ['preset' => $preset, 'as_of' => $now->toDateString()];
        }
        $asOf = $date ?: $now->toDateString();

        return ['preset' => $preset ?: 'custom', 'as_of' => $asOf];
    }

    /**
     * Malaysia SST 2-calendar-month window containing $now. date_to is period end (not today).
     *
     * @return array{date_from:string,date_to:string}
     */
    public static function sstPeriod(?CarbonInterface $now = null): array
    {
        $now = Carbon::parse($now ?? now())->startOfDay();
        $month = (int) $now->month; // 1-12
        $startMonth = $month % 2 === 0 ? $month - 1 : $month;
        $start = $now->copy()->month($startMonth)->startOfMonth();
        $end = $start->copy()->addMonth()->endOfMonth();

        return ['date_from' => $start->toDateString(), 'date_to' => $end->toDateString()];
    }

    /**
     * Inclusive previous window of the same number of days, ending the day before $from.
     *
     * @return array{date_from:string,date_to:string}
     */
    public static function previousOfSameLength(string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        $days = $start->diffInDays($end) + 1;
        $prevEnd = $start->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays($days - 1);

        return ['date_from' => $prevStart->toDateString(), 'date_to' => $prevEnd->toDateString()];
    }

    /**
     * @return array{date_from:string,date_to:string}
     */
    public static function lastYearSameDates(string $from, string $to): array
    {
        return [
            'date_from' => Carbon::parse($from)->subYear()->toDateString(),
            'date_to' => Carbon::parse($to)->subYear()->toDateString(),
        ];
    }

    public static function detectPreset(string $from, string $to, ?CarbonInterface $now = null): string
    {
        $now = Carbon::parse($now ?? now())->startOfDay();
        foreach (['this_month', 'last_month', 'this_quarter', 'ytd'] as $preset) {
            [$s, $e] = self::presetRange($preset, $now);
            if ($from === $s->toDateString() && $to === $e->toDateString()) {
                return $preset;
            }
        }
        $sst = self::sstPeriod($now);
        if ($from === $sst['date_from'] && $to === $sst['date_to']) {
            return 'this_sst_period';
        }

        return 'custom';
    }

    /**
     * @return array{0:Carbon,1:Carbon}
     */
    private static function presetRange(string $preset, CarbonInterface $now): array
    {
        $today = Carbon::parse($now)->startOfDay();

        return match ($preset) {
            'this_month' => [$today->copy()->startOfMonth(), $today],
            'last_month' => [
                $today->copy()->subMonthNoOverflow()->startOfMonth(),
                $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay(),
            ],
            'this_quarter' => [$today->copy()->firstOfQuarter(), $today],
            'ytd' => [$today->copy()->startOfYear(), $today],
            default => [$today->copy()->startOfMonth(), $today],
        };
    }
}
