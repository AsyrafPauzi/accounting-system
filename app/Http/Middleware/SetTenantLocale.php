<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Sets the application locale from the authenticated user's tenant. Falls
 * back to 'en'. Runs after auth/inertia middleware so it can read $request->user().
 *
 * Storage: Stancl Tenancy stores custom fields in the tenants.data JSON column,
 * so $tenant->language is automatically read from / written to that JSON blob —
 * no dedicated DB column required.
 */
class SetTenantLocale
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
            $locale = $tenant?->language ?: 'en';
            if (in_array($locale, ['en', 'ms'], true)) {
                App::setLocale($locale);
            }
        }

        return $next($request);
    }
}
