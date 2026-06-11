<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\LnurlAuthController;
use App\Http\Controllers\MobileAuthController;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')
    ->group(function () {
        Route::livewire('/login', 'auth.login')
            ->name('login');

        Route::livewire('/forgot-password', 'auth.forgot-password')
            ->name('password.request');

        Route::livewire('/reset-password/{token}', 'auth.reset-password')
            ->name('password.reset');

        Route::get('/auth/complete-lightning/{k1}', [LnurlAuthController::class, 'completeLogin'])
            ->where('k1', '[a-f0-9]{64}')
            ->name('auth.ln.complete');
    });

Route::middleware('auth')
    ->group(function () {
        Route::livewire('/verify-email', 'auth.verify-email')
            ->name('verification.notice');

        Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::livewire('/confirm-password', 'auth.confirm-password')
            ->name('password.confirm');
    });

/*
 * Mobile app auth flow: works for guests (login via Lightning/Nostr) and
 * for already authenticated users (confirmation screen), so it lives
 * outside the guest group.
 */
Route::livewire('/auth/mobile', 'auth.mobile-login')
    ->middleware('throttle:30,1')
    ->name('auth.mobile');

Route::get('/auth/mobile/complete/{k1}', [MobileAuthController::class, 'complete'])
    ->where('k1', '[a-f0-9]{64}')
    ->middleware('throttle:30,1')
    ->name('auth.mobile.complete');

// NIP-55 signer callback (Amber): k1 in the path, the signer appends the
// URL-encoded signed event after the trailing slash. With verified App
// Links this URL opens the app directly; this web route is the fallback.
Route::get('/auth/mobile/signed/{payload}', [MobileAuthController::class, 'signedCallback'])
    ->where('payload', '.*')
    ->middleware('throttle:30,1')
    ->name('auth.mobile.signed');

// App handoff: verified Android App Link — opens the app with the token.
// In the browser (unverified install) it renders a button-based fallback.
Route::get('/app/auth', [MobileAuthController::class, 'handoff'])
    ->middleware('throttle:30,1')
    ->name('auth.mobile.handoff');

Route::post('/auth/mobile/confirm', [MobileAuthController::class, 'confirm'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('auth.mobile.confirm');

Route::post('logout', Logout::class)
    ->name('logout');
