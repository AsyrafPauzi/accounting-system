<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\Carbon;

class AdminSubscriptionService
{
    /**
     * Assign or change a tenant's subscription plan.
     * Duration modes: 1_month, 1_year, lifetime, custom (uses ends_at).
     */
    public function assign(Tenant $tenant, array $validated): Subscription
    {
        $plan = Plan::findOrFail($validated['plan_id']);

        [$interval, $endsAt] = match ($validated['duration']) {
            'lifetime' => ['lifetime', null],
            '1_year'   => ['yearly', now()->addYear()->toDateString()],
            'custom'   => ['monthly', Carbon::parse($validated['ends_at'])->toDateString()],
            default    => ['monthly', now()->addMonth()->toDateString()], // 1_month
        };

        return Subscription::updateOrCreate(
            ['tenant_id' => $tenant->getKey()],
            [
                'plan_id'               => $plan->id,
                'status'                => 'active',
                'interval'              => $interval,
                'current_period_start'  => now()->toDateString(),
                'current_period_ends_at' => $endsAt,
                'gateway'               => 'admin',
            ]
        );
    }

    /**
     * Extend the current subscription by a number of days.
     * If the subscription is expired, extends from today.
     */
    public function extend(Tenant $tenant, int $days): Subscription
    {
        $subscription = Subscription::where('tenant_id', $tenant->getKey())->firstOrFail();

        $base = ($subscription->current_period_ends_at && $subscription->current_period_ends_at->isFuture())
            ? $subscription->current_period_ends_at
            : now();

        $subscription->update([
            'status'                => 'active',
            'current_period_ends_at' => $base->addDays($days)->toDateString(),
            'gateway'               => 'admin',
        ]);

        return $subscription->refresh();
    }

    /**
     * Cancel a tenant's subscription immediately.
     */
    public function cancel(Tenant $tenant): Subscription
    {
        $subscription = Subscription::where('tenant_id', $tenant->getKey())->firstOrFail();
        $subscription->update(['status' => 'canceled']);

        return $subscription->refresh();
    }

    /**
     * Grant lifetime access on a specific plan.
     */
    public function grantLifetime(Tenant $tenant, int $planId): Subscription
    {
        $plan = Plan::findOrFail($planId);

        return Subscription::updateOrCreate(
            ['tenant_id' => $tenant->getKey()],
            [
                'plan_id'               => $plan->id,
                'status'                => 'active',
                'interval'              => 'lifetime',
                'current_period_start'  => now()->toDateString(),
                'current_period_ends_at' => null,
                'gateway'               => 'admin',
            ]
        );
    }
}
