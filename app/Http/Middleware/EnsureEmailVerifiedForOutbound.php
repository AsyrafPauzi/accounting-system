<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;

/**
 * Blocks routes that send outbound email to third parties (invoice
 * delivery, customer statements, client invites, etc.) until the
 * authenticated user has verified their own email address.
 *
 * Why a separate middleware from Laravel's built-in `verified`?
 *
 *   The default `verified` middleware redirects to `verification.notice`,
 *   which yanks the user off the page they were on (e.g. the invoice
 *   they were trying to email). For inline actions we want to keep
 *   them in context, so we redirect `back()` with a flash error AND
 *   re-arm the verify reminder modal so it pops up immediately with
 *   a "Resend verification email" button — i.e. fix-it-now UX rather
 *   than redirect-and-leave-them-stranded UX.
 *
 *   Anti-abuse rationale: a fraudulent signup with an unverified
 *   email could otherwise use our Resend account as a free spam
 *   relay. Hard-blocking outbound email until ownership is proven
 *   keeps the sending domain's reputation clean and gives Resend's
 *   deliverability machine a stable reputation signal.
 */
class EnsureEmailVerifiedForOutbound
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user instanceof MustVerifyEmail || $user->hasVerifiedEmail()) {
            return $next($request);
        }

        // Re-arm the verify reminder so the modal pops on the back-
        // redirect. The user is actively trying to send mail right
        // now, so this is the right moment to nudge — not 2 days
        // from now when the normal cool-down expires.
        $user->forceFill(['verify_reminder_at' => null])->save();

        $message = 'Verify your email address before sending messages from your account. We\'ve re-opened the verification prompt — use it to resend the confirmation link.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return back()->with('error', $message);
    }
}
