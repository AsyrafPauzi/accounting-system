<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Second leg of the login handshake — only reachable after the user has
 * passed email+password but has 2FA enabled. The pending user id lives
 * in the session under `auth.2fa.pending_user_id`; we never re-validate
 * the password here.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor)
    {
    }

    public function show(Request $request): Response | RedirectResponse
    {
        if (! $request->session()->has('auth.2fa.pending_user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge', [
            'status' => session('status'),
        ]);
    }

    /**
     * Verify either a TOTP code (preferred) or a one-time recovery code.
     * Either succeeds → finalise login and redirect; failure throws back
     * to the challenge page with a generic error so an attacker can't
     * tell the recovery code from the TOTP from the form's response.
     */
    public function store(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('auth.2fa.pending_user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $request->validate([
            'code'          => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
        ], [], [], static function ($validator) use ($request) {
            // ensure at least one is provided
            if (! $request->filled('code') && ! $request->filled('recovery_code')) {
                $validator->errors()->add('code', 'Enter your 6-digit code or a recovery code.');
            }
        });

        $user = User::find($userId);
        if (! $user || ! $user->two_factor_secret) {
            $request->session()->forget(['auth.2fa.pending_user_id', 'auth.2fa.remember']);
            return redirect()->route('login')->withErrors(['email' => 'Session expired. Please sign in again.']);
        }

        $remember = (bool) $request->session()->get('auth.2fa.remember');

        // Try TOTP first.
        if ($request->filled('code')
            && $this->twoFactor->verifyCode($user->two_factor_secret, (string) $request->input('code'))) {
            return $this->completeLogin($request, $user, $remember, used: 'totp');
        }

        // Fall back to recovery code.
        if ($request->filled('recovery_code')) {
            $remaining = $this->twoFactor->consumeRecoveryCode(
                $user->two_factor_recovery_codes ?: [],
                (string) $request->input('recovery_code'),
            );
            if ($remaining !== null) {
                $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();
                return $this->completeLogin($request, $user, $remember, used: 'recovery');
            }
        }

        Log::warning('2FA challenge failed', [
            'user_id' => $user->id,
            'ip'      => $request->ip(),
        ]);

        return back()->withErrors(['code' => 'That code is incorrect. Please try again.']);
    }

    private function completeLogin(Request $request, User $user, bool $remember, string $used): RedirectResponse
    {
        Auth::login($user, $remember);
        $request->session()->forget(['auth.2fa.pending_user_id', 'auth.2fa.remember']);
        $request->session()->regenerate();

        Log::info('2FA challenge passed', [
            'user_id' => $user->id,
            'method'  => $used,
        ]);

        if ($user->hasRole('super-admin')) {
            return redirect()->intended(route('admin.tenants.index', absolute: false));
        }
        if ($user->isFirmUser()) {
            return redirect()->intended(route('practice.dashboard', absolute: false));
        }
        return redirect()->intended(route('dashboard', absolute: false));
    }
}
