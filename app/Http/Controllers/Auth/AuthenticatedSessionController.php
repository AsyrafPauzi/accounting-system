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
        $request->authenticate();

        $request->session()->regenerate();

        if ($request->user()->hasRole('super-admin')) {
            return redirect()->intended(route('admin.tenants.index', absolute: false));
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
