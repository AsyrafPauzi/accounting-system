<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyByLoggedInUser
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Check if the user is logged in
        if (auth()->check() && auth()->user()->tenant_id) {
            
            // 2. Find their specific company (Tenant)
            $tenant = \App\Models\Tenant::find(auth()->user()->tenant_id);
            
            if ($tenant) {
                // 3. MAGIC: Tell Laravel to switch to THIS company's private database
                tenancy()->initialize($tenant);
            }
        }

        return $next($request);
    }
}