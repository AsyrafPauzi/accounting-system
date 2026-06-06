<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'botGuard' => ['ts' => \App\Http\Middleware\SpamBotGuard::freshTimestamp()],
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $needs2fa = $request->authenticate();

        if ($needs2fa) {
            // Don't regenerate or log them in here — the challenge
            // controller takes over and finishes the auth handshake
            // once the TOTP/recovery code clears.
            return redirect()->route('auth.2fa.challenge.show');
        }

        $request->session()->regenerate();

        if ($request->user()->hasRole('super-admin')) {
            return redirect()->intended(route('admin.tenants.index', absolute: false));
        }

        // Firm (Practice) users have no tenant database of their own.
        // Sending them to /dashboard would query tenant-scoped tables
        // against the central connection and explode. They live in
        // the Practice console until they "switch into" a client.
        if ($request->user()->isFirmUser()) {
            return redirect()->intended(route('practice.dashboard', absolute: false));
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // Expire any stale session/XSRF cookies already in the browser before
        // the session middleware writes the fresh ones. Without this, a browser
        // can hold two same-name cookies (e.g. one from before logout that still
        // has time left) and PHP non-deterministically picks the stale one on
        // the next POST, producing a CSRF token mismatch (419).
        return redirect()->route('login')
            ->withCookie(cookie()->forget(config('session.cookie')))
            ->withCookie(cookie()->forget('XSRF-TOKEN'));
    }
}
