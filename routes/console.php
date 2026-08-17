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

Schedule::command('bills:generate-recurring')
    ->dailyAt('06:05')
    ->withoutOverlapping(60)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/recurring-bills.log'));

Schedule::command('estimates:expire')
    ->dailyAt('06:15')
    ->withoutOverlapping(30)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/estimates-expire.log'));

Schedule::command('invoices:send-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping(60)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/invoice-reminders.log'));

Schedule::command('statements:send-monthly')
    ->monthlyOn(1, '07:00')
    ->withoutOverlapping(60)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/monthly-statements.log'));

/*
 * Daily 02:00 — flip any subscription whose current period has ended and has
 * a queued downgrade (`pending_plan_id`) onto the new plan. Runs *before*
 * subscription:expire so the pending plan isn't lost when the row would
 * otherwise be marked expired.
 */
Schedule::command('subscription:apply-pending')
    ->dailyAt('02:00')
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/subscription-pending.log'));

/*
 * Daily 02:15 — mark active subscriptions whose period_ends_at is past as
 * expired. Scheduled 15 minutes after apply-pending so any downgrades have
 * already moved to the new plan and reset their period.
 */
Schedule::command('subscription:expire')
    ->dailyAt('02:15')
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/subscription-expire.log'));

/*
 * Daily 03:00 — trim aged operational tables (sessions, password resets,
 * failed jobs, audit logs) and finalise scheduled account erasures whose
 * cooling-off has elapsed. Runs after the subscription jobs so a deletion
 * that landed on the same day as a renewal still gets the renewal logic
 * first.
 */
Schedule::command('retention:purge')
    ->dailyAt('03:00')
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/retention-purge.log'));

/*
 * Daily 04:00 — self-hosted heartbeat. Pings the publisher with the
 * license key + usage stats and picks up any revocations. The command
 * itself short-circuits on SaaS deployments, so this schedule is safe
 * to keep enabled in both shapes (one less mode-aware kernel branch
 * to maintain).
 */
Schedule::command('self-hosted:heartbeat')
    ->dailyAt('04:00')
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/self-hosted-heartbeat.log'));
