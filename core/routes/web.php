<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PortalController::class, 'home'])->name('home');

require __DIR__.'/auth.php';

// Keluar dari seluruh PROFINA (dipanggil modul). Tidak butuh login: kalau belum login, hanya ke halaman login.
Route::get('/sso/logout', [PortalController::class, 'ssoLogout'])->name('sso.logout');

Route::middleware('auth')->group(function () {
    // Pintu masuk tunggal
    Route::get('/portal', [PortalController::class, 'index'])->name('portal');
    Route::get('/launch/{module}', [PortalController::class, 'launch'])->name('portal.launch');

    // Profil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto'])->name('profile.photo.upload');
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto'])->name('profile.photo.delete');
    Route::get('/profile/{user}/photo', [ProfileController::class, 'photo'])->name('profile.photo');

    // Modul Inti: pantau
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/sessions/{session}/revoke', [DashboardController::class, 'revokeSession'])->middleware('permission:sessions.revoke')->name('sessions.revoke');

    // Modul Inti: kelola pengguna & penugasan
    Route::middleware('permission:users.view')->group(function () {
        Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    });
    Route::middleware('permission:users.manage')->group(function () {
        Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
        Route::post('/admin/users/{user}/approve', [UserController::class, 'approve'])->name('admin.users.approve');
        Route::post('/admin/users/{user}/reject', [UserController::class, 'reject'])->name('admin.users.reject');
        Route::post('/admin/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('admin.users.toggle-active');
        Route::post('/admin/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('admin.users.reset-password');
        Route::put('/admin/users/{user}/access', [UserController::class, 'updateAccess'])->name('admin.users.access');
    });

    // Audit
    Route::get('/admin/audit', [AuditController::class, 'index'])->middleware('permission:audit.view')->name('admin.audit');
});
