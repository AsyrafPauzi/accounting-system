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
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
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
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\InitializeTenancyByLoggedInUser::class,
            \App\Http\Middleware\SetTenantLocale::class,
            EnsureSubscribed::class,
        ]);

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
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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
    })->create();
