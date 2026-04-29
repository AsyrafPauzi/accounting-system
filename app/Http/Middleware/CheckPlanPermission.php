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
        $user = $request->user();
        if (! $user || ! $user->tenant_id) {
            return $next($request);
        }

        $tenant = Tenant::find($user->tenant_id);
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
