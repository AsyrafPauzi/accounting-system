<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')) {
            $input = $request->all();
            
            array_walk_recursive($input, function (&$val) {
                if (is_string($val)) {
                    // Safe cleanup only (e.g. trimming)
                    // We no longer strip_tags here to prevent data corruption.
                    // XSS protection is handled at the output layer (Inertia/Vue/Blade).
                    $val = trim($val);
                }
            });

            $request->merge($input);
        }

        return $next($request);
    }
}
