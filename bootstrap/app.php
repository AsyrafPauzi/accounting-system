<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Providers\TenancyServiceProvider;
use App\Http\Middleware\EnsureSubscribed;
use App\Http\Middleware\CheckPermission;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        TenancyServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // Stateless external API for OAuth-style partner integrations
        // (today: Fin Persona). Auth on /api/v1/* is by Bearer api_key
        // → ApiKeyAuth middleware → tenancy initialise. /api/oauth/token
        // is the partner's server-to-server code-for-keys exchange and
        // is registered CSRF-exempt in the api stack (no session).
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // SaaS-only routes (publisher tenant admin, license issue,
        // subscription billing). Always loaded so route names always
        // resolve; each group enforces `saas.only` so they 404 on
        // self-hosted installs.
        then: function () {
            \Illuminate\Support\Facades\Route::middleware('web')
                ->group(__DIR__.'/../routes/saas.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\SanitizeInput::class,
        ]);

        // Toyyibpay calls these from its servers — there's no session, so CSRF
        // would always fail. The endpoints validate the request shape and rely
        // on the gateway-issued bill code as the authentication signal.
        $middleware->validateCsrfTokens(except: [
            '/subscription/webhook',
            '/subscription/webhook/extra-user',
            // Self-hosted heartbeat is a public API endpoint
            // authenticated by the license signature, not a session.
            '/api/self-hosted/heartbeat',
            // OAuth token-exchange is server-to-server (partner backend
            // posts client_secret + auth code). Pre-shared client_secret
            // is the auth signal, not a session cookie.
            '/api/oauth/token',
            // External partner /api/v1 surface. Authenticated by Bearer
            // api_key (and HMAC for writes), not by session — CSRF is
            // not the right defence here.
            '/api/v1/*',
        ]);

        $webMiddleware = [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\InitializeTenancyByLoggedInUser::class,
            \App\Http\Middleware\SetTenantLocale::class,
            // License gate is a no-op on SaaS; on self-hosted it
            // redirects to /install or /license-invalid based on
            // license + heartbeat state.
            \App\Http\Middleware\SelfHostedLicenseGate::class,
        ];

        // EnsureSubscribed enforces SaaS billing gates. On self-hosted
        // it would short-circuit in its own handle() anyway, but
        // skipping registration entirely saves one middleware
        // dispatch per request — small but worth it given every
        // authenticated page render goes through this stack.
        if (env('APP_DEPLOYMENT_MODE', 'saas') !== 'self_hosted') {
            $webMiddleware[] = EnsureSubscribed::class;
        }

        $middleware->web(append: $webMiddleware);

        // Global per-IP / per-user ceiling. ~5 req/sec sustained — catches
        // dumb scrapers and abusive clients before they hit per-route
        // throttles. Targeted limiters (auth, sensitive, creation) still
        // run on top with their own keys.
        $middleware->web(append: ['throttle:global']);
        $middleware->api(prepend: ['throttle:global']);

        $middleware->alias([
            'permission' => CheckPermission::class,
            'role'       => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'plan.permission' => \App\Http\Middleware\CheckPlanPermission::class,
            'log.sensitive' => \App\Http\Middleware\LogSensitiveAccess::class,
            // Returns 404 in self-hosted single-tenant mode. Used to
            // hide multi-tenant SaaS surface (Practice console, super-
            // admin tenants, subscription billing) on customer infra.
            'saas.only' => \App\Http\Middleware\SaasOnly::class,
            // Gates the Practice (Accountant) console: SaaS = always
            // on, self-hosted = on iff license carries `practice.console`.
            'feature.practice' => \App\Http\Middleware\FeaturePractice::class,
            // Enforces that the platform-level admin UI is only reachable
            // on `internal.bukucloud.com` (when INTERNAL_ADMIN_HOST is
            // set). No-op when the config is null (local dev).
            'internal.host' => \App\Http\Middleware\InternalAdminHost::class,
            // External /api/v1 partner authentication. Resolves
            // Authorization: Bearer <api_key> to a tenant_api_credentials
            // row, initialises tenancy, and re-checks the api.access
            // plan permission on every request.
            'api.key' => \App\Http\Middleware\ApiKeyAuth::class,
            // HMAC-SHA256 signature verifier for mutating /api/v1
            // requests. Must run after api.key (depends on the
            // resolved credential). See ApiSignatureVerifier docblock
            // for the canonical-string format.
            'api.signed' => \App\Http\Middleware\ApiSignatureVerifier::class,
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // PDPA defence: prevent any of these inputs being flashed back into
        // the session on a 422 / 500. If the framework re-renders the form
        // it'll see empty strings rather than the user's password etc.
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'new_password',
            'remember_token',
            '_token',
            'api_key',
            'gemini_api_key',
            'toyyibpay_secret',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'token',
            '_hp_email',
            '_hp_url',
            '_hp_ts',
            'authorize_extra_seat_charge',
            // OAuth "Connect to BukuCloud" partner credentials. Even
            // though these never live in form input under normal flow,
            // a 422 re-render after a webhook validation error could
            // otherwise leak them into rendered HTML.
            'client_secret',
            'transaction_signing_key',
            'signing_key',
            'oauth_state',
        ]);

        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expired. Please log in again.'], 419);
            }
            
            return redirect()->route('login')->with('info', 'Your session has expired. Please log in again.');
        });

        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }
            abort(403);
        });

        $exceptions->render(function (\Illuminate\Routing\Exceptions\InvalidSignatureException $e, $request) {
            if (! $request->routeIs('verification.verify')) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'This verification link is invalid or has expired.'], 403);
            }

            $user = $request->user();
            $isVerified = (bool) $user?->hasVerifiedEmail();
            $homeRoute = $user?->isFirmUser() ? 'practice.dashboard' : 'dashboard';

            return \Inertia\Inertia::render('Auth/InvalidVerificationLink', [
                'isVerified' => $isVerified,
                'homeUrl' => $user ? route($homeRoute, absolute: false) : route('login', absolute: false),
            ])->toResponse($request)->setStatusCode(403);
        });
    })->create();
