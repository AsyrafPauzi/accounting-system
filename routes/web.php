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
use App\Http\Controllers\TenantAdminController;
use App\Http\Controllers\ChartOfAccountsController;
use App\Http\Controllers\GeneralLedgerController;
use App\Http\Controllers\ProfitAndLossController;
use App\Http\Controllers\BalanceSheetController;
use App\Http\Controllers\CashflowSummaryController;
use App\Http\Controllers\AgedReceivablesController;
use App\Http\Controllers\ReportsHubController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// --- Public Routes ---
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// --- Dashboard, Profile & App (Auth Required) ---
Route::middleware(['auth', 'verified'])->group(function () {
    // Subscription pages (always allowed, EnsureSubscribed skips these)
    Route::get('/subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout'])->name('subscription.checkout');
    Route::get('/subscription/success', [SubscriptionController::class, 'success'])->name('subscription.success');

    // Dashboard (paid-only via EnsureSubscribed)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Company settings (tenant-level)
    Route::get('/settings/company', [CompanySettingsController::class, 'edit'])->name('settings.company');
    Route::patch('/settings/company', [CompanySettingsController::class, 'update'])->name('settings.company.update');

    // --- Sales & Invoicing Module (A-Z Rich Features) ---
    // Simple tenant admin tools (admin role only)
    Route::get('/admin/tenants', [TenantAdminController::class, 'index'])->name('admin.tenants.index');
    Route::get('/admin/tenants/{tenant}/backup', [TenantAdminController::class, 'backup'])->name('admin.tenants.backup');
    Route::delete('/admin/tenants/{tenant}', [TenantAdminController::class, 'destroy'])->name('admin.tenants.destroy');
    Route::post('/admin/tenants/impersonate/{user}', [TenantAdminController::class, 'impersonate'])->name('admin.tenants.impersonate');
    Route::post('/admin/tenants/stop-impersonating', [TenantAdminController::class, 'stopImpersonating'])->name('admin.tenants.stop-impersonating');

    // Standard CRUD
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{id}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
    Route::put('/invoices/{id}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::delete('/invoices/{id}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');

    // Rich Lifecycle Actions
    Route::post('/invoices/{id}/post', [InvoiceController::class, 'postInvoice'])->name('invoices.post');
    Route::post('/invoices/{id}/void', [InvoiceController::class, 'voidInvoice'])->name('invoices.void');
    Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'downloadPdf'])->name('invoices.pdf');
    Route::post('/invoices/{id}/email', [InvoiceController::class, 'emailPdf'])->name('invoices.email');
    
    // Payment Recording (Name aligned with React Index.jsx)
    Route::post('/invoices/{id}/payments', [InvoiceController::class, 'recordPayment'])->name('invoices.record-payment');

    // Credit Notes Module
    Route::get('/credit-notes/create/{invoice_id}', [CreditNoteController::class, 'create'])->name('credit-notes.create');
    Route::get('/credit-notes', [CreditNoteController::class, 'index'])->name('credit-notes.index');
    Route::post('/credit-notes', [CreditNoteController::class, 'store'])->name('credit-notes.store');

    // Suppliers (Purchases)
    Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
    Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
    Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
    Route::get('/suppliers/{id}', [SupplierController::class, 'show'])->name('suppliers.show');
    Route::get('/suppliers/{id}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
    Route::put('/suppliers/{id}', [SupplierController::class, 'update'])->name('suppliers.update');
    Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

    // Bills / Purchases
    Route::get('/bills', [BillController::class, 'index'])->name('bills.index');
    Route::get('/bills/create', [BillController::class, 'create'])->name('bills.create');
    Route::post('/bills', [BillController::class, 'store'])->name('bills.store');
    Route::get('/bills/{id}/edit', [BillController::class, 'edit'])->name('bills.edit');
    Route::put('/bills/{id}', [BillController::class, 'update'])->name('bills.update');
    Route::delete('/bills/{id}', [BillController::class, 'destroy'])->name('bills.destroy');
    Route::post('/bills/{id}/post', [BillController::class, 'postBill'])->name('bills.post');
    Route::post('/bills/{id}/void', [BillController::class, 'voidBill'])->name('bills.void');
    Route::post('/bills/{id}/payments', [BillController::class, 'recordPayment'])->name('bills.record-payment');

    // Accounts Payable
    Route::get('/accounts-payable', [AccountsPayableController::class, 'index'])->name('accounts-payable.index');

    // Chart of Accounts
    Route::get('/chart-of-accounts', [ChartOfAccountsController::class, 'index'])->name('chart-of-accounts.index');
    Route::get('/chart-of-accounts/export/csv', [ChartOfAccountsController::class, 'exportCsv'])->name('chart-of-accounts.export.csv');
    Route::get('/chart-of-accounts/export/pdf', [ChartOfAccountsController::class, 'exportPdf'])->name('chart-of-accounts.export.pdf');
    Route::get('/chart-of-accounts/create', [ChartOfAccountsController::class, 'create'])->name('chart-of-accounts.create');
    Route::post('/chart-of-accounts', [ChartOfAccountsController::class, 'store'])->name('chart-of-accounts.store');
    Route::get('/chart-of-accounts/{id}/edit', [ChartOfAccountsController::class, 'edit'])->name('chart-of-accounts.edit');
    Route::put('/chart-of-accounts/{id}', [ChartOfAccountsController::class, 'update'])->name('chart-of-accounts.update');
    Route::delete('/chart-of-accounts/{id}', [ChartOfAccountsController::class, 'destroy'])->name('chart-of-accounts.destroy');
    Route::post('/chart-of-accounts/seed-default', [ChartOfAccountsController::class, 'seedDefault'])->name('chart-of-accounts.seed-default');

    // General Ledger
    Route::get('/general-ledger', [GeneralLedgerController::class, 'index'])->name('general-ledger.index');
    Route::get('/general-ledger/export/csv', [GeneralLedgerController::class, 'exportCsv'])->name('general-ledger.export.csv');
    Route::get('/general-ledger/export/pdf', [GeneralLedgerController::class, 'exportPdf'])->name('general-ledger.export.pdf');
    Route::get('/general-ledger/report', [GeneralLedgerController::class, 'report'])->name('general-ledger.report');
    Route::get('/general-ledger/report/export/csv', [GeneralLedgerController::class, 'exportReportCsv'])->name('general-ledger.report.export.csv');
    Route::get('/general-ledger/report/export/pdf', [GeneralLedgerController::class, 'exportReportPdf'])->name('general-ledger.report.export.pdf');
    Route::get('/general-ledger/{id}', [GeneralLedgerController::class, 'show'])->name('general-ledger.show');

    // Profit & Loss
    Route::get('/profit-and-loss', [ProfitAndLossController::class, 'index'])->name('profit-and-loss.index');
    Route::get('/profit-and-loss/export/csv', [ProfitAndLossController::class, 'exportCsv'])->name('profit-and-loss.export.csv');
    Route::get('/profit-and-loss/export/pdf', [ProfitAndLossController::class, 'exportPdf'])->name('profit-and-loss.export.pdf');
    Route::get('/balance-sheet', [BalanceSheetController::class, 'index'])->name('balance-sheet.index');
    Route::get('/balance-sheet/export/csv', [BalanceSheetController::class, 'exportCsv'])->name('balance-sheet.export.csv');
    Route::get('/balance-sheet/export/pdf', [BalanceSheetController::class, 'exportPdf'])->name('balance-sheet.export.pdf');

    // Cashflow Summary & Aged Receivables
    Route::get('/cashflow-summary', [CashflowSummaryController::class, 'index'])->name('cashflow-summary.index');
    Route::get('/aged-receivables', [AgedReceivablesController::class, 'index'])->name('aged-receivables.index');

    // Reports hub (single entry for all reports)
    Route::get('/reports', [ReportsHubController::class, 'index'])->name('reports.index');

     // 1. List all customers (The Directory)
     Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    
     // 2. Show the "Onboard New Customer" form
     Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
     
     // 3. Save the new customer to database
     Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');

     // 3b. Quick-create customer from invoice create (returns JSON)
     Route::post('/customers/quick-store', [CustomerController::class, 'quickStore'])->name('customers.quick-store');
     
     // 4. Show the "Customer 360" profile (History, Invoices, etc.)
     Route::get('/customers/{id}', [CustomerController::class, 'show'])->name('customers.show');
     
     // 5. Show the "Edit Profile" form
     Route::get('/customers/{id}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
     
     // 6. Update the customer profile data
     Route::put('/customers/{id}', [CustomerController::class, 'update'])->name('customers.update');
     
     // 7. Delete customer (Standard CRUD - though Enterprise usually uses 'is_active' toggle)
     Route::delete('/customers/{id}', [CustomerController::class, 'destroy'])->name('customers.destroy');
});

require __DIR__.'/auth.php';