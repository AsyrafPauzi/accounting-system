<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\TenantApiCredential;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Facades\Tenancy;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates incoming /api/v1 requests against a tenant_api_credentials
 * row, then initialises tenancy on the credential's tenant so downstream
 * controllers can query tenant-scoped tables without thinking about it.
 *
 *   Authorization: Bearer pk_live_xxxxxxxxxxxxxxxx
 *
 * Failure modes (all return application/json with
 * `{ "error": "...", "error_description": "..." }`):
 *
 *   - Missing / malformed header              → 401 invalid_request
 *   - Key not found / revoked                 → 401 invalid_token
 *   - Tenant gone / plan no longer covers API → 403 insufficient_scope
 *
 * On success the resolved credential is stashed on the request as
 * `$request->attributes->set('apiCredential', ...)`. ApiSignatureVerifier
 * downstream uses it to recover the HMAC secret.
 */
class ApiKeyAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');

        if (! preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return $this->fail(401, 'invalid_request', 'Missing or malformed Authorization header. Expected: Bearer <api_key>.');
        }

        $plaintext = $m[1];

        $credential = TenantApiCredential::findActiveByPlaintextKey($plaintext);
        if (! $credential) {
            return $this->fail(401, 'invalid_token', 'API key is invalid or has been revoked.');
        }

        $tenant = Tenant::find($credential->tenant_id);
        if (! $tenant) {
            // Tenant deletion didn't cascade for some reason (or the
            // credential row outlived its tenant via a bug). Refuse
            // rather than 500.
            return $this->fail(401, 'invalid_token', 'API key is no longer valid.');
        }

        // Plan gate. Re-checked on every request because a tenant can
        // downgrade between credential issuance and now; we don't want
        // a Startup-tier tenant siphoning data via an old Solo-era key.
        if (! $tenant->hasPlanPermission('api.access')) {
            return $this->fail(403, 'insufficient_scope', 'The tenant\'s current plan does not include API access.');
        }

        // Touch last_used_at on a best-effort basis. We don't want a
        // DB write blip to fail an otherwise-good request, so swallow
        // exceptions here. The column is purely informational.
        try {
            $credential->forceFill(['last_used_at' => now()])->saveQuietly();
        } catch (\Throwable) {
            // Intentional: don't let this fail the request.
        }

        // Initialize tenancy for the duration of the request. The /api
        // route group does not run InitializeTenancyByLoggedInUser, so
        // without this every Eloquent query in the controller would hit
        // the central connection.
        Tenancy::initialize($tenant);

        $request->attributes->set('apiCredential', $credential);

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
