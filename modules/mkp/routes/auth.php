<?php

use Illuminate\Support\Facades\Route;
use Profina\Sso\Http\Controllers\LogoutController;
use Profina\Sso\Http\Controllers\RedirectToCoreController;

/*
|--------------------------------------------------------------------------
| PROFINA: modul ini TIDAK punya login sendiri.
|--------------------------------------------------------------------------
| Satu-satunya pintu masuk adalah Portal (Core). Semua halaman login/daftar/lupa-password lama
| dialihkan ke Portal. Nama rute lama dipertahankan supaya route('register') dkk di kode lama tidak error.
| Satu-satunya cara membuat sesi di modul ini: GET /sso/consume (paket profina/sso-client).
*/
Route::get('login', RedirectToCoreController::class)->name('login');
Route::get('password/reset/{token}', RedirectToCoreController::class)->name('password.reset');
Route::get('__legacy/register', RedirectToCoreController::class)->name('register');
Route::get('__legacy/register.verify', RedirectToCoreController::class)->name('register.verify');
Route::get('__legacy/register.verify.resend', RedirectToCoreController::class)->name('register.verify.resend');
Route::get('__legacy/two-factor.challenge', RedirectToCoreController::class)->name('two-factor.challenge');
Route::get('__legacy/two-factor.verify', RedirectToCoreController::class)->name('two-factor.verify');
Route::get('__legacy/two-factor.resend', RedirectToCoreController::class)->name('two-factor.resend');
Route::get('__legacy/password.request', RedirectToCoreController::class)->name('password.request');
Route::get('__legacy/password.email', RedirectToCoreController::class)->name('password.email');
Route::get('__legacy/password.update', RedirectToCoreController::class)->name('password.update');

Route::middleware('auth')->post('logout', [LogoutController::class, 'destroy'])->name('logout');
