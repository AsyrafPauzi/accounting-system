<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscribed
{
    /**
    * Routes that are always allowed on the free tier (no subscription required).
    */
    protected array $alwaysAllowedRoutePrefixes = [
        'dashboard',
        'invoices.',
        'customers.',
        'credit-notes.',
        'profile.',
        'settings.',
        'subscription.',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if ($routeName && $this->isAlwaysAllowed($routeName)) {
            return $next($request);
        }

        $tenantId = $user->tenant_id ?? null;

        if (! $tenantId) {
            return redirect()->route('subscription.index');
        }

        /** @var Tenant|null $tenant */
        $tenant = Tenant::find($tenantId);

        if (! $tenant || ! $tenant->hasActiveSubscription()) {
            return redirect()->route('subscription.index')->with('error', 'Please upgrade your plan to access this feature.');
        }

        return $next($request);
    }

    protected function isAlwaysAllowed(string $routeName): bool
    {
        foreach ($this->alwaysAllowedRoutePrefixes as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        return false;
    }
}

