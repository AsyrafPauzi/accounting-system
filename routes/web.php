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

Route::get('/public/invoices/{uuid}/download', [InvoiceController::class, 'publicDownloadPdf'])
    ->name('public.invoices.download')
    ->middleware('signed');

// --- Toyyibpay Webhook (Server-to-Server) ---
Route::post('/subscription/webhook', [SubscriptionController::class, 'webhook'])->name('subscription.webhook');
Route::post('/subscription/webhook/extra-user', [SubscriptionController::class, 'webhookExtraUser'])->name('subscription.webhook.extra_user');

// --- Dashboard, Profile & App (Auth Required) ---
Route::middleware(['auth', 'verified'])->group(function () {
    // Subscription pages (always allowed, EnsureSubscribed skips these)
    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
    Route::get('/subscription/success', [SubscriptionController::class, 'success'])->name('subscription.success');
    Route::get('/subscription/callback', [SubscriptionController::class, 'callback'])->name('subscription.callback');

    // Dashboard (paid-only via EnsureSubscribed)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/theme', [ProfileController::class, 'theme'])->name('profile.theme');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Company settings (tenant-level)
    Route::get('/settings/company', [CompanySettingsController::class, 'edit'])->name('settings.company');
    Route::patch('/settings/company', [CompanySettingsController::class, 'update'])->name('settings.company.update');
    Route::get('/settings/plan', [SubscriptionController::class, 'planSettings'])->name('settings.plan.index');

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

    // --- Admin: Tenant management ---
    Route::middleware('permission:admin.tenants')->group(function () {
        Route::get('/admin/tenants', [TenantAdminController::class, 'index'])->name('admin.tenants.index');

        // Destructive tenant actions — low limit (backup/delete/impersonate)
        Route::middleware('throttle:sensitive')->group(function () {
            Route::get('/admin/tenants/{tenant}/backup', [TenantAdminController::class, 'backup'])->name('admin.tenants.backup');
            Route::delete('/admin/tenants/{tenant}', [TenantAdminController::class, 'destroy'])->name('admin.tenants.destroy');
            Route::post('/admin/tenants/impersonate/{user}', [TenantAdminController::class, 'impersonate'])->name('admin.tenants.impersonate');
        });

        // Subscription management — higher limit for normal admin workflow
        Route::middleware('throttle:creation')->group(function () {
            Route::put('/admin/tenants/{tenant}/subscription', [TenantAdminController::class, 'assignSubscription'])->name('admin.tenants.subscription.assign');
            Route::post('/admin/tenants/{tenant}/subscription/extend', [TenantAdminController::class, 'extendSubscription'])->name('admin.tenants.subscription.extend');
            Route::post('/admin/tenants/{tenant}/subscription/cancel', [TenantAdminController::class, 'cancelSubscription'])->name('admin.tenants.subscription.cancel');
            Route::post('/admin/tenants/{tenant}/subscription/lifetime', [TenantAdminController::class, 'grantLifetimeSubscription'])->name('admin.tenants.subscription.lifetime');
        });
    });

    // Stop impersonating can be called by an impersonated user who lacks "admin.tenants"
    Route::post('/admin/tenants/stop-impersonating', [TenantAdminController::class, 'stopImpersonating'])
        ->middleware('throttle:sensitive')
        ->name('admin.tenants.stop-impersonating');

    // --- Admin: Plan catalog ---
    Route::middleware('permission:admin.plans')->group(function () {
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
    Route::middleware('permission:invoices.view')->group(function () {
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{id}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
        Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
        Route::get('/invoices/{id}/preview', [InvoiceController::class, 'previewPdf'])->name('invoices.preview');
    });
    Route::middleware('permission:invoices.create')->group(function () {
        Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/invoices', [InvoiceController::class, 'store'])
            ->middleware('throttle:creation')
            ->name('invoices.store');
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
    Route::middleware(['permission:invoices.email', 'plan.permission:invoices.email'])->group(function () {
        Route::post('/invoices/{id}/email', [InvoiceController::class, 'emailPdf'])->name('invoices.email');
    });
    Route::middleware(['permission:invoices.record-payment', 'plan.permission:invoices.record-payment'])->group(function () {
        Route::post('/invoices/{id}/payments', [InvoiceController::class, 'recordPayment'])->name('invoices.record-payment');
    });

    // --- Credit Notes ---
    Route::middleware('permission:credit-notes.view')->group(function () {
        Route::get('/credit-notes', [CreditNoteController::class, 'index'])->name('credit-notes.index');
    });
    Route::middleware('permission:credit-notes.create')->group(function () {
        Route::get('/credit-notes/create/{invoice_id}', [CreditNoteController::class, 'create'])->name('credit-notes.create');
        Route::post('/credit-notes', [CreditNoteController::class, 'store'])->name('credit-notes.store');
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
    });
    Route::middleware(['permission:bills.create', 'plan.permission:bills.view'])->group(function () {
        Route::get('/bills/create', [BillController::class, 'create'])->name('bills.create');
        Route::post('/bills', [BillController::class, 'store'])
            ->middleware('throttle:creation')
            ->name('bills.store');
        Route::post('/bills/upload-receipt', [BillController::class, 'uploadReceipt'])->name('bills.upload-receipt');
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
    // Re-uses the journal.create permission (any staff member who can post a
    // manual journal can also record a payroll run).
    Route::middleware(['permission:journal.create', 'plan.permission:journal.create'])->group(function () {
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
});

require __DIR__.'/auth.php';