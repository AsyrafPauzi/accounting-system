<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Global scope for central-DB models that carry a `tenant_id` column
 * (Subscription, ExtraSeatPurchase, …). Adds a `tenant_id = ?` filter to
 * every query when tenancy is initialised, so a future bug where the
 * controller forgot the explicit `where('tenant_id', …)` doesn't leak
 * another tenant's data.
 *
 * Why we don't apply it to Stancl-tenant-DB models (Customer, Invoice,
 * etc.):
 *   Those rows live in a per-tenant database whose connection itself
 *   switches per request — there's no shared table for cross-tenant
 *   access to leak through. Adding a scope there would just check a
 *   column that doesn't exist.
 *
 * Why we don't apply it to User:
 *   Login, registration, and password-reset all run *before* tenancy is
 *   initialised. A global scope would 1) match nothing (no tenant
 *   context) — fine — but 2) confuse super-admin code paths that need
 *   to look up users across tenants. Cleaner to keep User explicit.
 *
 * Bypass for super-admin code paths:
 *   `Subscription::withoutGlobalScope(BelongsToTenantScope::class)->…`
 *   the explicit name lets calling code opt out cleanly.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('belongs_to_tenant', function (Builder $builder) {
            if (! tenancy()->initialized) {
                return;
            }
            $tenantId = tenant()?->id;
            if (! $tenantId) {
                return;
            }
            $builder->where(
                $builder->getModel()->qualifyColumn('tenant_id'),
                $tenantId,
            );
        });
    }
}
