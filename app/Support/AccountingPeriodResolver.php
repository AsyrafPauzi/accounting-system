<?php

namespace App\Support;

use App\Models\AccountingPeriod;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;

final class AccountingPeriodResolver
{
    /** @var array<string, true> */
    private static array $ensuredForTenant = [];

    public static function ensurePeriodsExist(int $monthsBack = 12, int $monthsForward = 3): void
    {
        if (! Schema::hasTable('accounting_periods')) {
            return;
        }

        $tenantKey = (function_exists('tenancy') && tenancy()->initialized)
            ? (string) tenant('id')
            : 'default';

        if (isset(self::$ensuredForTenant[$tenantKey])) {
            return;
        }

        $start = Carbon::today()->startOfMonth()->subMonths($monthsBack);
        $end = Carbon::today()->startOfMonth()->addMonths($monthsForward);

        while ($start <= $end) {
            $periodStart = $start->copy()->startOfMonth();
            $periodEnd = $start->copy()->endOfMonth();

            $exists = AccountingPeriod::query()
                ->whereDate('start_date', $periodStart->toDateString())
                ->whereDate('end_date', $periodEnd->toDateString())
                ->exists();

            if (! $exists) {
                try {
                    AccountingPeriod::create([
                        'start_date' => $periodStart->toDateString(),
                        'end_date'   => $periodEnd->toDateString(),
                        'label'      => $periodStart->format('M Y'),
                        'status'     => 'open',
                    ]);
                } catch (UniqueConstraintViolationException) {
                    // Another request or nested call seeded the same month.
                }
            }

            $start->addMonth();
        }

        self::$ensuredForTenant[$tenantKey] = true;
    }

    public static function forDate(string $date): ?AccountingPeriod
    {
        if (! Schema::hasTable('accounting_periods')) {
            return null;
        }

        self::ensurePeriodsExist();

        return AccountingPeriod::query()
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
    }

    public static function assertOpenForDate(string $date): void
    {
        $period = self::forDate($date);
        if ($period && $period->isClosed()) {
            throw new \LogicException('Accounting period '.$period->label.' is closed.');
        }
    }
}
