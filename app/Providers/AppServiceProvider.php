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
     */
    protected function configureRateLimiting(): void
    {
        \Illuminate\Support\Facades\RateLimiter::for('creation', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        \Illuminate\Support\Facades\RateLimiter::for('sensitive', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });
    }
}
