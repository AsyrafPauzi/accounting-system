<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\AccountsPayableController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\TenantUserController;
use App\Http\Controllers\TenantAdminController;
use App\Http\Controllers\AdminPlanController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AdminAuditLogController;
use App\Http\Controllers\ChartOfAccountsController;
use App\Http\Controllers\GeneralLedgerController;
use App\Http\Controllers\ProfitAndLossController;
use App\Http\Controllers\BalanceSheetController;
use App\Http\Controllers\CashflowSummaryController;
use App\Http\Controllers\AgedReceivablesController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\ReportsHubController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// --- Public Routes ---
Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/privacy', [\App\Http\Controllers\PrivacyController::class, 'show'])
    ->name('privacy.show');

Route::get('/public/invoices/{uuid}/download', [InvoiceController::class, 'publicDownloadPdf'])
    ->name('public.invoices.download')
    ->middleware('signed');

Route::get('/public/invoices/{uuid}/open.gif', [InvoiceController::class, 'publicPixel'])
    ->name('public.invoices.pixel')
    ->middleware('signed');

Route::get('/public/invoices/{uuid}/pay-return', [InvoiceController::class, 'publicPayReturn'])
    ->name('public.invoices.pay.return')
    ->middleware('signed');

Route::get('/public/credit-notes/{uuid}/download', [CreditNoteController::class, 'publicDownloadPdf'])
    ->name('public.credit-notes.download')
    ->middleware('signed');

Route::get('/public/estimates/{uuid}/download', [\App\Http\Controllers\EstimateController::class, 'publicDownloadPdf'])
    ->name('public.estimates.download')
    ->middleware('signed');

// --- Toyyibpay Webhook (Server-to-Server) ---
Route::post('/subscription/webhook', [SubscriptionController::class, 'webhook'])->name('subscription.webhook');
Route::post('/subscription/webhook/extra-user', [SubscriptionController::class, 'webhookExtraUser'])->name('subscription.webhook.extra_user');
Route::post('/subscription/webhook/copilot-credits', [SubscriptionController::class, 'webhookCopilotCredits'])->name('subscription.webhook.copilot_credits');
Route::post('/pay/toyyibpay/callback', [InvoiceController::class, 'toyyibpayCallback'])->name('pay.toyyibpay.callback');
Route::post('/pay/billplz/callback', [InvoiceController::class, 'billplzCallback'])->name('pay.billplz.callback');
Route::post('/pay/commercepay/callback', [InvoiceController::class, 'commercepayCallback'])->name('pay.commercepay.callback');

// --- Self-hosted publisher API ---
// Customer installs ping this once a day with their license + stats.
// It's intentionally public — the license signature *is* the auth.
Route::post('/api/self-hosted/heartbeat', [\App\Http\Controllers\Api\HeartbeatController::class, 'store'])
    ->middleware(['throttle:30,1'])
    ->name('api.self-hosted.heartbeat');

// License-invalid landing page (used by SelfHostedLicenseGate when
// the running install can't validate). Returns a static Inertia page.
Route::get('/license-invalid', function (\Illuminate\Http\Request $request) {
    return inertia('License/Invalid', ['reason' => $request->session()->get('reason', 'unknown')]);
})->name('license.invalid');

// First-run install wizard — only reachable on self-hosted, and only
// before the default tenant has an admin user. Throttled hard since
// the form drops a license key into .env on POST.
Route::middleware('throttle:6,60')->group(function () {
    Route::get('/install', [\App\Http\Controllers\Install\InstallController::class, 'show'])
        ->name('install.show');
    Route::post('/install', [\App\Http\Controllers\Install\InstallController::class, 'store'])
        ->name('install.store');
});

