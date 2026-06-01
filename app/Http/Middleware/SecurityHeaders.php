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

        // Classic hardening headers.
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Modern process-isolation / capability hardening.
        // Permissions-Policy disables sensors/APIs we never use, so a future
        // XSS or untrusted iframe can't ask the browser for camera/mic/etc.
        $response->headers->set('Permissions-Policy', implode(', ', [
            'accelerometer=()',
            'autoplay=()',
            'camera=()',
            'display-capture=()',
            'encrypted-media=()',
            'fullscreen=(self)',
            'geolocation=()',
            'gyroscope=()',
            'magnetometer=()',
            'microphone=()',
            'midi=()',
            'payment=()',
            'picture-in-picture=()',
            'sync-xhr=(self)',
            'usb=()',
            'xr-spatial-tracking=()',
        ]));

        // Cross-origin isolation. Same-origin opener prevents window-tampering
        // by popups; same-site CORP rejects cross-site fetches of our HTML/JSON.
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-site');

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
                   "connect-src 'self';";
        }

        $response->headers->set('Content-Security-Policy', $csp);

        // Remove information disclosure headers
        $response->headers->remove('X-Powered-By');

        return $response;
    }
}
