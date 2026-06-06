<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Customer;
use App\Models\Tenant;

/**
 * Per-plan resource caps that can't be expressed as Spatie permissions.
 *
 * Today only the Startup (Free) tier has caps:
 *   - 5 active customers
 *   - 1 bank account (sub_type = 'bank' on the chart of accounts)
 *
 * The cap is silent on every other plan (returns `null`) and on
 * self-hosted installs (license drives entitlements, not plan rows).
 */
class PlanCap
{
    public const STARTUP_CUSTOMER_CAP = 5;
    public const STARTUP_BANK_ACCOUNT_CAP = 1;

    /**
     * Return the plan slug for the current tenant, or null when caps
     * shouldn't apply (self-hosted, firm users acting on a client whose
     * plan is "startup" still gets capped via acting_tenant_id below,
     * unauthenticated, or no active subscription).
     */
    public static function currentPlanSlug(): ?string
    {
        if (Deployment::isSelfHosted()) {
            return null;
        }

        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $tenantId = $user->isFirmUser()
            ? session('acting_tenant_id')
            : $user->tenant_id;

        if (! $tenantId) {
            return null;
        }

        $tenant = Tenant::find($tenantId);
        $sub = $tenant?->activeSubscription();

        return $sub?->plan?->slug;
    }

    /**
     * Has the current tenant hit the Startup customer cap? Returns
     * false on every other plan and on self-hosted.
     */
    public static function customerCapHit(): bool
    {
        if (self::currentPlanSlug() !== 'startup') {
            return false;
        }

        return Customer::query()->count() >= self::STARTUP_CUSTOMER_CAP;
    }

    /**
     * Has the current tenant hit the Startup bank-account cap?
     * Bank accounts are Account rows with sub_type = 'bank'.
     */
    public static function bankAccountCapHit(): bool
    {
        if (self::currentPlanSlug() !== 'startup') {
            return false;
        }

        return Account::query()
            ->where('sub_type', 'bank')
            ->count() >= self::STARTUP_BANK_ACCOUNT_CAP;
    }
}
