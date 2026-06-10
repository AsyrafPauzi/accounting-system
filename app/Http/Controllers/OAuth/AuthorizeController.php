<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Entry point of the OAuth "Connect to BukuCloud" handshake.
 *
 *   GET /oauth/authorize
 *     ?client_id=finpersona
 *     &redirect_uri=https://app.finpersona.com/integrations/bukucloud/callback
 *     &state=<opaque partner-supplied csrf token>
 *
 * This controller does NOT log anyone in or mint any keys. Its job is:
 *
 *   1. Validate the partner is registered (`config/oauth.php`)
 *   2. Validate the redirect URI is on the partner's allow-list
 *   3. Stash the request's intent in the session under
 *      'oauth.pending' (we'll need it across the login + consent
 *      pages, and we don't want it in the URL after step 1)
 *   4. Branch:
 *        - Already-logged-in SME tenant users with the api.access plan
 *          permission → straight to the consent screen.
 *        - Anyone else (logged out, firm users, free-plan tenants) →
 *          to the custom branded login page (which knows to push the
 *          user into the consent flow on success).
 *
 * If the request is malformed (unknown client, bad redirect_uri) we
 * deliberately render a static error page rather than redirecting
 * anywhere with the suspicious `redirect_uri`. RFC 6749 §4.1.2.1
 * says the same: "if the request fails due to a missing, invalid,
 * or mismatching redirection URI [...] the authorization server
 * SHOULD inform the resource owner of the error". Our error page
 * is `OAuth/Error.jsx`.
 */
class AuthorizeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|Response
    {
        $clientId    = (string) $request->input('client_id', '');
        $redirectUri = (string) $request->input('redirect_uri', '');
        $state       = (string) $request->input('state', '');

        $client = config("oauth.clients.$clientId");

        if (! $client) {
            return Inertia::render('OAuth/Error', [
                'reason' => 'unknown_client',
                'detail' => 'The application requesting access is not registered with BukuCloud.',
            ]);
        }

        if (! in_array($redirectUri, (array) ($client['redirect_uris'] ?? []), true)) {
            // Don't echo the redirect_uri back — the user has no way to
            // verify whether the URL on screen is the partner's real
            // callback or an attacker's. Just say the request was
            // rejected.
            return Inertia::render('OAuth/Error', [
                'reason' => 'invalid_redirect_uri',
                'detail' => 'The application is misconfigured. Please contact ' . ($client['name'] ?? 'the partner') . ' support.',
            ]);
        }

        // Persist the request intent. Subsequent pages (login, consent)
        // pull from this rather than from the URL — once we've
        // validated above, we don't want to re-validate on every step.
        $request->session()->put('oauth.pending', [
            'client_id'    => $clientId,
            'redirect_uri' => $redirectUri,
            'state'        => $state,
            // Initial entry timestamp. We refuse to honour a stale
            // pending session that's been sitting for >30 min — the
            // user has clearly walked away.
            'started_at'   => now()->timestamp,
        ]);

        // Logged-in SME tenant user with API access? Skip the login
        // step — go straight to consent. They already proved who they
        // are; don't ask them to type a password again.
        if (auth()->check()) {
            $user = auth()->user();
            if ($user->isFirmUser()) {
                // Firm users don't have a tenant DB of their own; the
                // OAuth flow grants access to a tenant's data, so a
                // firm user has nothing to authorise here. Send them
                // to the error page rather than into an unfixable
                // consent loop.
                return Inertia::render('OAuth/Error', [
                    'reason' => 'firm_user',
                    'detail' => 'API integrations are scoped to a single business account. Please log in with the company account that owns the data you want to share.',
                ]);
            }

            $tenant = $user->tenant_id ? Tenant::find($user->tenant_id) : null;
            if ($tenant && $tenant->hasPlanPermission('api.access')) {
                return redirect()->route('oauth.consent.show');
            }

            // Logged-in but on a free plan — kick to the upgrade prompt.
            return redirect()->route('oauth.upgrade.show');
        }

        return redirect()->route('oauth.login.show');
    }
}
