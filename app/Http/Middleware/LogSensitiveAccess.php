<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records a row in the central `audit_logs` table whenever a request
 * passes through a route that exposes data crossing tenant boundaries
 * or is otherwise sensitive (super-admin tenant browsing, account
 * erasure scheduling, PDPA exports, audit-log viewing).
 *
 * Why a separate middleware vs the existing model-level Auditable trait:
 *   - The existing audit trail tracks *changes* to specific records via
 *     Eloquent events. It says nothing about *reads* — yet PDPA's
 *     accountability principle expects us to know who looked at what.
 *   - Sensitive routes are typically GETs. A middleware captures them
 *     uniformly without bolting view-tracking onto every controller.
 *
 * What gets logged:
 *   - `auditable_type`   — the route name (e.g. 'admin.tenants.index')
 *   - `auditable_id`     — full URL path so the row links back to the
 *                          exact resource for forensic review
 *   - `event`            — 'sensitive_read'
 *   - `new_values`       — JSON: HTTP method, IP, user-agent, status,
 *                          and tenant context if any
 *
 * Failures here are swallowed and routed to the application log — we
 * never want audit-write trouble to break the user's request.
 */
class LogSensitiveAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->record($request, $response);
        } catch (\Throwable $e) {
            Log::warning('LogSensitiveAccess: write failed', [
                'route' => optional($request->route())->getName(),
                'err'   => $e->getMessage(),
            ]);
        }

        return $response;
    }

    private function record(Request $request, Response $response): void
    {
        $route = $request->route();
        $routeName = $route?->getName() ?? 'unknown';
        $user = $request->user();

        // Skip noise: redirects from auth flow, OPTIONS preflights, etc.
        if (! in_array($request->method(), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        DB::connection(config('tenancy.database.central_connection', 'mysql'))
            ->table('audit_logs')
            ->insert([
                'user_id'        => $user?->id,
                'user_name'      => $user?->name ?? 'guest',
                'auditable_type' => $routeName,
                'auditable_id'   => substr($request->path(), 0, 200),
                'event'          => 'sensitive_read',
                'old_values'     => null,
                'new_values'     => json_encode([
                    'method'      => $request->method(),
                    'ip'          => $request->ip(),
                    'user_agent'  => substr((string) $request->userAgent(), 0, 200),
                    'status'      => $response->getStatusCode(),
                    'tenant_id'   => tenant()?->id,
                    'query'       => $request->query() ?: null,
                ]),
                'created_at'     => now(),
            ]);
    }
}
