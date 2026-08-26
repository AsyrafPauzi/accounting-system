<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Endpoints for the two onboarding nag modals that float on top of
 * AuthenticatedLayout / PracticeLayout. Both write a single timestamp
 * on the user row and redirect back so the modal can disappear in
 * place without a page jump.
 *
 *   - dismiss():
 *       Marks the post-signup welcome tour seen. Fires once.
 *       Both "Skip for now" and "Get started" hit this — once
 *       welcomed_at is set the modal never shows again.
 *
 *   - dismissVerifyReminder():
 *       Stamps the verify-email reminder. Idempotent. Re-fires every
 *       day while the user is still unverified — the cadence is
 *       enforced client-side from `users.verify_reminder_at`. We
 *       deliberately don't gate the route on `verified` middleware
 *       because the entire point of the reminder is that the user
 *       is *not* verified yet.
 *
 * Both methods are auth-only and idempotent — re-clicking after a
 * refresh just re-stamps the same column.
 */
class WelcomeTourController extends Controller
{
    public function dismiss(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user && ! $user->welcomed_at) {
            $user->forceFill(['welcomed_at' => now()])->save();
        }
        return back();
    }

    public function dismissVerifyReminder(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            $user->forceFill(['verify_reminder_at' => now()])->save();
        }
        return back();
    }

    public function dismissChecklist(Request $request): RedirectResponse
    {
        $user = $request->user()?->fresh();
        if ($user && ! $user->isFirmUser() && \Illuminate\Support\Facades\Schema::connection($user->getConnectionName())->hasColumn('users', 'onboarding_steps')) {
            $progress = is_array($user->onboarding_steps) ? $user->onboarding_steps : [];
            $progress['dismissed_at'] = now()->toIso8601String();
            $user->forceFill(['onboarding_steps' => $progress])->save();
        }

        return back();
    }
}
