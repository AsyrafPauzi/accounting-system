<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Custom login page for the OAuth "Connect" handshake.
 *
 *   GET  /oauth/login          — branded login form
 *   POST /oauth/login          — authenticate, then redirect either
 *                                 to /oauth/2fa-challenge (if 2FA on)
 *                                 or to /oauth/consent.
 *
 * This is a deliberate fork of `AuthenticatedSessionController` rather
 * than a flag because:
 *
 *   - The success branch is different (consent, not dashboard).
 *   - The page chrome is different (partner name + logo, "BukuCloud
 *     is asking you to log in to authorise X" copy).
 *   - The post-authentication firm-vs-SME branching is different
 *     (firm users get rejected here; SME users continue to consent).
 *
 * If the user lands on this page without a pending OAuth request in
 * session — say they bookmarked it — we redirect them to the regular
 * /login. The page is meaningless without `oauth.pending`.
 */
class LoginController extends Controller
{
    public function show(Request $request): RedirectResponse|Response
    {
        $pending = $request->session()->get('oauth.pending');
        if (! $pending) {
            return redirect()->route('login');
        }

        $client = config("oauth.clients.{$pending['client_id']}");

        return Inertia::render('OAuth/Login', [
            'partner'         => [
                'id'   => $pending['client_id'],
                'name' => $client['name'] ?? $pending['client_id'],
            ],
            'canResetPassword' => true,
            'status'          => session('status'),
            'botGuard'        => ['ts' => \App\Http\Middleware\SpamBotGuard::freshTimestamp()],
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        if (! $request->session()->has('oauth.pending')) {
            return redirect()->route('login');
        }

        // Pre-seed the "intended" URL the regular 2FA / login dance
        // honours. Whether or not the user has 2FA, the existing
        // completion paths in TwoFactorChallengeController and
        // AuthenticatedSessionController already call
        // `redirect()->intended(...)` — by stashing /oauth/consent
        // here we land the user on the consent screen instead of
        // the dashboard, without forking that controller.
        $request->session()->put('url.intended', route('oauth.consent.show'));

        $needs2fa = $request->authenticate();

        if ($needs2fa) {
            return redirect()->route('auth.2fa.challenge.show');
        }

        $request->session()->regenerate();

        $user = $request->user();

        // Firm users have no tenant data to grant access to. Fail
        // fast with a clear message rather than silently dumping them
        // on a useless consent page.
        if ($user->isFirmUser()) {
            auth()->logout();
            return redirect()->route('oauth.error.firm-user');
        }

        $tenant = $user->tenant_id ? Tenant::find($user->tenant_id) : null;
        if (! $tenant || ! $tenant->hasPlanPermission('api.access')) {
            return redirect()->route('oauth.upgrade.show');
        }

        return redirect()->route('oauth.consent.show');
    }
}
