<?php

use Illuminate\Support\Facades\Route;
use Profina\Sso\Http\Controllers\ConsumeController;
use Profina\Sso\Http\Controllers\HealthController;
use Profina\Sso\Http\Controllers\LogoutController;

Route::middleware('web')->group(function () {
    // Satu-satunya pintu masuk modul: token sekali pakai dari Portal.
    Route::get('sso/consume', ConsumeController::class)->middleware('throttle:30,1')->name('profina.consume');
    Route::match(['get', 'post'], 'sso/logout', [LogoutController::class, 'destroy'])->name('profina.logout');
});

// Health check server-ke-server (bertanda tangan) untuk dashboard pemantauan Portal.
Route::get('profina/health', HealthController::class)->middleware('throttle:60,1')->name('profina.health');
