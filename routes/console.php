<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Daily 06:00 server-time: walk every tenant and materialise draft invoices
 * for any recurring-invoice template whose next_run_date is today or earlier.
 *
 * `withoutOverlapping` so a long-running tenant loop never starts a second
 * copy on top of itself. `runInBackground` is intentionally omitted — we want
 * the tenant context to settle before the next scheduled task runs.
 */
Schedule::command('invoices:generate-recurring')
    ->dailyAt('06:00')
    ->withoutOverlapping(60)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/recurring-invoices.log'));
