<?php

namespace App\Providers;

use App\Listeners\NotifyFailedJob;
use App\Listeners\SetSentryTenantContext;
use App\Models\FirmClient;
use App\Support\FirmActingPermissions;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Events\TenancyInitialized;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Behind ALB/HTTPS the app often sees HTTP internally; force HTTPS on generated
        // asset and route URLs so CSP 'self' matches the browser's https:// origin.
        if ($this->shouldForceHttps()) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Practice / Accountant track: firm users get tenant-level
        // permissions (invoices.create, bills.delete, customers.edit,
        // etc.) only while they're "acting into" a client tenant —
        // i.e. their session has a valid `acting_tenant_id` and the
        // FirmClient pivot is still active (re-checked by
        // InitializeTenancyByLoggedInUser on every request).
        //
        // Two safety constraints:
        //   1. We require `practice.access` on the firm user, so a
        //      revoked staff member loses tenant-write rights even if
        //      a stale session still has the acting id.
        //   2. We refuse to grant `admin.*` permissions through this
        //      path — those are central super-admin rights, never
        //      tenant-scoped, so we leave them to Spatie's normal
        //      role/permission resolution.
        \Illuminate\Support\Facades\Gate::before(function ($user, string $ability) {
            if (! $user || ! method_exists($user, 'isFirmUser') || ! $user->isFirmUser()) {
                return null;
            }
            if (! $user->can('practice.access')) {
                return null;
            }
            if (str_starts_with($ability, 'admin.') || str_starts_with($ability, 'practice.')) {
                return null;
            }
            // Only when actually acting on a client (tenancy
            // initialised by InitializeTenancyByLoggedInUser).
            if (! function_exists('tenancy') || ! tenancy()->initialized) {
                return null;
            }

            $level = FirmClient::query()
                ->where('firm_id', $user->firm_id)
                ->where('tenant_id', tenant('id'))
                ->where('status', 'active')
                ->value('permission_level');

            $allowed = FirmActingPermissions::allowedForLevel($level ?? 'viewer');

            if (! in_array($ability, $allowed, true)) {
                return false;
            }

            return true;
        });

        \Illuminate\Database\Eloquent\Model::shouldBeStrict(!$this->app->environment('production'));

        \Illuminate\Validation\Rules\Password::defaults(function () {
            $rule = \Illuminate\Validation\Rules\Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols();

            return app()->environment('production') ? $rule->uncompromised() : $rule;
        });

        $this->configureRateLimiting();
        $this->configureObservability();
    }

    protected function configureObservability(): void
    {
        Event::listen(TenancyInitialized::class, SetSentryTenantContext::class);
        Event::listen(JobFailed::class, NotifyFailedJob::class);
    }

    /**
     * Whether generated URLs should always use HTTPS (deployed environments behind a TLS-terminating proxy).
     */
    protected function shouldForceHttps(): bool
    {
        if ($this->app->environment(['local', 'testing'])) {
            return false;
        }

        if (env('FORCE_HTTPS') !== null) {
            return filter_var(env('FORCE_HTTPS'), FILTER_VALIDATE_BOOL);
        }

        return true;
    }

    /**
     * Configure the rate limiters for the application.
     *
     * Three layers, all keyed by user-id (or IP if unauthenticated):
     *   - global   : a wide ceiling on ANY request from a single source.
     *                Catches scrapers / spammy clients that aren't hitting
     *                a specific endpoint hard enough to trip the more
     *                targeted limiters below. ~5 req/sec sustained — well
     *                above any realistic human use of the SPA.
     *   - creation : applied to write endpoints that create resources.
     *   - sensitive: tight ceiling for password reset / billing endpoints.
     *   - auth     : guest auth endpoints (login/register/forgot/reset).
     *                Tighter than 'creation' and keyed by IP+email so a
     *                shared-IP office can still log in but a credential
     *                stuffer can't sweep through 1k accounts from one box.
     */
    protected function configureRateLimiting(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('global', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(300)
                ->by($request->user()?->id ?: $request->ip());
        });

        \Illuminate\Support\Facades\RateLimiter::for('creation', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(20)
                ->by($request->user()?->id ?: $request->ip());
        });

        \Illuminate\Support\Facades\RateLimiter::for('sensitive', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)
                ->by($request->user()?->id ?: $request->ip());
        });

        \Illuminate\Support\Facades\RateLimiter::for('auth', function (\Illuminate\Http\Request $request) {
            $emailKey = strtolower((string) $request->input('email', 'guest'));
            return [
                // Per-IP ceiling stops a single bad host from sweeping accounts.
                \Illuminate\Cache\RateLimiting\Limit::perMinute(20)->by($request->ip()),
                // Per-email ceiling stops password-spray attacks across IPs
                // targeted at one specific user.
                \Illuminate\Cache\RateLimiting\Limit::perMinute(8)->by('auth-email:'.$emailKey),
            ];
        });

        // External API throttle. Keyed by api_key when present so each
        // credential gets its own bucket. Falls back to IP when no bearer
        // key is sent.
        \Illuminate\Support\Facades\RateLimiter::for('api-v1', function (\Illuminate\Http\Request $request) {
            $perMinute = max(1, (int) config('api.rate_limit_per_minute', 600));

            $auth = (string) $request->header('Authorization', '');
            if (preg_match('/^Bearer\s+(\S+)$/i', $auth, $m)) {
                return \Illuminate\Cache\RateLimiting\Limit::perMinute($perMinute)
                    ->by('api-v1:'.hash('sha256', $m[1]));
            }
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by($request->ip());
        });
    }
}
