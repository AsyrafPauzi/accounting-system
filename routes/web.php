<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\CreditNoteController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\TenantAdminController;
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

// --- Dashboard & Profile (Auth Required) ---
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// --- Sales & Invoicing Module (A-Z Rich Features) ---
Route::middleware(['auth', 'verified'])->group(function () {
    // Simple tenant admin tools (admin role only)
    Route::get('/admin/tenants', [TenantAdminController::class, 'index'])->name('admin.tenants.index');
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