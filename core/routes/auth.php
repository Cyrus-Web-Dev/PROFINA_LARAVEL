<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\RegisterVerifyController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    // Pendaftaran mandiri: form -> kode verifikasi email -> akun "pending" menunggu persetujuan Admin PROFINA
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:10,1');
    Route::get('register/verify', [RegisterVerifyController::class, 'create'])->name('register.verify');
    Route::post('register/verify', [RegisterVerifyController::class, 'store'])->middleware('throttle:10,1')->name('register.verify.store');
    Route::post('register/verify/resend', [RegisterVerifyController::class, 'resend'])->middleware('throttle:5,1')->name('register.resend');

    // Login (tahap 1: email+password -> kirim kode 2FA)
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Login (tahap 2: verifikasi kode 2FA -> sesi login benar-benar dibuat)
    Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->name('two-factor.verify');
    Route::post('two-factor-challenge/resend', [TwoFactorChallengeController::class, 'resend'])->name('two-factor.resend');

    // Lupa Password
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:6,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
