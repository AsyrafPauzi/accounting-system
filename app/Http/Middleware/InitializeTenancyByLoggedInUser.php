<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByLoggedInUser
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check if this is a public invoice download request (prioritize request param)
        if ($request->is('public/invoices/*/download') && $request->has('tenant_id')) {
            $tenant = \App\Models\Tenant::find($request->input('tenant_id'));
            if ($tenant) {
                tenancy()->initialize($tenant);
                return $next($request);
            }
        }

        // 2. Otherwise, check if the user is logged in
        if (auth()->check() && auth()->user()->tenant_id) {
            
            // 3. Find their specific company (Tenant)
            $tenant = \App\Models\Tenant::find(auth()->user()->tenant_id);
            
            if ($tenant) {
                // 4. MAGIC: Tell Laravel to switch to THIS company's private database
                tenancy()->initialize($tenant);
            }
        }

        return $next($request);
    }
}