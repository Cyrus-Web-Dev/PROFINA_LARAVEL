<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\PayrollApiController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PayrollWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API (v1) — token-based lewat Laravel Sanctum, TERPISAH dari sesi web.
| Ditujukan untuk aplikasi mobile atau integrasi pihak ketiga.
| Base URL: /api/v1/...   Semua endpoint (kecuali /login) butuh header:
|   Authorization: Bearer <token>
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->name('api.')->group(function () {

    // Login: 5 percobaan / menit per IP, supaya tidak bisa dipakai brute-force password.
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::get('/me', [AuthController::class, 'me'])->name('me');

        // Karyawan (izin employees.view/manage dicek di dalam controller, sama seperti web).
        Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');

        // Self-service: absensi
        Route::post('/attendances/check-in', [AttendanceController::class, 'checkIn'])->name('attendances.check-in');
        Route::post('/attendances/check-out', [AttendanceController::class, 'checkOut'])->name('attendances.check-out');
        Route::get('/attendances/history', [AttendanceController::class, 'history'])->name('attendances.history');

        // Self-service: cuti
        Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves.index');
        Route::get('/leaves/balances', [LeaveController::class, 'balances'])->name('leaves.balances');
        Route::post('/leaves', [LeaveController::class, 'store'])->name('leaves.store');
        Route::post('/leaves/{leave}/cancel', [LeaveController::class, 'cancel'])->name('leaves.cancel');

        // Self-service: slip gaji (link unduh PDF disertakan per item, lihat PayrollController)
        Route::get('/payroll/my-payslips', [PayrollController::class, 'myPayslips'])->name('payroll.mine');

        // Pengumuman perusahaan
        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    });
});

/*
| REST API untuk PAYBRIDGE (Payroll & Budgeting) - auth: token klien integrasi (bukan Sanctum user).
| Dokumentasi: docs/INTEGRASI-PAYBRIDGE.md
*/
Route::prefix('payroll/v1')->middleware(['throttle:payroll-api'])->group(function () {
    Route::middleware('integration.client')->get('/ping', [PayrollApiController::class, 'ping']);
    Route::middleware('integration.client:employees:read')->group(function () {
        Route::get('/employees', [PayrollApiController::class, 'employees']);
        Route::get('/employees/{nip}', [PayrollApiController::class, 'employee']);
        Route::get('/departments', [PayrollApiController::class, 'departments']);
    });
    Route::middleware('integration.client:attendance:read')->group(function () {
        Route::get('/attendance-summary', [PayrollApiController::class, 'attendanceSummary']);
        Route::get('/holidays', [PayrollApiController::class, 'holidays']);
    });
});

// Webhook dari PAYBRIDGE: tanpa sesi/CSRF; keamanan lewat tanda tangan HMAC.
Route::post('/payroll/webhook', PayrollWebhookController::class)->middleware('throttle:240,1')->name('payroll.webhook');
