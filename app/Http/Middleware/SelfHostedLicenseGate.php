<?php

namespace App\Http\Middleware;

use App\Console\Commands\SelfHostedHeartbeat;
use App\Services\Licensing\LicenseService;
use App\Support\Deployment;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * In self-hosted mode, gate the app on license validity.
 *
 * Behaviour:
 *
 *   - License missing / unconfigured → redirect to /install (the
 *     setup wizard). Existing customers re-running a fresh image
 *     land here too; the wizard saves the key into a writable env
 *     bag (Phase D).
 *
 *   - License malformed / bad signature → redirect to a 'license
 *     invalid' page. There's no graceful path; the customer must
 *     re-paste a valid key.
 *
 *   - License revoked or expired → put the app in 'read-only mode'
 *     (we expose this via the request and let controllers decide;
 *     in practice we return a 'license_expired' page to most routes).
 *
 *   - Last successful heartbeat older than the grace period → ditto.
 *     The grace period is `deployment.heartbeat_grace_days` (default
 *     14) so a customer who's offline for a couple weeks isn't
 *     instantly locked out.
 *
 * SaaS mode short-circuits at the first `if`.
 */
class SelfHostedLicenseGate
{
    public const DEFAULT_GRACE_DAYS = 14;

    /** Routes that always pass through, even while locked. */
    private array $allowedWhenLocked = [
        'login', 'logout', 'license.invalid', 'install.', 'privacy', 'verification.',
    ];

    public function __construct(private readonly LicenseService $svc)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! Deployment::isSelfHosted()) {
            return $next($request);
        }

        $status = $this->svc->status()['status'] ?? 'missing';

        // No license at all → redirect to the install wizard.
        if (in_array($status, ['missing', 'unconfigured'], true)) {
            if ($this->isAllowed($request)) return $next($request);
            return redirect()->route('install.show');
        }

        if (in_array($status, ['malformed', 'bad_signature'], true)) {
            if ($this->isAllowed($request)) return $next($request);
            return redirect()->route('license.invalid')->with('reason', $status);
        }

        if (in_array($status, ['expired', 'revoked'], true) || $this->heartbeatStale()) {
            // Pin a flag on the request so individual controllers can
            // render a degraded UI if they want to be polite about it.
            $request->attributes->set('license.locked', true);
            if ($this->isAllowed($request)) return $next($request);
            return redirect()->route('license.invalid')->with('reason', $status === 'valid' ? 'heartbeat_stale' : $status);
        }

        return $next($request);
    }

    private function isAllowed(Request $request): bool
    {
        $name = (string) ($request->route()?->getName() ?? '');
        if ($name === '') return false;
        foreach ($this->allowedWhenLocked as $prefix) {
            if (str_starts_with($name, $prefix)) return true;
        }
        return false;
    }

    private function heartbeatStale(): bool
    {
        $graceDays = (int) (config('deployment.heartbeat_grace_days') ?? self::DEFAULT_GRACE_DAYS);
        $lastIso = Cache::get(SelfHostedHeartbeat::LAST_OK_KEY);
        if (! $lastIso) {
            // No heartbeat yet — not stale until the grace period elapses
            // since *first run*. We don't know that, so we use the
            // license `issued_at` as the start.
            $claims = $this->svc->claims();
            if (! $claims || empty($claims['issued_at'])) return false;
            return Carbon::parse($claims['issued_at'])->addDays($graceDays)->isPast();
        }
        return Carbon::parse($lastIso)->addDays($graceDays)->isPast();
    }
}
