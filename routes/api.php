<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| External Partner API
|--------------------------------------------------------------------------
|
| Stateless JSON API authenticated by Bearer api_key. Tenancy is
| initialised inside ApiKeyAuth on a per-request basis from the key.
|
*/

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['throttle:api-v1', 'api.key'])
    ->group(function () {
        Route::get('/transactions', [\App\Http\Controllers\Api\V1\TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/invoices',     [\App\Http\Controllers\Api\V1\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/bills',        [\App\Http\Controllers\Api\V1\BillController::class, 'index'])->name('bills.index');
        Route::get('/customers',    [\App\Http\Controllers\Api\V1\CustomerController::class, 'index'])->name('customers.index');
        Route::get('/suppliers',    [\App\Http\Controllers\Api\V1\SupplierController::class, 'index'])->name('suppliers.index');

        Route::middleware(['api.signed'])->group(function () {
            Route::post('/transactions/deposit',    [\App\Http\Controllers\Api\V1\TransactionController::class, 'storeDeposit'])->name('transactions.deposit');
            Route::post('/transactions/withdrawal', [\App\Http\Controllers\Api\V1\TransactionController::class, 'storeWithdrawal'])->name('transactions.withdrawal');
        });
    });
