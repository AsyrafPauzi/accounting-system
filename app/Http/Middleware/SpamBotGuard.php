<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two-factor bot defence on guest forms (login, register, forgot-password,
 * reset-password). Designed to catch dumb credential-stuffing scripts and
 * generic form-spam bots WITHOUT requiring the user to solve a CAPTCHA.
 *
 *   1. HONEYPOT: a hidden input named SpamBotGuard::HONEYPOT_FIELD is rendered
 *      via off-screen CSS. Real users never see or fill it. Headless bots
 *      that auto-fill every input will populate it. If we see ANY value in
 *      the honeypot, the request is silently rejected — we do NOT tell the
 *      bot it was caught (would give them a tuning signal).
 *
 *   2. TIME CHALLENGE: when the form is rendered, the browser receives an
 *      encrypted timestamp via the SpamBotGuard::TIMESTAMP_FIELD hidden
 *      input. On submit, we decrypt and require ≥ MIN_RENDER_MS to have
 *      elapsed. Bots that submit instantly trip this; humans always pass.
 *
 * Both signals are silent in production (we 422 with a generic validation
 * error) so attackers can't easily distinguish from a real form failure.
 */
class SpamBotGuard
{
    /** Hidden input name. Must match resources/js/Components/SpamBotFields.jsx. */
    public const HONEYPOT_FIELD = '_hp_email';

    /** Encrypted form-render timestamp field. */
    public const TIMESTAMP_FIELD = '_hp_ts';

    /** Minimum elapsed milliseconds between form render and submission. */
    private const MIN_RENDER_MS = 800;

    /** Maximum age of a render token (15 min — beyond this the form is stale). */
    private const MAX_RENDER_MS = 15 * 60 * 1000;

    public function handle(Request $request, Closure $next): Response
    {
        // Master switch. Defaults ON in production. We turn it OFF in
        // phpunit.xml so the existing auth feature tests (which POST
        // /login etc. without rendering the form first) keep working.
        // Direct middleware tests in SpamBotGuardTest re-enable it
        // locally to exercise the real logic.
        if (! config('security.spambot_guard.enabled', true)) {
            return $next($request);
        }

        // Only run on state-changing methods. The middleware is also
        // attached to GET-form routes elsewhere as a no-op (so we don't
        // break the /login GET that just renders Inertia).
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        // 1) HONEYPOT — any value in the hidden field is a bot.
        $honeypot = $request->input(self::HONEYPOT_FIELD);
        if ($honeypot !== null && $honeypot !== '') {
            $this->logTrip($request, 'honeypot');
            return $this->reject();
        }

        // 2) TIME CHALLENGE — token must exist, decrypt, and be ≥800ms old.
        $token = $request->input(self::TIMESTAMP_FIELD);
        if (! is_string($token) || $token === '') {
            $this->logTrip($request, 'missing_timestamp');
            return $this->reject();
        }

        try {
            $renderedAtMs = (int) Crypt::decryptString($token);
        } catch (DecryptException) {
            $this->logTrip($request, 'invalid_timestamp');
            return $this->reject();
        }

        $nowMs = (int) (microtime(true) * 1000);
        $elapsed = $nowMs - $renderedAtMs;

        if ($elapsed < self::MIN_RENDER_MS) {
            $this->logTrip($request, 'too_fast', ['elapsed_ms' => $elapsed]);
            return $this->reject();
        }
        if ($elapsed > self::MAX_RENDER_MS) {
            $this->logTrip($request, 'stale_form', ['elapsed_ms' => $elapsed]);
            return $this->reject();
        }

        // Strip the bot-defence fields so they don't leak into validators
        // or get persisted by accident.
        $request->request->remove(self::HONEYPOT_FIELD);
        $request->request->remove(self::TIMESTAMP_FIELD);

        return $next($request);
    }

    /**
     * Generate a fresh encrypted render-time token. Called by the auth
     * controllers when rendering the form so the browser receives a token
     * that's bound to "now".
     */
    public static function freshTimestamp(): string
    {
        return Crypt::encryptString((string) (int) (microtime(true) * 1000));
    }

    /**
     * Reject silently. We use 422 with a generic validation-shaped error
     * so the response is indistinguishable from a real form failure to
     * any bot scraping responses.
     */
    private function reject(): Response
    {
        return back()
            ->withInput()
            ->withErrors([
                'email' => 'Unable to process this request. Please reload the page and try again.',
            ]);
    }

    /**
     * Structured log for ops dashboards. Kept at INFO so we don't spam
     * error channels but can still grep for `SpamBotGuard` if traffic
     * looks weird. Never logs the email/password fields.
     */
    private function logTrip(Request $request, string $reason, array $extra = []): void
    {
        Log::info('[SpamBotGuard] tripped', array_merge([
            'reason' => $reason,
            'ip' => $request->ip(),
            'route' => $request->path(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 200),
        ], $extra));
    }
}
