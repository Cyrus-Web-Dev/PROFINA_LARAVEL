<?php

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\SdmWebhookController;
use Illuminate\Support\Facades\Route;

/*
| REST API PAYBRIDGE v1 - dipakai Keuangan (MKP) dan SDM. Autentikasi: Authorization: Bearer <token klien API>
| (dibuat di menu Integrasi). Respons: {"data": ...} atau {"error": {"code","message","details"}}.
| Dokumentasi lengkap: docs/INTEGRASI.md
*/
Route::prefix('v1')->middleware(['throttle:pay-api'])->group(function () {
    Route::middleware('api.client')->get('/ping', [ApiController::class, 'ping']);

    Route::middleware('api.client:funding:read')->group(function () {
        Route::get('/funding', [ApiController::class, 'fundingIndex']);
        Route::get('/funding/{number}', [ApiController::class, 'fundingShow']);
    });
    Route::middleware('api.client:funding:decide')->group(function () {
        Route::post('/funding/{number}/decision', [ApiController::class, 'fundingDecision']);
        Route::post('/funding/{number}/disbursement', [ApiController::class, 'fundingDisbursement']);
        Route::post('/funding/{number}/notes', [ApiController::class, 'fundingNote']);
    });
    Route::middleware('api.client:remittance:read')->get('/remittances', [ApiController::class, 'remittanceIndex']);
    Route::middleware('api.client:remittance:update')->post('/remittances/{id}/paid', [ApiController::class, 'remittancePaid']);
    Route::middleware('api.client:budget:read')->get('/budgets/active', [ApiController::class, 'budgetActive']);
    Route::middleware('api.client:contracts:read')->get('/contracts', [ApiController::class, 'contracts']);
    Route::middleware('api.client:payslips:read')->get('/payslips', [ApiController::class, 'payslips']);
    Route::middleware('api.client:audit:read')->get('/audit/head', [ApiController::class, 'auditHead']);

    Route::fallback(fn () => response()->json(['error' => ['code' => 'not_found', 'message' => 'Endpoint tidak ditemukan.']], 404));
});

// Webhook dari SDM: tanpa sesi/CSRF; keamanan lewat tanda tangan HMAC (lihat controller).
Route::post('/sdm/webhook', SdmWebhookController::class)->middleware('throttle:240,1')->name('sdm.webhook');
