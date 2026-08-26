<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirect SME users whose tenant database is still being provisioned
 * to the provisioning polling page. Firm users and routes that must
 * remain reachable during setup are excluded.
 */
class EnsureTenantProvisioned
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        if ($user->isFirmUser() || ! $user->tenant_id) {
            return $next($request);
        }

        if ($request->routeIs(
            'provisioning',
            'provisioning.status',
            'provisioning.retry',
            'logout',
        )) {
            return $next($request);
        }

        $tenant = Tenant::find($user->tenant_id);

        if (! $tenant || $tenant->isProvisioned()) {
            return $next($request);
        }

        return redirect()->route('provisioning');
    }
}
