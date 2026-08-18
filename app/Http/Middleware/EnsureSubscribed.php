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
        'verification.',
        'logout',
        // Practice / Accountant track lives on the central side, not
        // inside any tenant — the firm has its own subscription via
        // `firms.firm_subscription_id`. The tenant-subscription gate
        // below would redirect-loop firm users otherwise.
        'practice.',
        // Tenant→firm invite acceptance lives outside the `practice.`
        // prefix for naming clarity (`firm.invite.accept`) but is
        // logically part of the Practice console — firm-owners reach
        // it via a link the SME shared with them. Without this prefix,
        // the firm-user fallthrough below would bounce them to the
        // practice dashboard before they ever see the AcceptInvite
        // page, which presents to the user as "the link goes back to
        // homepage and I can't accept the invite."
        'firm.invite.',
        // Welcome-tour / verify-email reminder dismiss — stamps a
        // timestamp on the user row. Must stay reachable on the free
        // tier so Skip does not close-then-reopen the modal.
        'onboarding.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Self-hosted: there's no subscription to check. The customer
        // already paid for their license; the running app is itself
        // the proof of purchase. Skip the gate entirely.
        if (\App\Support\Deployment::isSelfHosted()) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // If this user is being impersonated by a super-admin, allow the request to
        // bypass subscription checks so the admin can debug inactive tenants and
        // return to the admin panel.
        if ($request->session()->has('impersonator_id')) {
            return $next($request);
        }

        $routeName = (string) $request->route()?->getName();

        // Firm users (Accountant track) — let the practice console
        // through, and when they're "acting" inside a client, key the
        // subscription check off the *client's* tenant rather than the
        // user's (which is null on firm staff).
        if ($user->isFirmUser()) {
            // Practice routes never require a tenant subscription.
            if ($routeName && (str_starts_with($routeName, 'practice.') || $this->isAlwaysAllowed($routeName))) {
                return $next($request);
            }

            $actingTenantId = $request->session()->get('acting_tenant_id');
            if ($actingTenantId) {
                $clientTenant = Tenant::find($actingTenantId);
                if ($clientTenant && $clientTenant->hasActiveSubscription()) {
                    return $next($request);
                }
                // Client has no active sub — bounce back to the firm
                // console with a hint instead of looping subscription.
                return redirect()->route('practice.dashboard')
                    ->with('error', 'That client\'s subscription is not active.');
            }

            // Firm user, no client selected → send them to the
            // practice console rather than the subscription page.
            return redirect()->route('practice.dashboard');
        }

        if ($user->hasRole('super-admin')) {
            // Super admins are restricted to central platform management.
            // They must impersonate a tenant to access business modules (invoices, customers, etc).
            // `onboarding.` is a self-action on the user row (welcome-tour /
            // verify-email reminder dismiss). Blocking it made Skip close the
            // modal then immediately re-open, because welcomed_at never saved.
            $allowedRoutes = ['admin.', 'profile.', 'logout', 'login', 'onboarding.'];
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

