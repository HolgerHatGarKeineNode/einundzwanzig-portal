<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\LnurlAuthController;
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

Route::post('logout', Logout::class)
    ->name('logout');
