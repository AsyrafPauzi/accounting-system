<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if (app()->environment('local')) {
            // Local dev needs permissive CSP for Vite HMR over various localhost IPs/ports
            $csp = "default-src 'self'; " .
                   "script-src * 'unsafe-inline' 'unsafe-eval'; " .
                   "style-src * 'unsafe-inline'; " .
                   "font-src * data:; " .
                   "img-src * data: blob:; " .
                   "connect-src * ws: wss:;";
        } else {
            // Strict CSP for Production
            $csp = "default-src 'self'; " .
                   "script-src 'self' 'unsafe-inline'; " .
                   "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net; " .
                   "font-src 'self' https://fonts.gstatic.com https://fonts.bunny.net data:; " .
                   "img-src 'self' data: blob: https:; " .
                   "connect-src 'self' ws: wss:;";
        }

        $response->headers->set('Content-Security-Policy', $csp);

        // Remove information disclosure headers
        $response->headers->remove('X-Powered-By');
        if (function_exists('header_remove')) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
