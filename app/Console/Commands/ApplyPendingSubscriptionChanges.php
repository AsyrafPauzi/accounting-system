<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Applies any subscription whose current billing period has ended *and*
 * has a scheduled plan change waiting (`pending_plan_id`).
 *
 * Why a separate command from subscription:expire?
 *   - We need this to run *before* expiration. If we just expired the
 *     subscription first, we'd lose the pending plan signal and the tenant
 *     would silently fall off everything. The scheduler in
 *     routes/console.php runs this command first, then the expire pass.
 *
 * Behaviour by target plan type:
 *   - Free Startup tier → flip plan_id, reset period to today + 1 month,
 *     keep status = active. No money involved.
 *   - Other paid tier  → flip plan_id, set status to "pending" so the UI
 *     prompts the tenant to settle the new bill via Toyyibpay. We don't
 *     try to silently auto-charge through the gateway because the existing
 *     integration is one-shot bills, not recurring tokens.
 */
class ApplyPendingSubscriptionChanges extends Command
{
    protected $signature = 'subscription:apply-pending';

    protected $description = 'Apply scheduled plan changes (downgrades) when the current billing period has ended';

    public function handle(): int
    {
        $today = Carbon::now()->toDateString();

        $due = Subscription::query()
            ->whereNotNull('pending_plan_id')
            ->whereNotNull('current_period_ends_at')
            ->whereDate('current_period_ends_at', '<=', $today)
            ->with(['plan', 'pendingPlan'])
            ->get();

        if ($due->isEmpty()) {
            $this->info('No pending subscription changes to apply.');
            return self::SUCCESS;
        }

        $applied = 0;
        foreach ($due as $sub) {
            $newPlan = $sub->pendingPlan;
            if (! $newPlan) {
                $sub->update(['pending_plan_id' => null, 'pending_interval' => null]);
                continue;
            }

            $newInterval = $sub->pending_interval ?: $sub->interval;
            $isFree = strtolower((string) $newPlan->slug) === 'startup'
                || (float) $newPlan->price_monthly <= 0.0;

            $update = [
                'plan_id'          => $newPlan->id,
                'interval'         => $newInterval,
                'pending_plan_id'  => null,
                'pending_interval' => null,
            ];

            if ($isFree) {
                $update['status']                 = 'active';
                $update['gateway']                = 'system';
                $update['current_period_start']   = $today;
                $update['current_period_ends_at'] = Carbon::now()->addMonth()->toDateString();
            } else {
                // Paid plan downgrade: lower the tenant onto the new plan
                // immediately, but mark it pending so the UI nudges them to
                // pay through Toyyibpay before they get blocked.
                $update['status']  = 'pending';
                $update['gateway'] = 'toyyibpay';
            }

            $sub->update($update);
            $applied++;

            Log::info('Subscription pending change applied', [
                'subscription_id' => $sub->id,
                'tenant_id'       => $sub->tenant_id,
                'previous_plan'   => $sub->plan?->slug,
                'new_plan'        => $newPlan->slug,
                'new_interval'    => $newInterval,
                'is_free'         => $isFree,
            ]);
        }

        $this->info("Applied {$applied} pending subscription change(s).");
        return self::SUCCESS;
    }
}