// --- Dashboard, Profile & App (Auth Required) ---
//
// Email verification is a soft requirement now: unverified users can
// still use the app, but a reminder modal nags them every 2 days
// until they verify (see resources/js/Components/VerifyEmailReminderModal.jsx
// and `users.verify_reminder_at`). Hard-blocking on `verified` here used
// to bounce freshly-registered users to /verify-email and stranded them
// if email delivery was delayed — we deliberately moved that to a
// product-level nag instead of a route-level wall.
Route::middleware(['auth'])->group(function () {
    // Subscription pages, publisher tenant admin, license issue/revoke,
    // patch broadcaster, etc. — all SaaS-only routes are extracted to
    // routes/saas.php for visual quarantine. They're all gated by
    // `saas.only` middleware, so they 404 on self-hosted.

    // --- Practice (Accountant track) ---
    // Routes the firm reaches without "acting into" a client. Any
    // firm-user can read; mutating actions are gated by their own
    // permission (handled inside the controllers).
    //
    // `feature.practice` returns 404 unless this deployment has the
    // Practice console enabled — SaaS always does; self-hosted only
    // does when the license carries the `practice.console` feature
    // (i.e. self-hosted Enterprise tier).
    Route::middleware(['feature.practice', 'permission:practice.access'])->prefix('practice')->name('practice.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Practice\PracticeDashboardController::class, 'index'])->name('dashboard');
        Route::get('/ar', [\App\Http\Controllers\Practice\PracticeArController::class, 'index'])->name('ar');
        // Switcher — sets `acting_tenant_id` and bounces to the client
        // dashboard. Posting (not GET) prevents accidental switching
        // via prefetch / link previews.
        Route::post('/switch/{tenantId}', [\App\Http\Controllers\Practice\ClientSwitcherController::class, 'switch'])
            ->where('tenantId', '[A-Za-z0-9_-]+')
            ->name('switch');
        Route::post('/exit', [\App\Http\Controllers\Practice\ClientSwitcherController::class, 'exit'])->name('exit');

        // Plan picker — firm-owners land here from the in-console
        // upgrade banner once they outgrow Practice Free. SaaS-only;
        // self-hosted Enterprise uses license-driven caps so there's
        // no plan picker on those installs. The controller methods
        // also defend themselves (`abort_if(!saasFeaturesEnabled())`)
        // so removing the middleware here is safe — defence in depth
        // without two layers of route-level gating.
        Route::middleware('saas.only')->group(function () {
            Route::get('/plan', [\App\Http\Controllers\Practice\PracticeBillingController::class, 'show'])->name('plan');
            Route::post('/plan/checkout', [\App\Http\Controllers\Practice\PracticeBillingController::class, 'checkout'])->name('plan.checkout');
        });

        // Add Client — firm provisions a new tenant or invites an
        // existing SME's email. Cap-gated by the firm's plan.
        Route::get('/clients/create', [\App\Http\Controllers\Practice\AddClientController::class, 'show'])->name('clients.create');
        Route::post('/clients/create', [\App\Http\Controllers\Practice\AddClientController::class, 'createNew'])->name('clients.create.new');
        Route::post('/clients/invite', [\App\Http\Controllers\Practice\AddClientController::class, 'inviteExisting'])
            ->middleware(\App\Http\Middleware\EnsureEmailVerifiedForOutbound::class)
            ->name('clients.invite');

        // Unlink a client. Firm-owners only via the dedicated permission;
        // staff need the action escalated. Tenant data is preserved —
        // see ClientLinkController for the rationale.
        Route::delete('/clients/{tenantId}/unlink', [\App\Http\Controllers\Practice\ClientLinkController::class, 'destroy'])
            ->where('tenantId', '[A-Za-z0-9_-]+')
            ->middleware('permission:practice.clients.unlink')
            ->name('clients.unlink');
    });

    // Tenant → firm invite acceptance. Lives outside `practice.` prefix
    // because we want "firm.invite.*" naming for clarity, but it still
    // requires an authenticated firm-owner (enforced in the controller).
    //
    // The historical `withoutMiddleware('verified')` calls became no-ops
    // when we moved verification from a route-wall to a soft reminder
    // modal. Kept here as defence-in-depth: if a future change re-adds
    // `verified` to the parent group, the firm-invite token flow must
    // remain reachable to unverified users — the token in the URL is
    // the secret, and the inviting SME chose where to share it.
    Route::get('/firm-invite/{token}', [\App\Http\Controllers\Practice\FirmInvitationController::class, 'show'])
        ->where('token', '[A-Za-z0-9]+')
        ->withoutMiddleware('verified')
        ->name('firm.invite.accept');
    Route::post('/firm-invite/{token}', [\App\Http\Controllers\Practice\FirmInvitationController::class, 'accept'])
        ->where('token', '[A-Za-z0-9]+')
        ->withoutMiddleware('verified')
        ->name('firm.invite.accept.store');

    // Onboarding tour dismissal. Both "Skip" and "Get started" on the
    // post-signup welcome modal hit this single endpoint to stamp
    // welcomed_at = now() so the modal never shows again.
    Route::post('/onboarding/dismiss', [\App\Http\Controllers\WelcomeTourController::class, 'dismiss'])
        ->name('onboarding.dismiss');

    // Verify-email reminder dismissal. Stamps verify_reminder_at = now()
    // so the modal cools down for 2 days. Deliberately auth-only (not
    // verified-gated) because the user clicking this *is* the unverified
    // user — the whole point is to keep them moving without verifying.
    Route::post('/onboarding/verify-reminder/dismiss', [\App\Http\Controllers\WelcomeTourController::class, 'dismissVerifyReminder'])
        ->name('onboarding.verify-reminder.dismiss');

    // Tenant-side: SME admin invites a firm to take over their books.
    Route::get('/settings/invite-firm', [\App\Http\Controllers\Settings\InviteFirmController::class, 'show'])
        ->name('settings.invite-firm.show');
    Route::post('/settings/invite-firm', [\App\Http\Controllers\Settings\InviteFirmController::class, 'store'])
        ->name('settings.invite-firm.store');
    Route::delete('/settings/invite-firm/{invitation}', [\App\Http\Controllers\Settings\InviteFirmController::class, 'destroy'])
        ->whereNumber('invitation')
        ->name('settings.invite-firm.destroy');

    // Tenant-side: accept / decline a firm-initiated invite.
    Route::post('/settings/invite-firm/{invitation}/accept', [\App\Http\Controllers\Settings\InviteFirmController::class, 'acceptIncoming'])
        ->whereNumber('invitation')
        ->name('settings.invite-firm.accept');
    Route::post('/settings/invite-firm/{invitation}/decline', [\App\Http\Controllers\Settings\InviteFirmController::class, 'declineIncoming'])
        ->whereNumber('invitation')
        ->name('settings.invite-firm.decline');

    // Dashboard (paid-only via EnsureSubscribed)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware(['permission:copilot.use', 'plan.permission:copilot.use'])->group(function () {
        Route::get('/copilot/chat', [\App\Http\Controllers\CopilotController::class, 'show'])->name('copilot.show');
        Route::post('/copilot/chat', [\App\Http\Controllers\CopilotController::class, 'chat'])->name('copilot.chat');
        Route::post('/copilot/confirm/{id}', [\App\Http\Controllers\CopilotController::class, 'confirm'])->whereNumber('id')->name('copilot.confirm');
        Route::post('/copilot/cancel/{id}', [\App\Http\Controllers\CopilotController::class, 'cancel'])->whereNumber('id')->name('copilot.cancel');
        Route::post('/copilot/clear', [\App\Http\Controllers\CopilotController::class, 'clear'])->name('copilot.clear');
    });

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/theme', [ProfileController::class, 'theme'])->name('profile.theme');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Company settings (tenant-level)
    Route::get('/settings/company', [CompanySettingsController::class, 'edit'])->name('settings.company');
    Route::patch('/settings/company', [CompanySettingsController::class, 'update'])->name('settings.company.update');
    Route::post('/settings/company/myinvois-test', [CompanySettingsController::class, 'testMyInvois'])->name('settings.company.myinvois-test');
    Route::get('/settings/plan', [SubscriptionController::class, 'planSettings'])->name('settings.plan.index');
    Route::post('/settings/plan/copilot-credits', [SubscriptionController::class, 'buyCopilotCredits'])
        ->middleware(['permission:copilot.use', 'plan.permission:copilot.use', 'throttle:sensitive'])
        ->name('settings.plan.copilot_credits');

    // API & Integrations — generate and revoke tenant API keys. Plan-gated by
    // `api.access` (Solo+); Spatie permission `integrations.view` is
    // also required so non-admin team members on the same tenant can
    // be excluded from the page even if the plan grants access.
    Route::middleware(['plan.permission:api.access', 'permission:integrations.view'])->group(function () {
        Route::get('/settings/integrations', [\App\Http\Controllers\Settings\IntegrationsController::class, 'index'])
            ->name('settings.integrations.index');
        Route::post('/settings/integrations', [\App\Http\Controllers\Settings\IntegrationsController::class, 'store'])
            ->middleware('throttle:creation')
            ->name('settings.integrations.store');
        Route::post('/settings/integrations/{id}/revoke', [\App\Http\Controllers\Settings\IntegrationsController::class, 'revoke'])
            ->whereNumber('id')
            ->name('settings.integrations.revoke');
    });

    // PDPA: right of access (data export) + right of erasure (account
    // deletion) — both available to any authenticated user. Throttled
    // independently from the global limiter because building the zip is
    // CPU-heavier than a normal page render.
    // PDPA right of access / right of erasure — both audited so we can
    // answer "did the user actually request this?" if anyone asks later.
    Route::middleware('log.sensitive')->group(function () {
        Route::get('/settings/data-export', [\App\Http\Controllers\Settings\DataExportController::class, 'show'])
            ->name('settings.data_export.show');
        // GET + signed URL for the actual download — see the comment in
        // DataExportController::show() for the reasoning. The `signed`
        // middleware verifies the URL was generated by us and hasn't
        // expired; combined with `throttle:6,60` this gives the same
        // safety profile as a CSRF-protected POST without the brittle
        // meta-tag plumbing.
        Route::get('/settings/data-export/download', [\App\Http\Controllers\Settings\DataExportController::class, 'download'])
            ->middleware(['signed', 'throttle:6,60'])
            ->name('settings.data_export.download');

        Route::get('/settings/delete-account', [\App\Http\Controllers\Settings\AccountErasureController::class, 'show'])
            ->name('settings.account_erase.show');
        Route::post('/settings/delete-account', [\App\Http\Controllers\Settings\AccountErasureController::class, 'request'])
            ->middleware('throttle:5,60')
            ->name('settings.account_erase.request');
        Route::post('/settings/delete-account/cancel', [\App\Http\Controllers\Settings\AccountErasureController::class, 'cancel'])
            ->name('settings.account_erase.cancel');
    });

    Route::middleware(['permission:audit-logs.view', 'plan.permission:audit-logs.view'])->group(function () {
        Route::get('/settings/audit-logs', [\App\Http\Controllers\AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    // --- Audit Module ---
    Route::middleware(['permission:audit.view', 'plan.permission:audit-logs.view'])->group(function () {
        Route::get('/audit', [\App\Http\Controllers\AuditController::class, 'index'])->name('audit.index');
        Route::get('/audit/report', [\App\Http\Controllers\AuditController::class, 'report'])->name('audit.report');
        Route::post('/audit/{id}/verify', [\App\Http\Controllers\AuditController::class, 'verify'])->name('audit.verify');
        Route::post('/audit/{id}/flag', [\App\Http\Controllers\AuditController::class, 'flag'])->name('audit.flag');
    });

    // Team / users (same tenant only)
    Route::middleware(['permission:users.view', 'plan.permission:users.view'])->group(function () {
        Route::get('/settings/team', [TenantUserController::class, 'index'])->name('settings.team.index');
    });
    Route::post('/settings/team', [TenantUserController::class, 'store'])
        ->middleware(['permission:users.create', 'plan.permission:users.view', 'throttle:creation'])
        ->name('settings.team.store');
    Route::middleware(['permission:users.edit', 'plan.permission:users.view'])->group(function () {
        Route::patch('/settings/team/{user}', [TenantUserController::class, 'update'])->name('settings.team.update');
    });
    Route::middleware(['permission:users.delete', 'plan.permission:users.view'])->group(function () {
        Route::delete('/settings/team/{user}', [TenantUserController::class, 'destroy'])->name('settings.team.destroy');
    });

    // Publisher-side SaaS admin (tenant management, license issue,
    // patch broadcaster, /admin/tenants/{tenant}/practice toggle,
    // /admin/tenants/stop-impersonating) lives in routes/saas.php.
    // See that file for the full inventory.

    // --- Admin: Plan catalog ---
    Route::middleware(['internal.host', 'permission:admin.plans'])->group(function () {
        Route::get('/admin/plans', [AdminPlanController::class, 'index'])->name('admin.plans.index');
        Route::get('/admin/plans/create', [AdminPlanController::class, 'create'])->name('admin.plans.create');
        Route::post('/admin/plans', [AdminPlanController::class, 'store'])
            ->middleware('throttle:creation')
            ->name('admin.plans.store');
        Route::get('/admin/plans/{plan}/edit', [AdminPlanController::class, 'edit'])->name('admin.plans.edit');
        Route::put('/admin/plans/{plan}', [AdminPlanController::class, 'update'])
            ->middleware('throttle:creation')
            ->name('admin.plans.update');
    });

    // --- Admin: Central user management ---
    Route::middleware('permission:admin.users')->group(function () {
        Route::get('/admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
        Route::post('/admin/users', [AdminUserController::class, 'store'])
            ->middleware('throttle:creation')
            ->name('admin.users.store');
        Route::patch('/admin/users/{user}/role', [AdminUserController::class, 'updateRole'])
            ->middleware('throttle:creation')
            ->name('admin.users.role');
        Route::post('/admin/users/{user}/password-reset', [AdminUserController::class, 'sendPasswordReset'])
            ->middleware('throttle:sensitive')
            ->name('admin.users.password-reset');
        Route::patch('/admin/users/{user}/toggle-active', [AdminUserController::class, 'toggleActive'])
            ->middleware('throttle:creation')
            ->name('admin.users.toggle-active');
    });

    // --- Admin: Platform audit log ---
    Route::middleware('permission:admin.audit')->group(function () {
        Route::get('/admin/audit-logs', [AdminAuditLogController::class, 'index'])->name('admin.audit-logs.index');
    });

    // --- Admin: Branding (self-hosted only — controller short-circuits in SaaS) ---
    Route::middleware('role:super-admin')->group(function () {
        Route::get('/admin/branding', [\App\Http\Controllers\Admin\BrandingController::class, 'edit'])
            ->name('admin.branding.edit');
        Route::post('/admin/branding', [\App\Http\Controllers\Admin\BrandingController::class, 'update'])
            ->middleware('throttle:creation')
            ->name('admin.branding.update');
    });

    // --- Admin: OCR provider settings (super-admin only — applies to all tenants) ---
    Route::middleware('role:super-admin')->group(function () {
        Route::get('/admin/ocr', [\App\Http\Controllers\Admin\OcrSettingsController::class, 'edit'])
            ->name('admin.ocr.edit');
        Route::post('/admin/ocr', [\App\Http\Controllers\Admin\OcrSettingsController::class, 'update'])
            ->middleware('throttle:creation')
            ->name('admin.ocr.update');
        Route::post('/admin/ocr/test', [\App\Http\Controllers\Admin\OcrSettingsController::class, 'test'])
            ->middleware('throttle:sensitive')
            ->name('admin.ocr.test');
    });

    // --- Invoices ---
    Route::middleware('permission:invoices.create')->group(function () {
        Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::get('/invoices/cash-sale', [InvoiceController::class, 'cashSaleCreate'])->name('invoices.cash-sale');
        Route::get('/invoices/batch', [InvoiceController::class, 'batchCreate'])->name('invoices.batch');
        Route::post('/invoices', [InvoiceController::class, 'store'])
            ->middleware('throttle:creation')
            ->name('invoices.store');
        Route::post('/invoices/cash-sale', [InvoiceController::class, 'cashSaleStore'])
            ->middleware('throttle:creation')
            ->name('invoices.cash-sale.store');
        Route::post('/invoices/batch', [InvoiceController::class, 'batchStore'])
            ->middleware('throttle:creation')
            ->name('invoices.batch.store');
        Route::post('/invoices/{id}/duplicate', [InvoiceController::class, 'duplicate'])
            ->whereNumber('id')
            ->name('invoices.duplicate');
    });
    Route::middleware('permission:invoices.view')->group(function () {
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/bulk-pdf', [InvoiceController::class, 'bulkPdf'])->name('invoices.bulk-pdf');
        Route::get('/invoices/{id}', [InvoiceController::class, 'show'])->whereNumber('id')->name('invoices.show');
        Route::get('/invoices/{id}/edit', [InvoiceController::class, 'edit'])->whereNumber('id')->name('invoices.edit');
        Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'downloadPdf'])->whereNumber('id')->name('invoices.pdf');
        Route::get('/invoices/{id}/preview', [InvoiceController::class, 'previewPdf'])->whereNumber('id')->name('invoices.preview');
        Route::get('/invoices/{id}/payments/{paymentId}/receipt', [InvoiceController::class, 'paymentReceipt'])->whereNumber('id')->name('invoices.payment-receipt');
    });
    Route::middleware('permission:invoices.edit')->group(function () {
        Route::put('/invoices/{id}', [InvoiceController::class, 'update'])->name('invoices.update');
    });
    Route::middleware('permission:invoices.delete')->group(function () {
        Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    });
    Route::middleware('permission:invoices.post')->group(function () {
        Route::post('/invoices/{id}/post', [InvoiceController::class, 'postInvoice'])->name('invoices.post');
    });
    Route::middleware('permission:invoices.void')->group(function () {
        Route::post('/invoices/{id}/void', [InvoiceController::class, 'voidInvoice'])->name('invoices.void');
    });
    Route::middleware(['permission:invoices.email', 'plan.permission:invoices.email', \App\Http\Middleware\EnsureEmailVerifiedForOutbound::class])->group(function () {
        Route::post('/invoices/bulk-email', [InvoiceController::class, 'bulkEmail'])->name('invoices.bulk-email');
        Route::post('/invoices/{id}/email', [InvoiceController::class, 'emailPdf'])->name('invoices.email');
    });
    Route::middleware(['permission:invoices.record-payment', 'plan.permission:invoices.record-payment'])->group(function () {
        Route::post('/invoices/{id}/payments', [InvoiceController::class, 'recordPayment'])->name('invoices.record-payment');
        Route::post('/invoices/{id}/payments/{paymentId}/reverse', [InvoiceController::class, 'reversePayment'])->whereNumber(['id', 'paymentId'])->name('invoices.reverse-payment');
    });
    Route::middleware('permission:invoices.edit')->group(function () {
        Route::post('/invoices/{id}/attachments', [InvoiceController::class, 'attach'])->whereNumber('id')->name('invoices.attach');
        Route::delete('/invoices/{id}/attachments/{attachmentId}', [InvoiceController::class, 'detach'])->whereNumber('id')->name('invoices.detach');
        Route::post('/invoices/{id}/recurring', [InvoiceController::class, 'createRecurring'])->whereNumber('id')->name('invoices.create-recurring');
        Route::post('/invoices/{id}/late-fee', [InvoiceController::class, 'issueLateFee'])->whereNumber('id')->name('invoices.late-fee');
        Route::post('/invoices/{id}/pay-now', [InvoiceController::class, 'payNow'])->whereNumber('id')->name('invoices.pay-now');
        Route::post('/invoices/{id}/reminders', [InvoiceController::class, 'updateReminders'])->whereNumber('id')->name('invoices.reminders');
    });
    Route::middleware(['permission:myinvois.submit', 'plan.permission:myinvois.submit'])->group(function () {
        Route::post('/invoices/{id}/myinvois', [InvoiceController::class, 'submitMyInvois'])->whereNumber('id')->name('invoices.myinvois.submit');
        Route::post('/invoices/{id}/myinvois/refresh', [InvoiceController::class, 'refreshMyInvois'])->whereNumber('id')->name('invoices.myinvois.refresh');
        Route::post('/invoices/{id}/myinvois/cancel', [InvoiceController::class, 'cancelMyInvois'])->whereNumber('id')->name('invoices.myinvois.cancel');
        Route::get('/myinvois/consolidated', [\App\Http\Controllers\ConsolidatedEInvoiceController::class, 'index'])->name('myinvois.consolidated.index');
        Route::post('/myinvois/consolidated', [\App\Http\Controllers\ConsolidatedEInvoiceController::class, 'store'])->name('myinvois.consolidated.store');
        Route::post('/myinvois/consolidated/{id}/cancel', [\App\Http\Controllers\ConsolidatedEInvoiceController::class, 'cancel'])->name('myinvois.consolidated.cancel');
    });

    // --- Credit Notes ---
    Route::middleware(['permission:credit-notes.view', 'plan.permission:credit-notes.view'])->group(function () {
        Route::get('/credit-notes', [CreditNoteController::class, 'index'])->name('credit-notes.index');
        Route::get('/credit-notes/{id}', [CreditNoteController::class, 'show'])->whereNumber('id')->name('credit-notes.show');
        Route::get('/credit-notes/{id}/pdf', [CreditNoteController::class, 'downloadPdf'])->whereNumber('id')->name('credit-notes.pdf');
    });
    Route::middleware(['permission:credit-notes.create', 'plan.permission:credit-notes.view'])->group(function () {
        Route::get('/credit-notes/create', [CreditNoteController::class, 'createStandalone'])->name('credit-notes.create-standalone');
        Route::get('/credit-notes/create/{invoice_id}', [CreditNoteController::class, 'create'])->name('credit-notes.create');
        Route::post('/credit-notes', [CreditNoteController::class, 'store'])->name('credit-notes.store');
        Route::get('/credit-notes/{id}/edit', [CreditNoteController::class, 'edit'])->whereNumber('id')->name('credit-notes.edit');
        Route::put('/credit-notes/{id}', [CreditNoteController::class, 'update'])->whereNumber('id')->name('credit-notes.update');
        Route::post('/credit-notes/{id}/apply', [CreditNoteController::class, 'apply'])->whereNumber('id')->name('credit-notes.apply');
        Route::post('/credit-notes/{id}/refund', [CreditNoteController::class, 'refund'])->whereNumber('id')->name('credit-notes.refund');
        Route::post('/credit-notes/{id}/void', [CreditNoteController::class, 'void'])->whereNumber('id')->name('credit-notes.void');
        Route::post('/credit-notes/{id}/myinvois', [CreditNoteController::class, 'submitMyInvois'])->whereNumber('id')->name('credit-notes.myinvois.submit');
        Route::post('/credit-notes/{id}/myinvois/refresh', [CreditNoteController::class, 'refreshMyInvois'])->whereNumber('id')->name('credit-notes.myinvois.refresh');
        Route::post('/credit-notes/{id}/myinvois/cancel', [CreditNoteController::class, 'cancelMyInvois'])->whereNumber('id')->name('credit-notes.myinvois.cancel');
    });
    Route::middleware(['permission:invoices.email', 'plan.permission:invoices.email', \App\Http\Middleware\EnsureEmailVerifiedForOutbound::class])->group(function () {
        Route::post('/credit-notes/{id}/email', [CreditNoteController::class, 'emailPdf'])->whereNumber('id')->name('credit-notes.email');
        Route::post('/debit-notes/{id}/email', [\App\Http\Controllers\DebitNoteController::class, 'emailPdf'])->whereNumber('id')->name('debit-notes.email');
        Route::post('/sales-orders/{id}/email', [\App\Http\Controllers\SalesOrderController::class, 'emailPdf'])->whereNumber('id')->name('sales-orders.email');
        Route::post('/delivery-orders/{id}/email', [\App\Http\Controllers\DeliveryOrderController::class, 'emailPdf'])->whereNumber('id')->name('delivery-orders.email');
        Route::post('/ar-deposits/{id}/email', [\App\Http\Controllers\ArDepositController::class, 'emailPdf'])->whereNumber('id')->name('ar-deposits.email');
        Route::post('/purchase-orders/{id}/email', [\App\Http\Controllers\PurchaseOrderController::class, 'emailPdf'])->whereNumber('id')->name('purchase-orders.email');
        Route::post('/goods-receipts/{id}/email', [\App\Http\Controllers\GoodsReceiptController::class, 'emailPdf'])->whereNumber('id')->name('goods-receipts.email');
    });

    // --- Debit Notes ---
    Route::middleware(['permission:debit-notes.view', 'plan.permission:credit-notes.view'])->group(function () {
        Route::get('/debit-notes', [\App\Http\Controllers\DebitNoteController::class, 'index'])->name('debit-notes.index');
        Route::get('/debit-notes/{id}', [\App\Http\Controllers\DebitNoteController::class, 'show'])->whereNumber('id')->name('debit-notes.show');
        Route::get('/debit-notes/{id}/pdf', [\App\Http\Controllers\DebitNoteController::class, 'downloadPdf'])->whereNumber('id')->name('debit-notes.pdf');
    });
    Route::middleware(['permission:debit-notes.create', 'plan.permission:credit-notes.view'])->group(function () {
        Route::get('/debit-notes/create', [\App\Http\Controllers\DebitNoteController::class, 'create'])->name('debit-notes.create');
        Route::post('/debit-notes', [\App\Http\Controllers\DebitNoteController::class, 'store'])->name('debit-notes.store');
        Route::get('/debit-notes/{id}/edit', [\App\Http\Controllers\DebitNoteController::class, 'edit'])->whereNumber('id')->name('debit-notes.edit');
        Route::put('/debit-notes/{id}', [\App\Http\Controllers\DebitNoteController::class, 'update'])->whereNumber('id')->name('debit-notes.update');
        Route::post('/debit-notes/{id}/void', [\App\Http\Controllers\DebitNoteController::class, 'void'])->whereNumber('id')->name('debit-notes.void');
        Route::post('/debit-notes/{id}/myinvois', [\App\Http\Controllers\DebitNoteController::class, 'submitMyInvois'])->whereNumber('id')->name('debit-notes.myinvois.submit');
        Route::post('/debit-notes/{id}/myinvois/refresh', [\App\Http\Controllers\DebitNoteController::class, 'refreshMyInvois'])->whereNumber('id')->name('debit-notes.myinvois.refresh');
    });

    // --- Sales Orders ---
    Route::middleware(['permission:sales-orders.view', 'plan.permission:estimates.view', 'goods.flow'])->group(function () {
        Route::get('/sales-orders', [\App\Http\Controllers\SalesOrderController::class, 'index'])->name('sales-orders.index');
        Route::get('/sales-orders/{id}', [\App\Http\Controllers\SalesOrderController::class, 'show'])->whereNumber('id')->name('sales-orders.show');
        Route::get('/sales-orders/{id}/pdf', [\App\Http\Controllers\SalesOrderController::class, 'downloadPdf'])->whereNumber('id')->name('sales-orders.pdf');
    });
    Route::middleware(['permission:sales-orders.create', 'plan.permission:estimates.view', 'goods.flow'])->group(function () {
        Route::get('/sales-orders/batch', [\App\Http\Controllers\SalesOrderController::class, 'batchCreate'])->name('sales-orders.batch');
        Route::post('/sales-orders/batch', [\App\Http\Controllers\SalesOrderController::class, 'batchStore'])
            ->middleware('throttle:creation')
            ->name('sales-orders.batch.store');
        Route::get('/sales-orders/create', [\App\Http\Controllers\SalesOrderController::class, 'create'])->name('sales-orders.create');
        Route::post('/sales-orders', [\App\Http\Controllers\SalesOrderController::class, 'store'])->name('sales-orders.store');
        Route::post('/sales-orders/{id}/deliver', [\App\Http\Controllers\SalesOrderController::class, 'deliver'])->whereNumber('id')->name('sales-orders.deliver');
        Route::post('/sales-orders/{id}/invoice', [\App\Http\Controllers\SalesOrderController::class, 'convertToInvoice'])->whereNumber('id')->name('sales-orders.invoice');
    });
    Route::middleware(['permission:sales-orders.edit', 'plan.permission:estimates.view', 'goods.flow'])->group(function () {
        Route::get('/sales-orders/{id}/edit', [\App\Http\Controllers\SalesOrderController::class, 'edit'])->whereNumber('id')->name('sales-orders.edit');
        Route::put('/sales-orders/{id}', [\App\Http\Controllers\SalesOrderController::class, 'update'])->whereNumber('id')->name('sales-orders.update');
        Route::post('/sales-orders/{id}/cancel', [\App\Http\Controllers\SalesOrderController::class, 'cancel'])->whereNumber('id')->name('sales-orders.cancel');
    });

    // --- Delivery Orders ---
    Route::middleware(['permission:delivery-orders.view', 'plan.permission:estimates.view', 'goods.flow'])->group(function () {
        Route::get('/delivery-orders', [\App\Http\Controllers\DeliveryOrderController::class, 'index'])->name('delivery-orders.index');
        Route::get('/delivery-orders/{id}', [\App\Http\Controllers\DeliveryOrderController::class, 'show'])->whereNumber('id')->name('delivery-orders.show');
        Route::get('/delivery-orders/{id}/pdf', [\App\Http\Controllers\DeliveryOrderController::class, 'downloadPdf'])->whereNumber('id')->name('delivery-orders.pdf');
        Route::post('/delivery-orders/{id}/invoice', [\App\Http\Controllers\DeliveryOrderController::class, 'convertToInvoice'])->whereNumber('id')->name('delivery-orders.invoice');
    });
    Route::middleware(['permission:delivery-orders.edit', 'plan.permission:estimates.view', 'goods.flow'])->group(function () {
        Route::get('/delivery-orders/{id}/edit', [\App\Http\Controllers\DeliveryOrderController::class, 'edit'])->whereNumber('id')->name('delivery-orders.edit');
        Route::put('/delivery-orders/{id}', [\App\Http\Controllers\DeliveryOrderController::class, 'update'])->whereNumber('id')->name('delivery-orders.update');
        Route::post('/delivery-orders/{id}/return', [\App\Http\Controllers\DeliveryOrderController::class, 'returnFull'])->whereNumber('id')->name('delivery-orders.return');
    });

    // --- Customer deposits (AR) ---
    Route::middleware(['permission:invoices.record-payment', 'plan.permission:invoices.record-payment'])->group(function () {
        Route::get('/ar-deposits', [\App\Http\Controllers\ArDepositController::class, 'index'])->name('ar-deposits.index');
        Route::get('/ar-deposits/create', [\App\Http\Controllers\ArDepositController::class, 'create'])->name('ar-deposits.create');
        Route::post('/ar-deposits', [\App\Http\Controllers\ArDepositController::class, 'store'])->name('ar-deposits.store');
        Route::get('/ar-deposits/{id}/edit', [\App\Http\Controllers\ArDepositController::class, 'edit'])->whereNumber('id')->name('ar-deposits.edit');
        Route::put('/ar-deposits/{id}', [\App\Http\Controllers\ArDepositController::class, 'update'])->whereNumber('id')->name('ar-deposits.update');
        Route::get('/ar-deposits/{id}/pdf', [\App\Http\Controllers\ArDepositController::class, 'downloadPdf'])->whereNumber('id')->name('ar-deposits.pdf');
        Route::get('/ar-deposits/{id}', [\App\Http\Controllers\ArDepositController::class, 'show'])->whereNumber('id')->name('ar-deposits.show');
        Route::post('/ar-deposits/{id}/apply', [\App\Http\Controllers\ArDepositController::class, 'apply'])->whereNumber('id')->name('ar-deposits.apply');
        Route::post('/ar-deposits/{id}/refund', [\App\Http\Controllers\ArDepositController::class, 'refund'])->whereNumber('id')->name('ar-deposits.refund');
        Route::post('/ar-deposits/{id}/forfeit', [\App\Http\Controllers\ArDepositController::class, 'forfeit'])->whereNumber('id')->name('ar-deposits.forfeit');
    });

    // --- Suppliers ---
    Route::middleware(['permission:suppliers.view', 'plan.permission:suppliers.view'])->group(function () {
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    });
    Route::middleware(['permission:suppliers.create', 'plan.permission:suppliers.view'])->group(function () {
        Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('/suppliers', [SupplierController::class, 'store'])
            ->middleware('throttle:creation')
            ->name('suppliers.store');
    });
    Route::middleware(['permission:suppliers.view', 'plan.permission:suppliers.view'])->group(function () {
        Route::get('/suppliers/{id}', [SupplierController::class, 'show'])->name('suppliers.show');
    });
    Route::middleware(['permission:suppliers.edit', 'plan.permission:suppliers.view'])->group(function () {
        Route::get('/suppliers/{id}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('/suppliers/{id}', [SupplierController::class, 'update'])->name('suppliers.update');
    });
    Route::middleware(['permission:suppliers.delete', 'plan.permission:suppliers.view'])->group(function () {
        Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    });

    // --- Bills ---
    Route::middleware(['permission:bills.view', 'plan.permission:bills.view'])->group(function () {
        Route::get('/bills', [BillController::class, 'index'])->name('bills.index');
        Route::get('/bills/{id}/edit', [BillController::class, 'edit'])->name('bills.edit');
        Route::get('/bills/{id?}/receipt', [BillController::class, 'showReceipt'])->name('bills.receipt');
        Route::get('/bills/{id}', [BillController::class, 'show'])->whereNumber('id')->name('bills.show');
    });
    Route::middleware(['permission:bills.create', 'plan.permission:bills.view'])->group(function () {
        Route::get('/bills/create', [BillController::class, 'create'])->name('bills.create');
        Route::get('/bills/batch', [BillController::class, 'batchCreate'])->name('bills.batch');
        Route::post('/bills/batch', [BillController::class, 'batchStore'])
            ->middleware('throttle:creation')
            ->name('bills.batch.store');
        Route::post('/bills', [BillController::class, 'store'])
            ->middleware('throttle:creation')
            ->name('bills.store');
        // OCR is the differentiating bullet on Solo+ — gate the receipt
        // upload endpoint specifically (the plain bill form stays open).
        Route::post('/bills/upload-receipt', [BillController::class, 'uploadReceipt'])
            ->middleware('plan.permission:ocr.use')
            ->name('bills.upload-receipt');
        Route::get('/bills/ocr-status', [BillController::class, 'ocrStatus'])
            ->middleware('plan.permission:ocr.use')
            ->name('bills.ocr-status');
    });
    Route::middleware(['permission:bills.edit', 'plan.permission:bills.view'])->group(function () {
        Route::put('/bills/{id}', [BillController::class, 'update'])->name('bills.update');
    });
    Route::middleware(['permission:bills.delete', 'plan.permission:bills.view'])->group(function () {
        Route::delete('/bills/{id}', [BillController::class, 'destroy'])->name('bills.destroy');
    });
    Route::middleware(['permission:bills.post', 'plan.permission:bills.view'])->group(function () {
        Route::post('/bills/{id}/post', [BillController::class, 'postBill'])->name('bills.post');
    });
    Route::middleware(['permission:bills.void', 'plan.permission:bills.view'])->group(function () {
        Route::post('/bills/{id}/void', [BillController::class, 'voidBill'])->name('bills.void');
    });
    Route::middleware(['permission:bills.record-payment', 'plan.permission:bills.view'])->group(function () {
        Route::post('/bills/{id}/payments', [BillController::class, 'recordPayment'])->name('bills.record-payment');
    });
    Route::middleware(['permission:myinvois.submit', 'plan.permission:myinvois.submit'])->group(function () {
        Route::post('/bills/{id}/myinvois', [BillController::class, 'submitMyInvois'])->whereNumber('id')->name('bills.myinvois.submit');
        Route::post('/bills/{id}/myinvois/refresh', [BillController::class, 'refreshMyInvois'])->whereNumber('id')->name('bills.myinvois.refresh');
        Route::post('/bills/{id}/myinvois/cancel', [BillController::class, 'cancelMyInvois'])->whereNumber('id')->name('bills.myinvois.cancel');
    });

    Route::middleware(['permission:bills.view', 'plan.permission:bills.view'])->group(function () {
        Route::get('/purchase-orders', [\App\Http\Controllers\PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('/purchase-orders/{id}', [\App\Http\Controllers\PurchaseOrderController::class, 'show'])->whereNumber('id')->name('purchase-orders.show');
        Route::get('/purchase-orders/{id}/pdf', [\App\Http\Controllers\PurchaseOrderController::class, 'downloadPdf'])->whereNumber('id')->name('purchase-orders.pdf');
        Route::get('/goods-receipts', [\App\Http\Controllers\GoodsReceiptController::class, 'index'])->name('goods-receipts.index');
        Route::get('/goods-receipts/{id}', [\App\Http\Controllers\GoodsReceiptController::class, 'show'])->whereNumber('id')->name('goods-receipts.show');
        Route::get('/goods-receipts/{id}/pdf', [\App\Http\Controllers\GoodsReceiptController::class, 'downloadPdf'])->whereNumber('id')->name('goods-receipts.pdf');
        Route::get('/supplier-credit-notes', [\App\Http\Controllers\SupplierCreditNoteController::class, 'index'])->name('supplier-credit-notes.index');
        Route::get('/supplier-credit-notes/{id}', [\App\Http\Controllers\SupplierCreditNoteController::class, 'show'])->whereNumber('id')->name('supplier-credit-notes.show');
        Route::get('/supplier-credit-notes/{id}/pdf', [\App\Http\Controllers\SupplierCreditNoteController::class, 'downloadPdf'])->whereNumber('id')->name('supplier-credit-notes.pdf');
        Route::get('/supplier-debit-notes', [\App\Http\Controllers\SupplierDebitNoteController::class, 'index'])->name('supplier-debit-notes.index');
        Route::get('/supplier-debit-notes/{id}', [\App\Http\Controllers\SupplierDebitNoteController::class, 'show'])->whereNumber('id')->name('supplier-debit-notes.show');
        Route::get('/supplier-debit-notes/{id}/pdf', [\App\Http\Controllers\SupplierDebitNoteController::class, 'downloadPdf'])->whereNumber('id')->name('supplier-debit-notes.pdf');
        Route::get('/recurring-bills', [\App\Http\Controllers\RecurringBillController::class, 'index'])->name('recurring-bills.index');
        Route::get('/recurring-bills/{id}', [\App\Http\Controllers\RecurringBillController::class, 'show'])->whereNumber('id')->name('recurring-bills.show');
        Route::get('/supplier-statements', [\App\Http\Controllers\SupplierStatementController::class, 'index'])->name('supplier-statements.index');
        Route::get('/supplier-statements/{supplierId}', [\App\Http\Controllers\SupplierStatementController::class, 'show'])->whereNumber('supplierId')->name('supplier-statements.show');
    });
    Route::middleware(['permission:bills.create', 'plan.permission:bills.view'])->group(function () {
        Route::get('/purchase-orders/{id}/edit', [\App\Http\Controllers\PurchaseOrderController::class, 'edit'])->whereNumber('id')->name('purchase-orders.edit');
        Route::put('/purchase-orders/{id}', [\App\Http\Controllers\PurchaseOrderController::class, 'update'])->whereNumber('id')->name('purchase-orders.update');
        Route::post('/purchase-orders/{id}/cancel', [\App\Http\Controllers\PurchaseOrderController::class, 'cancel'])->whereNumber('id')->name('purchase-orders.cancel');
        Route::put('/goods-receipts/{id}', [\App\Http\Controllers\GoodsReceiptController::class, 'update'])->whereNumber('id')->name('goods-receipts.update');
        Route::post('/goods-receipts/{id}/return', [\App\Http\Controllers\GoodsReceiptController::class, 'returnFull'])->whereNumber('id')->name('goods-receipts.return');
        Route::get('/purchase-orders/create', [\App\Http\Controllers\PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::post('/purchase-orders', [\App\Http\Controllers\PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
        Route::post('/purchase-orders/{id}/receive', [\App\Http\Controllers\PurchaseOrderController::class, 'receive'])->whereNumber('id')->name('purchase-orders.receive');
        Route::post('/purchase-orders/{id}/bill', [\App\Http\Controllers\PurchaseOrderController::class, 'convertToBill'])->whereNumber('id')->name('purchase-orders.bill');
        Route::post('/goods-receipts/{id}/bill', [\App\Http\Controllers\GoodsReceiptController::class, 'convertToBill'])->whereNumber('id')->name('goods-receipts.bill');
        Route::get('/supplier-credit-notes/create', [\App\Http\Controllers\SupplierCreditNoteController::class, 'create'])->name('supplier-credit-notes.create');
        Route::post('/supplier-credit-notes', [\App\Http\Controllers\SupplierCreditNoteController::class, 'store'])->name('supplier-credit-notes.store');
        Route::post('/supplier-credit-notes/{id}/apply', [\App\Http\Controllers\SupplierCreditNoteController::class, 'apply'])->whereNumber('id')->name('supplier-credit-notes.apply');
        Route::post('/supplier-credit-notes/{id}/refund', [\App\Http\Controllers\SupplierCreditNoteController::class, 'refund'])->whereNumber('id')->name('supplier-credit-notes.refund');
        Route::post('/supplier-credit-notes/{id}/void', [\App\Http\Controllers\SupplierCreditNoteController::class, 'void'])->whereNumber('id')->name('supplier-credit-notes.void');
        Route::get('/supplier-debit-notes/create', [\App\Http\Controllers\SupplierDebitNoteController::class, 'create'])->name('supplier-debit-notes.create');
        Route::post('/supplier-debit-notes', [\App\Http\Controllers\SupplierDebitNoteController::class, 'store'])->name('supplier-debit-notes.store');
        Route::post('/supplier-debit-notes/{id}/void', [\App\Http\Controllers\SupplierDebitNoteController::class, 'void'])->whereNumber('id')->name('supplier-debit-notes.void');
        Route::get('/recurring-bills/create', [\App\Http\Controllers\RecurringBillController::class, 'create'])->name('recurring-bills.create');
        Route::post('/recurring-bills', [\App\Http\Controllers\RecurringBillController::class, 'store'])->name('recurring-bills.store');
        Route::post('/recurring-bills/{id}/run', [\App\Http\Controllers\RecurringBillController::class, 'runNow'])->whereNumber('id')->name('recurring-bills.run');
        Route::post('/recurring-bills/{id}/toggle', [\App\Http\Controllers\RecurringBillController::class, 'toggle'])->whereNumber('id')->name('recurring-bills.toggle');
    });
    Route::middleware(['permission:bills.record-payment', 'plan.permission:bills.view'])->group(function () {
        Route::get('/ap-deposits', [\App\Http\Controllers\ApDepositController::class, 'index'])->name('ap-deposits.index');
        Route::get('/ap-deposits/create', [\App\Http\Controllers\ApDepositController::class, 'create'])->name('ap-deposits.create');
        Route::post('/ap-deposits', [\App\Http\Controllers\ApDepositController::class, 'store'])->name('ap-deposits.store');
        Route::get('/ap-deposits/{id}', [\App\Http\Controllers\ApDepositController::class, 'show'])->whereNumber('id')->name('ap-deposits.show');
        Route::post('/ap-deposits/{id}/apply', [\App\Http\Controllers\ApDepositController::class, 'apply'])->whereNumber('id')->name('ap-deposits.apply');
    });

    // --- Chart of Accounts ---
    Route::middleware(['permission:accounts.view', 'plan.permission:accounts.view'])->group(function () {
        Route::get('/chart-of-accounts', [ChartOfAccountsController::class, 'index'])->name('chart-of-accounts.index');
        
        Route::middleware('permission:reports.export.limited|reports.export.full')->group(function () {
            Route::get('/chart-of-accounts/export/csv', [ChartOfAccountsController::class, 'exportCsv'])->name('chart-of-accounts.export.csv');
        });
        
        Route::middleware('permission:reports.export.full')->group(function () {
            Route::get('/chart-of-accounts/export/pdf', [ChartOfAccountsController::class, 'exportPdf'])->name('chart-of-accounts.export.pdf');
        });
    });
    Route::middleware(['permission:accounts.create', 'plan.permission:accounts.create'])->group(function () {
        Route::get('/chart-of-accounts/create', [ChartOfAccountsController::class, 'create'])->name('chart-of-accounts.create');
        Route::post('/chart-of-accounts', [ChartOfAccountsController::class, 'store'])
            ->middleware('throttle:creation')
            ->name('chart-of-accounts.store');
        Route::post('/chart-of-accounts/seed-default', [ChartOfAccountsController::class, 'seedDefault'])
            ->middleware('throttle:creation')
            ->name('chart-of-accounts.seed-default');
    });
    Route::middleware(['permission:accounts.edit', 'plan.permission:accounts.edit'])->group(function () {
        Route::get('/chart-of-accounts/{id}/edit', [ChartOfAccountsController::class, 'edit'])->name('chart-of-accounts.edit');
        Route::put('/chart-of-accounts/{id}', [ChartOfAccountsController::class, 'update'])->name('chart-of-accounts.update');
    });
    Route::middleware(['permission:accounts.delete', 'plan.permission:accounts.delete'])->group(function () {
        Route::delete('/chart-of-accounts/{id}', [ChartOfAccountsController::class, 'destroy'])->name('chart-of-accounts.destroy');
    });

    // --- General Ledger ---
    Route::middleware(['permission:general-ledger.view', 'plan.permission:general-ledger.view'])->group(function () {
        Route::get('/general-ledger', [GeneralLedgerController::class, 'index'])->name('general-ledger.index');
        Route::get('/general-ledger/report', [GeneralLedgerController::class, 'report'])->name('general-ledger.report');
        Route::get('/trial-balance', [App\Http\Controllers\TrialBalanceController::class, 'index'])->name('trial-balance.index');
        Route::get('/general-ledger/{id}', [GeneralLedgerController::class, 'show'])->name('general-ledger.show');

        Route::middleware('permission:reports.export.limited|reports.export.full')->group(function () {
            Route::get('/general-ledger/export/csv', [GeneralLedgerController::class, 'exportCsv'])->name('general-ledger.export.csv');
            Route::get('/general-ledger/report/export/csv', [GeneralLedgerController::class, 'exportReportCsv'])->name('general-ledger.report.export.csv');
        });

        Route::middleware('permission:reports.export.full')->group(function () {
            Route::get('/general-ledger/export/pdf', [GeneralLedgerController::class, 'exportPdf'])->name('general-ledger.export.pdf');
            Route::get('/general-ledger/report/export/pdf', [GeneralLedgerController::class, 'exportReportPdf'])->name('general-ledger.report.export.pdf');
        });
    });

    // --- Transactions feed (bank/cash movements) + quick deposit / withdrawal ---
    Route::middleware(['permission:journal.view', 'plan.permission:journal.view'])->group(function () {
        Route::get('/transactions', [\App\Http\Controllers\TransactionsController::class, 'index'])->name('transactions.index');
    });

    Route::middleware(['permission:journal.create', 'plan.permission:journal.create'])->group(function () {
        Route::get('/transactions/deposit', [\App\Http\Controllers\TransactionsController::class, 'createDeposit'])->name('transactions.deposit.create');
        Route::post('/transactions/deposit', [\App\Http\Controllers\TransactionsController::class, 'storeDeposit'])
            ->middleware('throttle:creation')
            ->name('transactions.deposit.store');

        Route::get('/transactions/withdrawal', [\App\Http\Controllers\TransactionsController::class, 'createWithdrawal'])->name('transactions.withdrawal.create');
        Route::post('/transactions/withdrawal', [\App\Http\Controllers\TransactionsController::class, 'storeWithdrawal'])
            ->middleware('throttle:creation')
            ->name('transactions.withdrawal.store');
    });

    // --- Manual Journals ---
    Route::middleware(['permission:journal.view', 'plan.permission:journal.view'])->group(function () {
        Route::get('/journal/manual', [JournalController::class, 'index'])->name('journal.index');
    });

    Route::middleware(['permission:journal.create', 'plan.permission:journal.create'])->group(function () {
        Route::get('/journal/manual/create', [JournalController::class, 'create'])->name('journal.create');
        Route::post('/journal/manual', [JournalController::class, 'store'])
            ->middleware('throttle:creation')
            ->name('journal.store');
    });

    Route::middleware(['permission:journal.edit', 'plan.permission:journal.create'])->group(function () {
        Route::get('/journal/manual/{journal}/edit', [JournalController::class, 'edit'])->name('journal.edit');
        Route::put('/journal/manual/{journal}', [JournalController::class, 'update'])->name('journal.update');
    });

    Route::middleware(['permission:journal.post', 'plan.permission:journal.create'])->group(function () {
        Route::post('/journal/manual/{journal}/post', [JournalController::class, 'post'])->name('journal.post');
    });

    Route::middleware(['permission:journal.delete', 'plan.permission:journal.create'])->group(function () {
        Route::delete('/journal/manual/{journal}', [JournalController::class, 'destroy'])->name('journal.destroy');
    });

    // --- Payroll ---
    // Advertised as a Corporate-tier bullet, so gate by `payroll.run` plan
    // permission as well as the role-based `journal.create` (a payroll run
    // posts a manual journal under the hood, so the role still applies).
    Route::middleware(['permission:journal.create', 'plan.permission:payroll.run'])->group(function () {
        Route::get('/payroll', [\App\Http\Controllers\PayrollController::class, 'create'])->name('payroll.create');
        Route::post('/payroll', [\App\Http\Controllers\PayrollController::class, 'store'])
            ->middleware('throttle:creation')
            ->name('payroll.store');
    });

    // --- Reports Hub ---
    Route::middleware(['permission:reports.view', 'plan.permission:reports.view'])->group(function () {
        Route::get('/reports', [ReportsHubController::class, 'index'])->name('reports.index');
    });

    // --- Individual Reports (Plan Gated) ---
    Route::middleware(['permission:reports.profit-loss', 'plan.permission:reports.profit-loss'])->group(function () {
        Route::get('/profit-and-loss', [ProfitAndLossController::class, 'index'])->name('profit-and-loss.index');
    });

    Route::middleware(['permission:reports.sales', 'plan.permission:reports.sales'])->group(function () {
        Route::get('/reports/sales', [\App\Http\Controllers\SalesReportController::class, 'index'])->name('reports.sales.index');
    });

    Route::middleware(['permission:reports.balance-sheet', 'plan.permission:reports.balance-sheet'])->group(function () {
        Route::get('/balance-sheet', [BalanceSheetController::class, 'index'])->name('balance-sheet.index');
    });

    Route::middleware(['permission:reports.cashflow', 'plan.permission:reports.cashflow'])->group(function () {
        Route::get('/cashflow-summary', [CashflowSummaryController::class, 'index'])->name('cashflow-summary.index');
    });

    Route::middleware(['permission:reports.aged-reports', 'plan.permission:reports.aged-reports'])->group(function () {
        Route::get('/aged-receivables', [AgedReceivablesController::class, 'index'])->name('aged-receivables.index');
        Route::get('/accounts-payable', [AccountsPayableController::class, 'index'])->name('accounts-payable.index');
    });

    // --- Sales Tax Report (output vs input tax) ---
    Route::middleware(['permission:reports.sales-tax', 'plan.permission:reports.sales-tax'])->group(function () {
        Route::get('/reports/sales-tax', [\App\Http\Controllers\SalesTaxReportController::class, 'index'])->name('reports.sales-tax.index');
    });

    // --- Income by Customer (paid vs unpaid breakdown) ---
    Route::middleware(['permission:reports.sales', 'plan.permission:reports.sales'])->group(function () {
        Route::get('/reports/income-by-customer', [\App\Http\Controllers\IncomeByCustomerController::class, 'index'])->name('reports.income-by-customer.index');
    });

    // --- Customer Credits (open credit-note balances) ---
    Route::middleware(['permission:reports.customer-credits', 'plan.permission:reports.customer-credits'])->group(function () {
        Route::get('/reports/customer-credits', [\App\Http\Controllers\CustomerCreditsController::class, 'index'])->name('reports.customer-credits.index');
    });

    // --- Purchases by Vendor ---
    Route::middleware(['permission:reports.purchases-by-vendor', 'plan.permission:reports.purchases-by-vendor'])->group(function () {
        Route::get('/reports/purchases-by-vendor', [\App\Http\Controllers\PurchasesByVendorController::class, 'index'])->name('reports.purchases-by-vendor.index');
    });

    // --- Exports (Differentiated by Limited/Full) ---
    Route::middleware(['permission:reports.export.limited|reports.export.full'])->group(function () {
        Route::get('/profit-and-loss/export/csv', [ProfitAndLossController::class, 'exportCsv'])->name('profit-and-loss.export.csv');
        Route::get('/balance-sheet/export/csv', [BalanceSheetController::class, 'exportCsv'])->name('balance-sheet.export.csv');
    });

    Route::middleware(['permission:reports.export.full'])->group(function () {
        Route::get('/profit-and-loss/export/pdf', [ProfitAndLossController::class, 'exportPdf'])->name('profit-and-loss.export.pdf');
        Route::get('/balance-sheet/export/pdf', [BalanceSheetController::class, 'exportPdf'])->name('balance-sheet.export.pdf');
    });

    // --- Customers ---
    // Static paths must be registered before /customers/{id} or "create" is treated as an id.
    Route::middleware('permission:customers.view')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    });
    Route::middleware('permission:customers.create')->group(function () {
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        
        Route::middleware('throttle:creation')->group(function () {
            Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
            Route::post('/customers/quick-store', [CustomerController::class, 'quickStore'])->name('customers.quick-store');
        });
    });
    Route::middleware('permission:customers.view')->group(function () {
        Route::get('/customers/{id}', [CustomerController::class, 'show'])->name('customers.show');
    });
    Route::middleware('permission:customers.edit')->group(function () {
        Route::get('/customers/{id}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{id}', [CustomerController::class, 'update'])->name('customers.update');
    });
    Route::middleware('permission:customers.delete')->group(function () {
        Route::delete('/customers/{id}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    });

    // --- Customer Statements (Balance Forward report) ---
    // Growth+ on the SaaS pricing page ("Customer statements & portal").
    // Role-wise it reuses customers.view since anyone who can see a
    // customer can in principle see their statement — the gate that
    // matters here is the plan one.
    Route::middleware(['permission:customers.view', 'plan.permission:customer-statements.view'])->group(function () {
        Route::get('/customer-statements', [\App\Http\Controllers\CustomerStatementController::class, 'index'])->name('customer-statements.index');
        Route::get('/customer-statements/{customerId}', [\App\Http\Controllers\CustomerStatementController::class, 'show'])->name('customer-statements.show');
        Route::get('/customer-statements/{customerId}/preview', [\App\Http\Controllers\CustomerStatementController::class, 'previewPdf'])->name('customer-statements.preview');
        Route::get('/customer-statements/{customerId}/pdf', [\App\Http\Controllers\CustomerStatementController::class, 'downloadPdf'])->name('customer-statements.pdf');
        Route::post('/customer-statements/{customerId}/email', [\App\Http\Controllers\CustomerStatementController::class, 'email'])
            ->middleware(['throttle:sensitive', \App\Http\Middleware\EnsureEmailVerifiedForOutbound::class])
            ->name('customer-statements.email');
    });

    // --- Recurring Invoices (scheduled templates) ---
    // Solo+ on the SaaS pricing page.
    Route::middleware(['permission:recurring-invoices.view', 'plan.permission:recurring-invoices.view'])->group(function () {
        Route::get('/recurring-invoices', [\App\Http\Controllers\RecurringInvoiceController::class, 'index'])->name('recurring-invoices.index');
    });
    Route::middleware(['permission:recurring-invoices.create', 'plan.permission:recurring-invoices.view'])->group(function () {
        Route::get('/recurring-invoices/create', [\App\Http\Controllers\RecurringInvoiceController::class, 'create'])->name('recurring-invoices.create');
        Route::post('/recurring-invoices', [\App\Http\Controllers\RecurringInvoiceController::class, 'store'])
            ->middleware('throttle:creation')
            ->name('recurring-invoices.store');
    });
    Route::middleware(['permission:recurring-invoices.edit', 'plan.permission:recurring-invoices.view'])->group(function () {
        Route::get('/recurring-invoices/{id}/edit', [\App\Http\Controllers\RecurringInvoiceController::class, 'edit'])->name('recurring-invoices.edit');
        Route::put('/recurring-invoices/{id}', [\App\Http\Controllers\RecurringInvoiceController::class, 'update'])->name('recurring-invoices.update');
        Route::post('/recurring-invoices/{id}/toggle', [\App\Http\Controllers\RecurringInvoiceController::class, 'toggle'])->name('recurring-invoices.toggle');
    });
    Route::middleware(['permission:recurring-invoices.run', 'plan.permission:recurring-invoices.view'])->group(function () {
        Route::post('/recurring-invoices/{id}/run', [\App\Http\Controllers\RecurringInvoiceController::class, 'runNow'])->name('recurring-invoices.run');
    });
    Route::middleware(['permission:recurring-invoices.delete', 'plan.permission:recurring-invoices.view'])->group(function () {
        Route::delete('/recurring-invoices/{id}', [\App\Http\Controllers\RecurringInvoiceController::class, 'destroy'])->name('recurring-invoices.destroy');
    });

    // --- Estimates (Quotations) ---
    // Solo+ on the SaaS pricing page ("Email invoices & estimates").
    Route::middleware(['permission:estimates.view', 'plan.permission:estimates.view'])->group(function () {
        Route::get('/estimates', [\App\Http\Controllers\EstimateController::class, 'index'])->name('estimates.index');
    });
    Route::middleware(['permission:estimates.create', 'plan.permission:estimates.view'])->group(function () {
        Route::get('/estimates/batch', [\App\Http\Controllers\EstimateController::class, 'batchCreate'])->name('estimates.batch');
        Route::post('/estimates/batch', [\App\Http\Controllers\EstimateController::class, 'batchStore'])
            ->middleware('throttle:creation')
            ->name('estimates.batch.store');
        Route::get('/estimates/create', [\App\Http\Controllers\EstimateController::class, 'create'])->name('estimates.create');
        Route::post('/estimates', [\App\Http\Controllers\EstimateController::class, 'store'])
            ->middleware('throttle:creation')
            ->name('estimates.store');
    });
    Route::middleware(['permission:estimates.view', 'plan.permission:estimates.view'])->group(function () {
        Route::get('/estimates/bulk-pdf', [\App\Http\Controllers\EstimateController::class, 'bulkPdf'])->name('estimates.bulk-pdf');
        Route::get('/estimates/{id}', [\App\Http\Controllers\EstimateController::class, 'show'])->name('estimates.show');
        Route::get('/estimates/{id}/pdf', [\App\Http\Controllers\EstimateController::class, 'downloadPdf'])->name('estimates.pdf');
    });
    Route::middleware(['permission:estimates.edit', 'plan.permission:estimates.view'])->group(function () {
        Route::get('/estimates/{id}/edit', [\App\Http\Controllers\EstimateController::class, 'edit'])->name('estimates.edit');
        Route::put('/estimates/{id}', [\App\Http\Controllers\EstimateController::class, 'update'])->name('estimates.update');
        Route::post('/estimates/{id}/transition', [\App\Http\Controllers\EstimateController::class, 'transition'])->name('estimates.transition');
    });
    // Email is gated on its own permission AND its own plan flag so
    // we can sell it as the "Email estimates" bullet on Solo+ without
    // also selling estimate viewing/editing as paid features.
    Route::middleware(['permission:estimates.email', 'plan.permission:estimates.email', \App\Http\Middleware\EnsureEmailVerifiedForOutbound::class])->group(function () {
        Route::post('/estimates/bulk-email', [\App\Http\Controllers\EstimateController::class, 'bulkEmail'])->name('estimates.bulk-email');
        Route::post('/estimates/{id}/email', [\App\Http\Controllers\EstimateController::class, 'email'])->name('estimates.email');
    });
    Route::middleware(['permission:estimates.convert', 'plan.permission:estimates.view'])->group(function () {
        Route::post('/estimates/{id}/convert', [\App\Http\Controllers\EstimateController::class, 'convert'])->name('estimates.convert');
    });
    Route::middleware(['permission:estimates.convert', 'plan.permission:estimates.view', 'goods.flow'])->group(function () {
        Route::post('/estimates/{id}/convert-so', [\App\Http\Controllers\EstimateController::class, 'convertToSalesOrder'])->name('estimates.convert-so');
    });
    Route::middleware(['permission:estimates.create', 'plan.permission:estimates.view'])->group(function () {
        Route::post('/estimates/{id}/duplicate', [\App\Http\Controllers\EstimateController::class, 'duplicate'])->name('estimates.duplicate');
    });
    Route::middleware(['permission:estimates.delete', 'plan.permission:estimates.view'])->group(function () {
        Route::delete('/estimates/{id}', [\App\Http\Controllers\EstimateController::class, 'destroy'])->name('estimates.destroy');
    });

    // --- Products & Services (line-item catalogue) ---
    // Growth+ on the SaaS pricing page.
    Route::middleware(['permission:products.view', 'plan.permission:products.view'])->group(function () {
        Route::get('/products', [\App\Http\Controllers\ProductController::class, 'index'])->name('products.index');
    });
    Route::middleware(['permission:products.create', 'plan.permission:products.view'])->group(function () {
        Route::get('/products/create', [\App\Http\Controllers\ProductController::class, 'create'])->name('products.create');
        Route::post('/products', [\App\Http\Controllers\ProductController::class, 'store'])
            ->middleware('throttle:creation')
            ->name('products.store');
    });
    Route::middleware(['permission:products.edit', 'plan.permission:products.view'])->group(function () {
        Route::get('/products/{id}/edit', [\App\Http\Controllers\ProductController::class, 'edit'])->name('products.edit');
        Route::put('/products/{id}', [\App\Http\Controllers\ProductController::class, 'update'])->name('products.update');
    });
    Route::middleware(['permission:products.delete', 'plan.permission:products.view'])->group(function () {
        Route::delete('/products/{id}', [\App\Http\Controllers\ProductController::class, 'destroy'])->name('products.destroy');
    });
});

require __DIR__.'/auth.php';