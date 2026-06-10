<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| External Partner API
|--------------------------------------------------------------------------
|
| Stateless JSON API for OAuth-style partner integrations. Two surfaces:
|
|   /api/oauth/*   — handshake endpoints (no auth; client_secret is
|                    the auth signal where applicable)
|   /api/v1/*      — tenant data endpoints (Bearer api_key, plus HMAC
|                    signature on mutating methods)
|
| The standard Laravel session, CSRF, and HandleInertiaRequests stacks
| do NOT run here — that's by design (these are JSON-only, server-to-
| server). Tenancy is initialised inside ApiKeyAuth on a per-request
| basis from the api_key, not from the session.
|
*/

// ── OAuth handshake (no auth-key required; partner client_secret is
// the auth signal for the token exchange).
Route::prefix('oauth')->name('api.oauth.')->group(function () {
    Route::post('/token', \App\Http\Controllers\OAuth\TokenController::class)
        ->middleware(['throttle:auth'])
        ->name('token');
});

// ── /api/v1 — tenant-scoped data surface. Throttled per-key (or per-IP
// when unauth'd), authed by Bearer api_key, mutating methods additionally
// verified by HMAC signature.
Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['throttle:api-v1', 'api.key'])
    ->group(function () {
        // ── Read-only feeds. The Bearer key alone is enough; no
        // signature required. Pagination follows Laravel's default
        // length-aware paginator shape.
        Route::get('/transactions', [\App\Http\Controllers\Api\V1\TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/invoices',     [\App\Http\Controllers\Api\V1\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/bills',        [\App\Http\Controllers\Api\V1\BillController::class, 'index'])->name('bills.index');
        Route::get('/customers',    [\App\Http\Controllers\Api\V1\CustomerController::class, 'index'])->name('customers.index');
        Route::get('/suppliers',    [\App\Http\Controllers\Api\V1\SupplierController::class, 'index'])->name('suppliers.index');

        // ── Write surface. HMAC signature verification is layered on
        // top of api.key. Failure modes:
        //   - missing X-BukuCloud-Timestamp / X-BukuCloud-Signature → 401
        //   - signature mismatch                                    → 401
        //   - timestamp outside 5-min skew                          → 401
        Route::middleware(['api.signed'])->group(function () {
            Route::post('/transactions/deposit',    [\App\Http\Controllers\Api\V1\TransactionController::class, 'storeDeposit'])->name('transactions.deposit');
            Route::post('/transactions/withdrawal', [\App\Http\Controllers\Api\V1\TransactionController::class, 'storeWithdrawal'])->name('transactions.withdrawal');
        });
    });
