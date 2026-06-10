<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Models\OAuthAuthorizationCode;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Consent screen + approve/deny handlers.
 *
 *   GET  /oauth/consent          — "Fin Persona is asking to access X.
 *                                   Approve or Cancel?"
 *   POST /oauth/consent/approve  — issue an authorization code, redirect
 *                                   to partner's redirect_uri with code + state.
 *   POST /oauth/consent/deny     — redirect to partner's redirect_uri
 *                                   with an `error=access_denied` query
 *                                   string (RFC 6749 §4.1.2.1).
 *
 * The code we mint here is single-use, lives 10 minutes, and only
 * unlocks an api_key + signing key when the partner's backend POSTs
 * /api/oauth/token with a matching client_secret AND the same
 * redirect_uri the user just authorised against.
 */
class ConsentController extends Controller
{
    public function show(Request $request): RedirectResponse|Response
    {
        $pending = $this->pendingOrAbort($request);
        if ($pending instanceof RedirectResponse) {
            return $pending;
        }

        $user = auth()->user();
        $tenant = $user->tenant_id ? Tenant::find($user->tenant_id) : null;

        // Defence in depth — by the time we get here the login
        // controller has already enforced api.access, but a long-
        // running session could have lost it (downgrade mid-flow).
        if (! $tenant || ! $tenant->hasPlanPermission('api.access')) {
            return redirect()->route('oauth.upgrade.show');
        }

        $client = config("oauth.clients.{$pending['client_id']}");

        return Inertia::render('OAuth/Consent', [
            'partner' => [
                'id'          => $pending['client_id'],
                'name'        => $client['name'] ?? $pending['client_id'],
                'description' => $client['description'] ?? null,
                'scopes'      => $client['scopes'] ?? [],
            ],
            'tenant' => [
                'id'   => $tenant->id,
                // `tenant.display_name` is a virtual on Stancl's
                // JSON `data` column; falls back to legal_name then id.
                'name' => $tenant->display_name ?? $tenant->legal_name ?? $tenant->id,
            ],
            'user' => [
                'name'  => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function approve(Request $request): RedirectResponse|HttpResponse
    {
        $pending = $this->pendingOrAbort($request);
        if ($pending instanceof RedirectResponse) {
            return $pending;
        }

        $user = auth()->user();
        $tenant = $user->tenant_id ? Tenant::find($user->tenant_id) : null;

        if (! $tenant || ! $tenant->hasPlanPermission('api.access')) {
            return redirect()->route('oauth.upgrade.show');
        }

        $code = OAuthAuthorizationCode::issue(
            tenantId: $tenant->id,
            userId: $user->id,
            oauthClientId: $pending['client_id'],
            redirectUri: $pending['redirect_uri'],
        );

        // Done with the pending intent — clear it so a stray refresh
        // can't double-issue.
        $request->session()->forget('oauth.pending');

        return $this->externalRedirect(
            $request,
            $this->appendQuery($pending['redirect_uri'], [
                'code'  => $code->code,
                'state' => $pending['state'],
            ])
        );
    }

    public function deny(Request $request): RedirectResponse|HttpResponse
    {
        $pending = $request->session()->pull('oauth.pending');
        if (! $pending) {
            return redirect()->route('dashboard');
        }

        // RFC 6749 §4.1.2.1 — return the user to the partner with an
        // explicit error, NOT to a BukuCloud page. The partner will
        // show the user-friendly "permission denied" UX in their app.
        return $this->externalRedirect(
            $request,
            $this->appendQuery($pending['redirect_uri'], [
                'error'             => 'access_denied',
                'error_description' => 'The user denied the request.',
                'state'             => $pending['state'],
            ])
        );
    }

    /**
     * Redirect the user out to a third-party URL.
     *
     * Plain `redirect()->away()` sends a 302, which Inertia (XHR) just
     * follows blindly — and the partner's callback returns its own
     * JSON/HTML, blowing up the Inertia client-side router with
     * "must receive a valid Inertia response". The Inertia-aware way
     * is `Inertia::location()`, which emits a 409 + `X-Inertia-Location`
     * header so the React side does a full page navigation. For
     * non-Inertia requests (e.g. the user pasted the URL in a new tab
     * and the cookie carried over) we fall back to a normal 302.
     */
    private function externalRedirect(Request $request, string $url): RedirectResponse|HttpResponse
    {
        if ($request->header('X-Inertia')) {
            return Inertia::location($url);
        }
        return redirect()->away($url);
    }

    /**
     * Pull the pending OAuth intent from session, or redirect to the
     * generic dashboard if the session is missing or stale.
     */
    private function pendingOrAbort(Request $request): array|RedirectResponse
    {
        $pending = $request->session()->get('oauth.pending');
        if (! $pending) {
            return redirect()->route('dashboard');
        }

        // Stale-session guard: if the user started the handshake an
        // hour ago and only got around to clicking now, force a fresh
        // start. Defends against a logged-in attacker hijacking a
        // long-abandoned session.
        if (now()->timestamp - ($pending['started_at'] ?? 0) > 1800) {
            $request->session()->forget('oauth.pending');
            return redirect()->route('dashboard');
        }

        return $pending;
    }

    private function appendQuery(string $url, array $params): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . http_build_query($params);
    }
}
