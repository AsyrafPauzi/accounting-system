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

        $routeName = (string) $request->route()?->getName();

        if ($user->hasRole('super-admin')) {
            // Super admins are restricted to central platform management.
            // They must impersonate a tenant to access business modules (invoices, customers, etc).
            $allowedRoutes = ['admin.', 'profile.', 'logout', 'login'];
            $isAllowed = false;
            foreach ($allowedRoutes as $prefix) {
                if (str_starts_with($routeName, $prefix)) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                return redirect()->route('admin.tenants.index')->with('info', 'As a Platform Administrator, please impersonate a tenant to view or manage their specific data.');
            }

            return $next($request);
        }

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

