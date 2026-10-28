<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FundingController;
use App\Http\Controllers\InputController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MasterController;
use App\Http\Controllers\OpeningBalanceController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RemittanceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('welcome');

require __DIR__.'/auth.php';

/*
| Halaman internal (wajib login + akun aktif). Otorisasi per-izin ada di constructor tiap controller (Spatie Permission).
| Aturan pemisahan tugas (pembuat != pemeriksa != penyetuju) ditegakkan di lapisan layanan, bukan hanya di menu.
*/
Route::middleware(['auth', 'has.role'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto'])->name('profile.photo.upload');
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto'])->name('profile.photo.delete');
    Route::get('/profile/{user}/photo', [ProfileController::class, 'photo'])->name('profile.photo');

    // Slip gaji saya (layanan mandiri)
    Route::get('/my/payslips', [PayslipController::class, 'mine'])->name('payslips.mine');
    Route::get('/payslips/{item}/pdf', [PayslipController::class, 'download'])->name('payslips.pdf');

    // Karyawan (cermin SDM)
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('/employees/sync', [EmployeeController::class, 'sync'])->name('employees.sync');
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');

    // Kontrak kompensasi
    Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
    Route::get('/contracts/create', [ContractController::class, 'create'])->name('contracts.create');
    Route::post('/contracts', [ContractController::class, 'store'])->name('contracts.store');
    Route::get('/contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show');
    Route::post('/contracts/{contract}/decide', [ContractController::class, 'decide'])->name('contracts.decide');

    // Data induk & regulasi
    Route::get('/master', [MasterController::class, 'masters'])->middleware('permission:regulations.view')->name('master.index');
    Route::post('/master/grades', [MasterController::class, 'gradeSave'])->name('master.grades.save');
    Route::post('/master/components', [MasterController::class, 'componentSave'])->name('master.components.save');
    Route::get('/regulations', [MasterController::class, 'regulations'])->middleware('permission:regulations.view')->name('regulations.index');
    Route::post('/regulations', [MasterController::class, 'regulationPublish'])->name('regulations.publish');

    // Input variabel & kasbon
    Route::get('/inputs', [InputController::class, 'index'])->name('inputs.index');
    Route::post('/inputs', [InputController::class, 'store'])->name('inputs.store');
    Route::post('/inputs/{input}/decide', [InputController::class, 'decide'])->name('inputs.decide');
    Route::post('/inputs/{input}/cancel', [InputController::class, 'cancel'])->name('inputs.cancel');
    Route::get('/loans', [LoanController::class, 'index'])->name('loans.index');
    Route::post('/loans', [LoanController::class, 'store'])->name('loans.store');
    Route::post('/loans/{loan}/decide', [LoanController::class, 'decide'])->name('loans.decide');

    // Putaran payroll
    Route::get('/runs', [RunController::class, 'index'])->name('runs.index');
    Route::post('/runs', [RunController::class, 'store'])->name('runs.store');
    Route::get('/runs/{run}', [RunController::class, 'show'])->name('runs.show');
    Route::post('/runs/{run}/calculate', [RunController::class, 'calculate'])->name('runs.calculate');
    Route::post('/runs/{run}/submit', [RunController::class, 'submit'])->name('runs.submit');
    Route::post('/runs/{run}/decide', [RunController::class, 'decide'])->name('runs.decide');
    Route::post('/runs/{run}/cancel', [RunController::class, 'cancel'])->name('runs.cancel');
    Route::post('/runs/{run}/resubmit-funding', [RunController::class, 'resubmitFunding'])->name('runs.resubmit-funding');
    Route::post('/runs/{run}/bank', [RunController::class, 'bankGenerate'])->name('runs.bank');
    Route::post('/runs/{run}/payslips/email', [RunController::class, 'emailPayslips'])->name('runs.payslips.email');
    Route::get('/runs/{run}/export', [RunController::class, 'exportRegister'])->name('runs.export');
    Route::get('/run-items/{item}', [RunController::class, 'item'])->name('runs.item');
    Route::post('/run-items/{item}/hold', [RunController::class, 'hold'])->name('runs.hold');
    Route::post('/run-items/{item}/release', [RunController::class, 'release'])->name('runs.release');
    Route::post('/findings/{finding}/resolve', [RunController::class, 'resolveFinding'])->name('findings.resolve');
    Route::get('/batches/{batch}/download', [RunController::class, 'bankDownload'])->name('batches.download');
    Route::post('/batches/{batch}/reconcile', [RunController::class, 'bankReconcile'])->name('batches.reconcile');

    // Keuangan & kepatuhan
    Route::get('/funding', [FundingController::class, 'index'])->name('funding.index');
    Route::get('/remittances', [RemittanceController::class, 'index'])->name('remittances.index');
    Route::post('/remittances/{remittance}/paid', [RemittanceController::class, 'markPaid'])->name('remittances.paid');

    // Anggaran
    Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets.index');
    Route::post('/budgets', [BudgetController::class, 'store'])->name('budgets.store');
    Route::get('/budgets/{plan}', [BudgetController::class, 'show'])->name('budgets.show');
    Route::put('/budgets/{plan}/lines', [BudgetController::class, 'saveLines'])->name('budgets.lines');
    Route::post('/budgets/{plan}/submit', [BudgetController::class, 'submit'])->name('budgets.submit');
    Route::post('/budgets/{plan}/decide', [BudgetController::class, 'decide'])->name('budgets.decide');
    Route::post('/budgets/{plan}/version', [BudgetController::class, 'newVersion'])->name('budgets.version');

    // Laporan
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/pph21', [ReportController::class, 'pph21'])->name('reports.pph21');
    Route::get('/reports/bpjs', [ReportController::class, 'bpjs'])->name('reports.bpjs');
    Route::get('/reports/departments', [ReportController::class, 'departments'])->name('reports.departments');
    Route::get('/reports/annual-tax', [ReportController::class, 'annualTax'])->name('reports.annual-tax');

    // Audit
    Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
    Route::post('/audit/verify', [AuditController::class, 'verify'])->name('audit.verify');
    Route::get('/audit/export', [AuditController::class, 'export'])->name('audit.export');

    // Integrasi
    Route::get('/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::post('/integrations/clients', [IntegrationController::class, 'store'])->name('integrations.store');
    Route::put('/integrations/clients/{client}', [IntegrationController::class, 'update'])->name('integrations.update');
    Route::post('/integrations/clients/{client}/rotate', [IntegrationController::class, 'rotate'])->name('integrations.rotate');
    Route::post('/integrations/clients/{client}/ping', [IntegrationController::class, 'ping'])->name('integrations.ping');
    Route::delete('/integrations/clients/{client}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');
    Route::post('/integrations/deliveries/{delivery}/resend', [IntegrationController::class, 'resend'])->name('integrations.resend');
    Route::post('/integrations/sdm/test', [IntegrationController::class, 'sdmTest'])->name('integrations.sdm-test');
    Route::post('/integrations/inbound/{event}/replay', [IntegrationController::class, 'replay'])->name('integrations.replay');

    // Saldo awal & pengaturan
    Route::get('/opening-balances', [OpeningBalanceController::class, 'index'])->name('opening.index');
    Route::post('/opening-balances', [OpeningBalanceController::class, 'store'])->name('opening.store');
    Route::get('/settings/company', [SettingsController::class, 'edit'])->name('settings.company');
    Route::put('/settings/company', [SettingsController::class, 'update'])->name('settings.company.update');

    // Pengguna
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('/users/{user}/approve', [UserManagementController::class, 'approve'])->name('users.approve');
    Route::post('/users/{user}/reject', [UserManagementController::class, 'reject'])->name('users.reject');
    Route::put('/users/{user}/employee', [UserManagementController::class, 'linkEmployee'])->name('users.link-employee');
    Route::post('/users/{user}/toggle-active', [UserManagementController::class, 'toggleActive'])->name('users.toggle-active');
    Route::put('/users/{user}/roles', [UserManagementController::class, 'updateRoles'])->name('users.roles');

    // Backup & log aktivitas
    Route::get('/settings/backup', [BackupController::class, 'index'])->name('backup.index');
    Route::post('/settings/backup', [BackupController::class, 'store'])->name('backup.store');
    Route::get('/settings/backup/{filename}/download', [BackupController::class, 'download'])->name('backup.download');
    Route::delete('/settings/backup/{filename}', [BackupController::class, 'destroy'])->name('backup.destroy');
    Route::get('/settings/activity-log', [ActivityLogController::class, 'index'])->name('activity-log.index');
});
