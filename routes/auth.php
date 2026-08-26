<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\PracticeRegisteredUserController;
use App\Http\Controllers\Auth\ProvisioningController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

// All guest auth POSTs run through the dual-key 'auth' limiter
// (per-IP + per-email) plus the SpamBotGuard (honeypot + time challenge)
// to absorb credential stuffing, password spraying, and form-spam bots
// before they reach the controller.
Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware(['throttle:auth', \App\Http\Middleware\SpamBotGuard::class]);

    // Firm signup (Accountant track). Lives alongside the SME register
    // route so accountants get a distinct landing page that picks a
    // Practice plan + creates a Firm row.
    Route::get('register/practice', [PracticeRegisteredUserController::class, 'create'])
        ->name('register.practice.show');
    Route::post('register/practice', [PracticeRegisteredUserController::class, 'store'])
        ->middleware(['throttle:auth', \App\Http\Middleware\SpamBotGuard::class])
        ->name('register.practice');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->middleware(['throttle:auth', \App\Http\Middleware\SpamBotGuard::class]);

    // 2FA challenge — only reachable after a valid email+password where
    // the user has 2FA enabled. The pending user id lives in the
    // session, so this stays inside the `guest` middleware group.
    Route::get('two-factor-challenge', [\App\Http\Controllers\Auth\TwoFactorChallengeController::class, 'show'])
        ->name('auth.2fa.challenge.show');
    Route::post('two-factor-challenge', [\App\Http\Controllers\Auth\TwoFactorChallengeController::class, 'store'])
        ->middleware('throttle:auth')
        ->name('auth.2fa.challenge.store');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware(['throttle:auth', \App\Http\Middleware\SpamBotGuard::class])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware(['throttle:auth', \App\Http\Middleware\SpamBotGuard::class])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('provisioning', [ProvisioningController::class, 'show'])
        ->name('provisioning');
    Route::get('provisioning/status', [ProvisioningController::class, 'status'])
        ->name('provisioning.status');
    Route::post('provisioning/retry', [ProvisioningController::class, 'retry'])
        ->name('provisioning.retry');

    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store'])
        ->name('password.confirm.store');

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    // 2FA management lives under settings, behind auth.
    Route::get('settings/two-factor', [\App\Http\Controllers\Settings\TwoFactorController::class, 'index'])
        ->name('settings.2fa.show');
    Route::post('settings/two-factor/enable', [\App\Http\Controllers\Settings\TwoFactorController::class, 'enable'])
        ->middleware('throttle:auth')
        ->name('settings.2fa.enable');
    Route::post('settings/two-factor/confirm', [\App\Http\Controllers\Settings\TwoFactorController::class, 'confirm'])
        ->middleware('throttle:auth')
        ->name('settings.2fa.confirm');
    Route::post('settings/two-factor/disable', [\App\Http\Controllers\Settings\TwoFactorController::class, 'disable'])
        ->middleware('throttle:auth')
        ->name('settings.2fa.disable');
    Route::post('settings/two-factor/recovery-codes', [\App\Http\Controllers\Settings\TwoFactorController::class, 'regenerateRecoveryCodes'])
        ->middleware('throttle:auth')
        ->name('settings.2fa.recovery_codes');
    Route::post('settings/two-factor/cancel-pending', [\App\Http\Controllers\Settings\TwoFactorController::class, 'cancelPending'])
        ->name('settings.2fa.cancel_pending');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
