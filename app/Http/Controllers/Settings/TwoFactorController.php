<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor)
    {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user, 403);

        $hasPending = ! empty($user->two_factor_pending_secret);
        $isEnabled  = ! empty($user->two_factor_secret) && ! empty($user->two_factor_confirmed_at);

        // QR is only generated for the enrolment flow — once enabled,
        // we deliberately don't expose the secret again.
        $qr = $hasPending
            ? $this->twoFactor->qrCodeDataUrl($user, $user->two_factor_pending_secret)
            : null;

        return Inertia::render('Settings/TwoFactor', [
            'isEnabled'     => $isEnabled,
            'hasPending'    => $hasPending,
            'pendingSecret' => $user->two_factor_pending_secret, // visible only during setup
            'qrCode'        => $qr,
            'recoveryCodes' => session('two_factor.recovery_codes'), // one-shot session flash
            'enabledAt'     => optional($user->two_factor_confirmed_at)->toIso8601String(),
        ]);
    }

    /**
     * Begin enrolment. Generates a fresh secret and stores it as the
     * *pending* secret. The user scans the QR / types in the secret,
     * then submits a 6-digit code via confirm().
     */
    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        if ($user->two_factor_confirmed_at) {
            return redirect()->route('settings.2fa.show')
                ->with('info', '2FA is already enabled.');
        }

        $user->forceFill([
            'two_factor_pending_secret' => $this->twoFactor->generateSecret(),
        ])->save();

        return redirect()->route('settings.2fa.show');
    }

    /**
     * Verify the first code from the authenticator. On success: promote
     * the pending secret, generate recovery codes, flash plaintext to
     * the session for one-shot display.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:8'],
        ]);

        if (! $user->two_factor_pending_secret) {
            return redirect()->route('settings.2fa.show')
                ->with('error', 'Start the setup process first.');
        }

        if (! $this->twoFactor->verifyCode($user->two_factor_pending_secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'That code is incorrect or has expired. Try again.']);
        }

        $codes = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret'         => $user->two_factor_pending_secret,
            'two_factor_pending_secret' => null,
            'two_factor_recovery_codes' => $codes['hashed'],
            'two_factor_confirmed_at'   => Carbon::now(),
        ])->save();

        Log::info('2FA enabled', ['user_id' => $user->id]);

        // One-shot flash so the user can copy them down. After the next
        // request these are gone — we never show codes after setup.
        return redirect()->route('settings.2fa.show')
            ->with('two_factor.recovery_codes', $codes['plain'])
            ->with('success', '2FA is now enabled. Save your recovery codes — they will not be shown again.');
    }

    /**
     * Disable 2FA. Requires the current password to prevent a hijacked
     * session from silently turning it off.
     */
    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user->forceFill([
            'two_factor_secret'         => null,
            'two_factor_pending_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ])->save();

        Log::info('2FA disabled', ['user_id' => $user->id]);

        return redirect()->route('settings.2fa.show')
            ->with('success', '2FA has been disabled.');
    }

    /**
     * Generate a new batch of recovery codes (e.g. user used or lost
     * theirs). Replaces the existing list — old codes stop working
     * immediately.
     */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        if (! $user->two_factor_confirmed_at) {
            return redirect()->route('settings.2fa.show')
                ->with('error', 'Enable 2FA first.');
        }

        $request->validate(['password' => ['required', 'current_password']]);

        $codes = $this->twoFactor->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes['hashed']])->save();

        return redirect()->route('settings.2fa.show')
            ->with('two_factor.recovery_codes', $codes['plain'])
            ->with('success', 'Recovery codes regenerated. Save them now — they will not be shown again.');
    }

    /**
     * Cancel an enrolment in progress (clears `two_factor_pending_secret`).
     * Useful if the user starts setup, walks away, and wants to begin again.
     */
    public function cancelPending(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $user->forceFill(['two_factor_pending_secret' => null])->save();

        return redirect()->route('settings.2fa.show');
    }
}
