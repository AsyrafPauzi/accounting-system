<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * If `deployment.internal_admin_host` is set (e.g.
 * `internal.bukucloud.com`), enforce that platform-level admin routes
 * are *only* reachable via that host. Requests on the main host get
 * 404 (deliberately not 403, to avoid leaking that the route exists).
 *
 * If the config is null (default for dev), this middleware is a
 * no-op so `php artisan serve` keeps working without DNS setup.
 */
class InternalAdminHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $required = (string) (config('deployment.internal_admin_host') ?? '');
        if ($required === '') {
            return $next($request);
        }

        if (strcasecmp($request->getHost(), $required) !== 0) {
            abort(404);
        }

        return $next($request);
    }
}
