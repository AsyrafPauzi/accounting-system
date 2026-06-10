<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\OAuthAuthorizationCode;
use App\Models\Tenant;
use App\Models\TenantApiCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Server-to-server endpoint where the partner's backend exchanges the
 * authorization code for a real api_key + signing key.
 *
 *   POST /api/oauth/token
 *     {
 *       "grant_type":    "authorization_code",
 *       "client_id":     "finpersona",
 *       "client_secret": "...",                  // never seen by the user
 *       "code":          "...",                  // from the redirect
 *       "redirect_uri":  "https://app.finpersona.com/...callback"
 *     }
 *
 * On success returns:
 *
 *     {
 *       "api_key":                "pk_live_xxxxxxxxxxxxxx",
 *       "transaction_signing_key":"sk_live_xxxxxxxxxxxxxx",
 *       "tenant": {"id": "...", "name": "..."},
 *       "issued_at": "2026-06-10T05:11:33Z"
 *     }
 *
 * The api_key + signing_key are returned ONCE here and never again. If
 * the partner loses them, the user must re-authorise (which produces a
 * brand new credential row) or the operator must revoke + reissue.
 *
 * This endpoint is CSRF-exempt (registered in bootstrap/app.php) — it's
 * server-to-server, with no browser involvement on the request side.
 * Authentication is by the partner's pre-shared client_secret, not a
 * cookie.
 */
class TokenController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'grant_type'    => ['required', 'string', 'in:authorization_code'],
            'client_id'     => ['required', 'string'],
            'client_secret' => ['required', 'string'],
            'code'          => ['required', 'string'],
            'redirect_uri'  => ['required', 'string'],
        ]);

        $client = config("oauth.clients.{$data['client_id']}");
        if (! $client) {
            return $this->fail('invalid_client', 'Unknown client.');
        }

        // Constant-time compare to defeat timing attacks against the
        // client_secret. hash_equals returns false on any length
        // mismatch too, so we don't need a separate length check.
        if (! is_string($client['client_secret'] ?? null)
            || ! hash_equals($client['client_secret'], $data['client_secret'])) {
            return $this->fail('invalid_client', 'Client authentication failed.');
        }

        // Wrap in a transaction so the "find row + mark used + insert
        // credential" sequence is atomic. Without this, a concurrent
        // double-exchange of the same code could mint two credentials.
        return DB::transaction(function () use ($data) {
            // Lock the code row FOR UPDATE. SQLite ignores the lock but
            // the upstream MySQL/Postgres deployment uses it.
            /** @var OAuthAuthorizationCode|null $code */
            $code = OAuthAuthorizationCode::query()
                ->where('code', $data['code'])
                ->lockForUpdate()
                ->first();

            if (! $code || ! $code->isUsable()) {
                return $this->fail('invalid_grant', 'Authorization code is invalid, expired, or already used.');
            }

            if ($code->oauth_client_id !== $data['client_id']) {
                // Code-injection defence: don't let one partner swap in
                // another partner's code.
                return $this->fail('invalid_grant', 'Authorization code does not belong to this client.');
            }

            // Byte-for-byte check on redirect_uri. Mismatches mean
            // either a misconfigured partner or an attacker trying
            // to lift the code; either way we refuse.
            if (! hash_equals($code->redirect_uri, $data['redirect_uri'])) {
                return $this->fail('invalid_grant', 'redirect_uri mismatch.');
            }

            $tenant = Tenant::find($code->tenant_id);
            if (! $tenant) {
                return $this->fail('invalid_grant', 'Tenant no longer exists.');
            }

            // Final plan-permission check at exchange time. If the
            // tenant downgraded between consent and exchange we
            // refuse rather than mint a credential the next request
            // will reject anyway.
            if (! $tenant->hasPlanPermission('api.access')) {
                return $this->fail('insufficient_plan', 'The tenant\'s current plan does not include API access.');
            }

            $issued = TenantApiCredential::issueFor(
                tenantId: $tenant->id,
                oauthClientId: $code->oauth_client_id,
                issuedByUserId: $code->user_id,
            );

            $code->markUsed();

            // Audit log. We intentionally don't log the plaintext key
            // or signing secret — only the hash + last4. If you need
            // to identify a credential post-hoc, find its row by
            // tenant_id + last4.
            Log::info('oauth.token.issued', [
                'tenant_id'        => $tenant->id,
                'oauth_client_id'  => $code->oauth_client_id,
                'credential_id'    => $issued['credential']->id,
                'api_key_last4'    => $issued['credential']->api_key_last4,
                'issued_by_user_id' => $code->user_id,
            ]);

            return response()->json([
                'api_key'                  => $issued['api_key'],
                'transaction_signing_key'  => $issued['signing_key'],
                'tenant'                   => [
                    'id'   => $tenant->id,
                    'name' => $tenant->display_name ?? $tenant->legal_name ?? $tenant->id,
                ],
                'issued_at'                => $issued['credential']->created_at->toIso8601String(),
            ]);
        });
    }

    private function fail(string $error, string $description): JsonResponse
    {
        return response()->json([
            'error'             => $error,
            'error_description' => $description,
        ], 400);
    }
}
