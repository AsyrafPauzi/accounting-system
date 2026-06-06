<?php

use App\Http\Controllers\TenantAdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SaaS-only routes
|--------------------------------------------------------------------------
|
| Routes that only exist on the multi-tenant SaaS deployment (the
| publisher's `bukucloud.com` install). They are loaded unconditionally
| from `routes/web.php` so route names always resolve, but each group
| carries `saas.only` middleware that returns 404 in self-hosted mode.
|
| Why a separate file instead of inlining?
|   1. Visual quarantine — anyone reading `web.php` sees the customer-
|      facing surface; this file is for vendor-side admin tooling.
|   2. The `internal.host` middleware in the publisher cluster scopes
|      these to `internal.bukucloud.com` (away from the public app
|      hostname), which is easier to reason about as one block.
|   3. We can later evolve to "load only on saas" if route registration
|      cost becomes a concern, without untangling middleware chains.
|
| What lives here:
|   - /subscription/*                      (tenant SaaS billing UI)
|   - /admin/tenants/*                     (publisher tenant management)
|   - /admin/self-hosted/*                 (license issue / revoke / heartbeat-watch)
|   - /admin/platform                      (patch broadcaster)
|   - /admin/tenants/{tenant}/practice     (per-tenant feature toggle)
|
| What stays in web.php (because it works in both modes):
|   - /practice/*                          (gated by feature.practice; Enterprise unlocks)
|   - /admin/users, /admin/branding, /admin/audit-logs
|   - everything else
*/

Route::middleware(['auth', 'verified'])->group(function () {
    // ── Tenant SaaS billing ───────────────────────────────────────────
    // SubscriptionController endpoints. Self-hosted customers use the
    // license-driven /settings/plan page instead — no checkout, no
    // gateway. EnsureSubscribed already excludes these from the gate.
    Route::middleware('saas.only')->group(function () {
        Route::get('/subscription', [\App\Http\Controllers\SubscriptionController::class, 'index'])->name('subscription.index');
        Route::post('/subscription/checkout', [\App\Http\Controllers\SubscriptionController::class, 'checkout'])->name('subscription.checkout');
        Route::post('/subscription/cancel-pending', [\App\Http\Controllers\SubscriptionController::class, 'cancelPendingChange'])->name('subscription.cancel_pending');
        Route::get('/subscription/success', [\App\Http\Controllers\SubscriptionController::class, 'success'])->name('subscription.success');
        Route::get('/subscription/callback', [\App\Http\Controllers\SubscriptionController::class, 'callback'])->name('subscription.callback');
    });

    // ── Publisher: platform admin (license + patch + per-tenant flags) ─
    // `internal.host` makes this surface reachable only on the
    // platform-admin subdomain when configured. `log.sensitive` writes
    // every read/write into central audit_logs so we can answer "did
    // a super-admin touch this customer's data?" forensically.
    Route::middleware(['internal.host', 'saas.only', 'permission:admin.tenants', 'log.sensitive'])->group(function () {
        Route::get('/admin/self-hosted', [\App\Http\Controllers\Admin\SelfHostedInstallController::class, 'index'])
            ->name('admin.self-hosted.index');
        Route::post('/admin/self-hosted/issue', [\App\Http\Controllers\Admin\SelfHostedInstallController::class, 'issue'])
            ->name('admin.self-hosted.issue');
        Route::post('/admin/self-hosted/{install}/revoke', [\App\Http\Controllers\Admin\SelfHostedInstallController::class, 'revoke'])
            ->name('admin.self-hosted.revoke');
        Route::post('/admin/self-hosted/{install}/unrevoke', [\App\Http\Controllers\Admin\SelfHostedInstallController::class, 'unrevoke'])
            ->name('admin.self-hosted.unrevoke');

        // Patch broadcaster — sets the "latest available version"
        // advertised to every customer install on its next heartbeat.
        Route::get('/admin/platform', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'show'])
            ->name('admin.platform.show');
        Route::post('/admin/platform', [\App\Http\Controllers\Admin\PlatformSettingsController::class, 'update'])
            ->name('admin.platform.update');

        // Per-tenant accountant-feature toggle.
        Route::patch('/admin/tenants/{tenant}/practice', [\App\Http\Controllers\Admin\TenantFeatureController::class, 'togglePractice'])
            ->name('admin.tenants.practice.toggle');
    });

    // ── Publisher: tenant management ─────────────────────────────────
    // Used by the SaaS super-admin to inspect / impersonate / back up /
    // delete customer tenants. Gated by `admin.tenants` permission.
    Route::middleware(['internal.host', 'saas.only', 'permission:admin.tenants', 'log.sensitive'])->group(function () {
        Route::get('/admin/tenants', [TenantAdminController::class, 'index'])->name('admin.tenants.index');

        Route::middleware('throttle:sensitive')->group(function () {
            Route::get('/admin/tenants/{tenant}/backup', [TenantAdminController::class, 'backup'])->name('admin.tenants.backup');
            Route::delete('/admin/tenants/{tenant}', [TenantAdminController::class, 'destroy'])->name('admin.tenants.destroy');
            Route::post('/admin/tenants/impersonate/{user}', [TenantAdminController::class, 'impersonate'])->name('admin.tenants.impersonate');
        });

        Route::middleware('throttle:creation')->group(function () {
            Route::put('/admin/tenants/{tenant}/subscription', [TenantAdminController::class, 'assignSubscription'])->name('admin.tenants.subscription.assign');
            Route::post('/admin/tenants/{tenant}/subscription/extend', [TenantAdminController::class, 'extendSubscription'])->name('admin.tenants.subscription.extend');
            Route::post('/admin/tenants/{tenant}/subscription/cancel', [TenantAdminController::class, 'cancelSubscription'])->name('admin.tenants.subscription.cancel');
            Route::post('/admin/tenants/{tenant}/subscription/lifetime', [TenantAdminController::class, 'grantLifetimeSubscription'])->name('admin.tenants.subscription.lifetime');
        });
    });

    // Stop-impersonating is reachable even after the user lost the
    // `admin.tenants` permission (they ARE the impersonated user at
    // that point) — that's why it sits outside the gated group.
    Route::post('/admin/tenants/stop-impersonating', [TenantAdminController::class, 'stopImpersonating'])
        ->middleware('throttle:sensitive')
        ->name('admin.tenants.stop-impersonating');
});
