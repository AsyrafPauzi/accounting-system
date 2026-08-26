<?php

namespace App\Http\Middleware;

use App\Models\FirmClient;
use App\Models\Tenant;
use App\Support\DefaultChartOfAccountsSeeder;
use App\Support\Deployment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides which tenant database to attach to the current request, in
 * priority order:
 *
 *   1. Public invoice-download links carry an explicit `tenant_id`
 *      query string — initialise that tenant directly. This is how
 *      external customers reach a hosted PDF without an account.
 *
 *   2. Firm users (Practice / Accountant track) are central by
 *      default. They opt into a specific client tenant via the
 *      session key `acting_tenant_id` set by the client switcher.
 *      We *must* re-verify the FirmClient pivot every request — a
 *      stale session id from a removed client must not grant access.
 *
 *   3. Regular SME tenant users initialise the tenant their
 *      `users.tenant_id` points at. Same as before.
 */
class InitializeTenancyByLoggedInUser
{
    public function handle(Request $request, Closure $next): Response
    {
        // Self-hosted Standard (no Practice console) is single-tenant:
        // short-circuit to the "default" tenant. No firm routing,
        // no client switching, no `acting_tenant_id`. This is the
        // overwhelmingly common shape — single company on their own
        // server.
        //
        // Self-hosted Enterprise (Practice console enabled) goes
        // through the full tenant-resolution path below — same as
        // SaaS — because firm users need to act into client tenants.
        if (Deployment::isSelfHosted() && ! Deployment::practiceConsoleEnabled()) {
            $tenant = Tenant::find(Deployment::DEFAULT_TENANT_ID);
            if ($tenant) {
                tenancy()->initialize($tenant);
            }
            return $this->finish($request, $next);
        }

        // 1. Public customer-facing links (PDF, tracking pixel, Pay Now return)
        if ($request->has('tenant_id') && (
            $request->is('public/*') || $request->is('pay/*')
        )) {
            $tenant = Tenant::find($request->input('tenant_id'));
            if ($tenant) {
                tenancy()->initialize($tenant);
                return $this->finish($request, $next);
            }
        }

        if (! auth()->check()) {
            return $this->finish($request, $next);
        }

        $user = auth()->user();

        // 2. Firm user with an "acting" client — initialise that
        // client's tenant if (and only if) the firm still has an
        // active link to it.
        if ($user->isFirmUser()) {
            $actingTenantId = $request->session()->get('acting_tenant_id');
            if ($actingTenantId) {
                $authorised = FirmClient::query()
                    ->where('firm_id', $user->firm_id)
                    ->where('tenant_id', $actingTenantId)
                    ->where('status', 'active')
                    ->exists();

                if ($authorised) {
                    $tenant = Tenant::find($actingTenantId);
                    if ($tenant) {
                        tenancy()->initialize($tenant);
                    }
                } else {
                    // Stale id — clean it out so subsequent requests
                    // don't keep retrying.
                    $request->session()->forget('acting_tenant_id');
                }
            }
            return $this->finish($request, $next);
        }

        // 3. Regular SME tenant user
        if ($user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);
            if ($tenant) {
                tenancy()->initialize($tenant);
            }
        }

        return $this->finish($request, $next);
    }

    private function finish(Request $request, Closure $next): Response
    {
        if (tenancy()->initialized && auth()->check()) {
            $this->ensureDefaultChartOfAccounts($request);
        }

        return $next($request);
    }

    /**
     * Backfill the default chart once per session per tenant so every
     * login gets a usable COA without manual "seed" clicks.
     */
    private function ensureDefaultChartOfAccounts(Request $request): void
    {
        $tenantId = tenant('id');
        if (! $tenantId) {
            return;
        }

        $sessionKey = "default_coa_ensured_{$tenantId}";
        if ($request->session()->get($sessionKey)) {
            return;
        }

        try {
            DefaultChartOfAccountsSeeder::seedMissing();
            \App\Support\DocumentNumberDefaults::seedMissing();
            \App\Support\TaxCodeDefaults::seedMissing();
            \App\Support\AccountingPeriodResolver::ensurePeriodsExist();
        } catch (\Throwable) {
            return;
        }

        $request->session()->put($sessionKey, true);
    }
}