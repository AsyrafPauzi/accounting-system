<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;

class CheckPlanPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        // Self-hosted installs don't model SaaS plan tiers — the
        // license is the entitlement. Skip plan-permission checks
        // entirely so features gated behind `plan.permission:*`
        // (recurring invoices, customer statements, etc.) work on
        // a self-hosted install regardless of whether the operator
        // ever ran PlanSeeder. License-driven feature gates run
        // through `Deployment::licenseFeatures()` instead.
        if (\App\Support\Deployment::isSelfHosted()) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        // Resolve the tenant whose plan governs this request:
        //   - Firm users acting on a client → the client tenant's plan
        //   - Regular SME users → their own tenant's plan
        // Without this branch, firm users would skip plan gating
        // (their `tenant_id` is null), which would let them create
        // recurring invoices etc. on a client whose plan disallows it.
        $tenantId = $user->isFirmUser()
            ? $request->session()->get('acting_tenant_id')
            : $user->tenant_id;

        if (! $tenantId) {
            return $next($request);
        }

        $tenant = Tenant::find($tenantId);
        if (! $tenant || ! $tenant->hasPlanPermission($permission)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Your current plan does not support this feature.',
                ], 403);
            }

            return redirect()->route('subscription.index')
                ->with('error', 'Your current plan does not support this feature. Please upgrade to access it.');
        }

        return $next($request);
    }
}
