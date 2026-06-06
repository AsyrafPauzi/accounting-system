<?php

namespace App\Http\Middleware;

use App\Support\Deployment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aborts requests to SaaS-only routes when running in self-hosted
 * mode. Used for the Practice console, subscription billing,
 * super-admin tenant management, and anything else that only makes
 * sense for the multi-tenant SaaS deployment.
 *
 * Returns 404 (not 403) so the route looks the same as a non-existent
 * path — there's no signal to a self-hosted install that these
 * features even exist in the codebase.
 */
class SaasOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(! Deployment::saasFeaturesEnabled(), 404);
        return $next($request);
    }
}
