<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

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
    }
}
