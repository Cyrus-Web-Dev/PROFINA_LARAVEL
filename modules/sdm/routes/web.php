<?php

use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\CareersController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDocumentController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayrollIntegrationController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecruitmentController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Halaman publik (TANPA login) - lowongan kerja & lamar
|--------------------------------------------------------------------------
*/
Route::get('/', fn () => Inertia::render('Welcome'))->name('welcome');

Route::prefix('careers')->name('careers.')->group(function () {
    Route::get('/', [CareersController::class, 'index'])->name('index');
    Route::get('/{slug}', [CareersController::class, 'show'])->name('show');
    Route::post('/{vacancy:slug}/apply', [RecruitmentController::class, 'apply'])
        ->middleware('throttle:5,60') // maks 5 lamaran per jam per IP - cegah spam
        ->name('apply');
});

require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Halaman internal (WAJIB login + akun aktif). Otorisasi per-permission
| sudah ditangani di masing-masing constructor controller (Spatie Permission).
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'has.role'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // --- Profil sendiri ---
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto'])->name('profile.photo.upload');
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto'])->name('profile.photo.delete');
    Route::get('/profile/{user}/photo', [ProfileController::class, 'photo'])->name('profile.photo');

    // --- Karyawan (master data) ---
    Route::get('/employees/export', [EmployeeController::class, 'export'])->name('employees.export');
    Route::get('/employees/{employee}/photo', [EmployeeController::class, 'photo'])->name('employees.photo');
    Route::resource('employees', EmployeeController::class);

    // --- Departemen & Jabatan ---
    Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
    Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
    Route::put('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
    Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');
    Route::post('/positions', [DepartmentController::class, 'storePosition'])->name('positions.store');

    // --- Kelola Pengguna & Role ---
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('/users/{user}/approve', [UserManagementController::class, 'approve'])->name('users.approve');
    Route::post('/users/{user}/reject', [UserManagementController::class, 'reject'])->name('users.reject');
    Route::put('/users/{user}/employee', [UserManagementController::class, 'linkEmployee'])->name('users.link-employee');
    Route::post('/users/{user}/toggle-active', [UserManagementController::class, 'toggleActive'])->name('users.toggle-active');
    Route::put('/users/{user}/roles', [UserManagementController::class, 'updateRoles'])->name('users.roles');

    // --- Absensi ---
    Route::get('/attendances', [AttendanceController::class, 'index'])->name('attendances.index');
    Route::put('/attendances/{attendance}', [AttendanceController::class, 'update'])->name('attendances.update');
    Route::post('/attendances/check-in', [AttendanceController::class, 'checkIn'])->name('attendances.check-in');
    Route::post('/attendances/check-out', [AttendanceController::class, 'checkOut'])->name('attendances.check-out');
    Route::get('/my/attendances', [AttendanceController::class, 'myHistory'])->name('attendances.my-history');

    // --- Cuti & Izin ---
    Route::get('/leaves', [LeaveController::class, 'index'])->name('leaves.index');
    Route::post('/leaves', [LeaveController::class, 'store'])->name('leaves.store');
    Route::post('/leaves/{leave}/approve', [LeaveController::class, 'approve'])->name('leaves.approve');
    Route::post('/leaves/{leave}/reject', [LeaveController::class, 'reject'])->name('leaves.reject');
    Route::post('/leaves/{leave}/cancel', [LeaveController::class, 'cancel'])->name('leaves.cancel');
    Route::get('/my/leaves', [LeaveController::class, 'myLeaves'])->name('leaves.mine');

    // --- Payroll ---
    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::post('/payroll', [PayrollController::class, 'store'])->name('payroll.store');
    Route::get('/payroll/{payrollPeriod}', [PayrollController::class, 'show'])->name('payroll.show');
    Route::post('/payroll/{payrollPeriod}/process', [PayrollController::class, 'process'])->name('payroll.process');
    Route::post('/payroll/{payrollPeriod}/mark-paid', [PayrollController::class, 'markPaid'])->name('payroll.mark-paid');
    Route::put('/payroll/items/{payrollItem}', [PayrollController::class, 'updateItem'])->name('payroll.items.update');
    Route::get('/my/payslips', [PayrollController::class, 'myPayslips'])->name('payroll.mine');

    // --- Penilaian Kinerja / KPI ---
    Route::get('/performance', [PerformanceController::class, 'index'])->name('performance.index');
    Route::post('/performance/cycles', [PerformanceController::class, 'storeCycle'])->name('performance.cycles.store');
    Route::post('/performance', [PerformanceController::class, 'store'])->name('performance.store');
    Route::post('/performance/{performanceReview}/acknowledge', [PerformanceController::class, 'acknowledge'])->name('performance.acknowledge');
    Route::get('/my/performance', [PerformanceController::class, 'myReviews'])->name('performance.mine');

    // --- Rekrutmen (internal / ATS) ---
    Route::get('/recruitment', [RecruitmentController::class, 'index'])->name('recruitment.index');
    Route::post('/recruitment', [RecruitmentController::class, 'store'])->name('recruitment.store');
    Route::get('/recruitment/{vacancy}', [RecruitmentController::class, 'show'])->name('recruitment.show');
    Route::get('/recruitment/applicants/{applicant}/resume', [RecruitmentController::class, 'downloadResume'])->name('recruitment.applicants.resume');
    Route::put('/recruitment/{vacancy}', [RecruitmentController::class, 'update'])->name('recruitment.update');
    Route::delete('/recruitment/{vacancy}', [RecruitmentController::class, 'destroy'])->name('recruitment.destroy');
    Route::put('/recruitment/applicants/{applicant}/stage', [RecruitmentController::class, 'updateApplicantStage'])->name('recruitment.applicants.stage');

    // --- Pelatihan ---
    Route::get('/trainings', [TrainingController::class, 'index'])->name('trainings.index');
    Route::post('/trainings', [TrainingController::class, 'store'])->name('trainings.store');
    Route::get('/trainings/{training}', [TrainingController::class, 'show'])->name('trainings.show');
    Route::put('/trainings/{training}', [TrainingController::class, 'update'])->name('trainings.update');
    Route::delete('/trainings/{training}', [TrainingController::class, 'destroy'])->name('trainings.destroy');
    Route::post('/trainings/{training}/participants', [TrainingController::class, 'addParticipant'])->name('trainings.participants.store');
    Route::put('/trainings/participants/{participant}', [TrainingController::class, 'updateParticipant'])->name('trainings.participants.update');
    Route::get('/my/trainings', [TrainingController::class, 'myTrainings'])->name('trainings.mine');

    // --- Hari Libur (nasional dari API + libur/hari kerja pengganti perusahaan) ---
    Route::get('/holidays', [\App\Http\Controllers\HolidayController::class, 'index'])->name('holidays.index');
    Route::post('/holidays', [\App\Http\Controllers\HolidayController::class, 'store'])->name('holidays.store');
    Route::post('/holidays/refresh', [\App\Http\Controllers\HolidayController::class, 'refresh'])->name('holidays.refresh');
    Route::delete('/holidays/{holiday}', [\App\Http\Controllers\HolidayController::class, 'destroy'])->name('holidays.destroy');

    // --- Pengumuman ---
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

    // --- Dokumen Karyawan ---
    Route::get('/documents', [EmployeeDocumentController::class, 'index'])->name('documents.index');
    Route::post('/employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])->name('documents.store');
    Route::delete('/documents/{document}', [EmployeeDocumentController::class, 'destroy'])->name('documents.destroy');
    Route::get('/documents/{document}/download', [EmployeeDocumentController::class, 'download'])->name('documents.download');

    // --- Integrasi Payroll (PAYBRIDGE) ---
    Route::get('/integrations/payroll', [PayrollIntegrationController::class, 'index'])->name('integrations.payroll');
    Route::post('/integrations/payroll/clients', [PayrollIntegrationController::class, 'store'])->name('integrations.payroll.store');
    Route::put('/integrations/payroll/clients/{client}', [PayrollIntegrationController::class, 'update'])->name('integrations.payroll.update');
    Route::post('/integrations/payroll/clients/{client}/rotate', [PayrollIntegrationController::class, 'rotate'])->name('integrations.payroll.rotate');
    Route::post('/integrations/payroll/clients/{client}/ping', [PayrollIntegrationController::class, 'ping'])->name('integrations.payroll.ping');
    Route::delete('/integrations/payroll/clients/{client}', [PayrollIntegrationController::class, 'destroy'])->name('integrations.payroll.destroy');
    Route::post('/integrations/payroll/deliveries/{delivery}/resend', [PayrollIntegrationController::class, 'resend'])->name('integrations.payroll.resend');
    Route::get('/my/payslips-payroll', [PayrollIntegrationController::class, 'mine'])->name('payroll.mine-external');

    // --- Backup Database (super-admin/hr-admin saja, lihat RolePermissionSeeder) ---
    Route::get('/settings/backup', [\App\Http\Controllers\BackupController::class, 'index'])->name('backup.index');
    Route::post('/settings/backup', [\App\Http\Controllers\BackupController::class, 'store'])->name('backup.store');
    Route::get('/settings/backup/{filename}/download', [\App\Http\Controllers\BackupController::class, 'download'])->name('backup.download');
    Route::delete('/settings/backup/{filename}', [\App\Http\Controllers\BackupController::class, 'destroy'])->name('backup.destroy');

    // --- Log Aktivitas (audit trail) ---
    Route::get('/settings/activity-log', [\App\Http\Controllers\ActivityLogController::class, 'index'])->name('activity-log.index');
});

// Unduh slip gaji PDF: dipisah dari grup di atas karena harus bisa diakses baik oleh sesi browser
// (guard "web", dari tombol di halaman) MAUPUN aplikasi mobile yang memakai token API (guard "sanctum").
Route::middleware(['auth:web,sanctum'])
    ->get('/payroll/items/{payrollItem}/slip', [\App\Http\Controllers\PayrollController::class, 'downloadPayslip'])
    ->name('payroll.items.slip');
