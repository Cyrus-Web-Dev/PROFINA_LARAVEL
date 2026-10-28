<?php

use Illuminate\Support\Facades\Route;
use Profina\Sso\Http\Controllers\LogoutController;
use Profina\Sso\Http\Controllers\RedirectToCoreController;

// Modul ini tidak punya login sendiri: semua jalan menuju Portal PROFINA.
Route::get('login', RedirectToCoreController::class)->name('login');
Route::middleware('auth')->post('logout', [LogoutController::class, 'destroy'])->name('logout');
