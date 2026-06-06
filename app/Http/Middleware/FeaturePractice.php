<?php

namespace App\Http\Middleware;

use App\Support\Deployment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates routes that only make sense when the Practice (Accountant)
 * console is enabled for this deployment.
 *
 * Resolution rules:
 *   - SaaS  → always enabled.
 *   - Self-hosted Standard → 404 (single-tenant install, no firm
 *     hierarchy).
 *   - Self-hosted Enterprise → enabled when the license carries the
 *     `practice.console` feature flag.
 *
 * Returns 404 (not 403) so a Standard install never even hints that
 * Practice exists in the codebase.
 */
class FeaturePractice
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(! Deployment::practiceConsoleEnabled(), 404);
        return $next($request);
    }
}
