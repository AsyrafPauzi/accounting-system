<?php

namespace App\Support;

use Carbon\Carbon;

final class SubscriptionPeriod
{
    /**
     * Next billing window, anchored on the prior period end (no drift from "today").
     *
     * @return array{period_start:string,period_end:string}
     */
    public static function nextWindow(string $interval, string $priorPeriodEnd): array
    {
        $start = Carbon::parse($priorPeriodEnd)->startOfDay();
        $end = match ($interval) {
            'yearly' => $start->copy()->addYearNoOverflow(),
            default => $start->copy()->addMonthNoOverflow(),
        };

        return [
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
        ];
    }

    public static function graceDeadline(string $periodEnd, ?int $graceDays = null): string
    {
        $days = $graceDays ?? (int) config('subscriptions.grace_days', 7);

        return Carbon::parse($periodEnd)->addDays($days)->toDateString();
    }

    /**
     * True when today is within the lead window before period end, or period end is already past.
     */
    public static function isDue(?string $periodEnd, int $leadDays, string $today): bool
    {
        if (! $periodEnd) {
            return false;
        }

        $deadline = Carbon::parse($periodEnd)->startOfDay();
        $todayC = Carbon::parse($today)->startOfDay();

        return $todayC->gte($deadline->copy()->subDays($leadDays));
    }

    /**
     * @return 'none'|'past_due'|'expired'
     */
    public static function expireAction(string $status, ?string $periodEnd, string $today, ?int $graceDays = null): string
    {
        if (! $periodEnd) {
            return 'none';
        }

        $todayC = Carbon::parse($today)->startOfDay();
        $ends = Carbon::parse($periodEnd)->startOfDay();

        if ($status === 'active' && $todayC->gt($ends)) {
            return 'past_due';
        }

        if ($status === 'past_due') {
            $grace = Carbon::parse(self::graceDeadline($periodEnd, $graceDays))->startOfDay();
            if ($todayC->gt($grace)) {
                return 'expired';
            }
        }

        return 'none';
    }
}
