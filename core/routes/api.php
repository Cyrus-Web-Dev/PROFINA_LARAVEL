<?php

use App\Http\Controllers\Api\IntrospectController;
use Illuminate\Support\Facades\Route;

// Server-ke-server (modul -> Portal). Dilindungi tanda tangan HMAC per-modul.
Route::post('/sso/introspect', IntrospectController::class)->middleware('throttle:120,1')->name('api.sso.introspect');
