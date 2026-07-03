<?php

namespace App\Http\Middleware;

use App\Models\TenantApiCredential;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the HMAC-SHA256 signature on mutating API requests.
 *
 * Why HMAC on writes (in addition to the Bearer api_key)?
 *
 *   - The api_key alone proves "I have the key" but not "I made this
 *     specific request". A leaked api_key (e.g. via a misconfigured
 *     log shipper) lets an attacker impersonate the partner.
 *   - With HMAC, the partner's backend signs each request with the
 *     never-transmitted signing key. Even if the api_key leaks, the
 *     attacker can't forge new write requests without ALSO leaking
 *     the signing key — defence in depth.
 *
 * The signed payload is the canonical request string:
 *
 *     "{$timestampMs}.{$method}.{$pathWithQuery}.{$body}"
 *
 * and the headers expected on every mutating call:
 *
 *     X-BukuCloud-Timestamp:  unix-millis at sign time
 *     X-BukuCloud-Signature:  hex-encoded hmac_sha256(canonical, signing_key)
 *
 * Replay defence: requests outside `api.signature_skew_seconds`
 * (default 5 min) of server time are rejected. Combined with the
 * signature itself this means a captured request body+sig is unusable
 * 5 minutes after capture.
 */
class ApiSignatureVerifier
{
    public function handle(Request $request, Closure $next): Response
    {
        // Read-only methods don't carry signatures — the api_key is
        // sufficient because read operations are idempotent and
        // rate-limited. The /api/v1 route group only wires this
        // middleware on POST/PUT/PATCH/DELETE methods, so this is
        // belt-and-braces.
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        /** @var TenantApiCredential|null $credential */
        $credential = $request->attributes->get('apiCredential');
        if (! $credential) {
            // ApiKeyAuth must run before us. If it didn't, there's a
            // routing bug; refuse rather than silently bypass the
            // signature.
            return $this->fail(500, 'server_error', 'Signature verifier ran without prior auth.');
        }

        $timestamp = (string) $request->header('X-BukuCloud-Timestamp', '');
        $signature = (string) $request->header('X-BukuCloud-Signature', '');

        if ($timestamp === '' || $signature === '') {
            return $this->fail(401, 'invalid_request', 'Missing X-BukuCloud-Timestamp or X-BukuCloud-Signature header.');
        }

        if (! ctype_digit($timestamp)) {
            return $this->fail(401, 'invalid_request', 'X-BukuCloud-Timestamp must be unix milliseconds as digits.');
        }

        $skew = (int) config('api.signature_skew_seconds', 300);
        $delta = abs((int) (now()->getTimestampMs()) - (int) $timestamp);
        if ($delta > $skew * 1000) {
            return $this->fail(401, 'invalid_request', "Timestamp is more than {$skew}s away from server time.");
        }

        $signingKey = $credential->decryptSigningKey();

        // Canonical request string. Includes path with query so a
        // captured signature can't be replayed against a different
        // endpoint, and the raw body so it can't be replayed with a
        // mutated payload.
        $pathWithQuery = $request->getPathInfo();
        if ($qs = $request->server->get('QUERY_STRING')) {
            $pathWithQuery .= '?' . $qs;
        }

        $canonical = $timestamp
            . '.' . $request->method()
            . '.' . $pathWithQuery
            . '.' . $request->getContent();

        $expected = hash_hmac('sha256', $canonical, $signingKey);

        if (! hash_equals($expected, $signature)) {
            return $this->fail(401, 'invalid_signature', 'Computed HMAC does not match X-BukuCloud-Signature.');
        }

        return $next($request);
    }

    private function fail(int $status, string $error, string $description): Response
    {
        return response()->json([
            'error'             => $error,
            'error_description' => $description,
        ], $status);
    }
}
