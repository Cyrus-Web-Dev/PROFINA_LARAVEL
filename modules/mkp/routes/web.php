<?php

use App\Http\Controllers\Admin\PeriodController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BankReconciliationController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CashBankController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FixedAssetController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\PayrollFundingController;
use App\Http\Controllers\ProcurementFundingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Settings\ActivityLogController;
use App\Http\Controllers\Settings\CompanyProfileController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;

// Halaman awal: langsung ke dashboard (kalau sudah login) atau ke halaman login.
// (Versi lama merender komponen Vue "Welcome" yang tidak ada -> layar putih + error di console.)
Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'))->name('home');

Route::middleware(['auth', 'has.role'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/notifications/summary', [NotificationController::class, 'summary'])->name('notifications.summary');

    // Modul Chart of Accounts (Daftar Akun)
    Route::get('/chart-of-accounts', [ChartOfAccountController::class, 'index'])->name('coa.index');
    Route::post('/chart-of-accounts', [ChartOfAccountController::class, 'store'])->middleware('permission:coa.manage')->name('coa.store');
    Route::put('/chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'update'])->middleware('permission:coa.manage')->name('coa.update');
    Route::delete('/chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'destroy'])->middleware('permission:coa.manage')->name('coa.destroy');

    // Modul Jurnal Umum
    Route::get('/journals', [JournalController::class, 'index'])->name('journals.index');
    Route::get('/journals/create', [JournalController::class, 'create'])->middleware('permission:journal.create')->name('journals.create');
    Route::post('/journals', [JournalController::class, 'store'])->middleware('permission:journal.create')->name('journals.store');
    Route::get('/journals/{journal}', [JournalController::class, 'show'])->name('journals.show');
    Route::get('/journals/{journal}/edit', [JournalController::class, 'edit'])->middleware('permission:journal.create')->name('journals.edit');
    Route::put('/journals/{journal}', [JournalController::class, 'update'])->middleware('permission:journal.create')->name('journals.update');
    Route::delete('/journals/{journal}', [JournalController::class, 'destroy'])->middleware('permission:journal.create')->name('journals.destroy');
    Route::post('/journals/{journal}/post', [JournalController::class, 'post'])->middleware('permission:journal.post')->name('journals.post');
    Route::post('/journals/{journal}/void', [JournalController::class, 'void'])->middleware('permission:journal.void')->name('journals.void');
    Route::post('/journals/{journal}/approve', [JournalController::class, 'approve'])->middleware('permission:journal.approve')->name('journals.approve');
    Route::post('/journals/{journal}/reject', [JournalController::class, 'reject'])->middleware('permission:journal.approve')->name('journals.reject');
    Route::get('/journals-export/excel', [JournalController::class, 'exportExcel'])->name('journals.export-excel');

    // Modul Buku Besar
    Route::get('/ledger', [LedgerController::class, 'index'])->name('ledger.index');

    // Modul Laporan Keuangan
    Route::get('/reports/trial-balance', [ReportController::class, 'trialBalance'])->name('reports.trial-balance');
    Route::get('/reports/income-statement', [ReportController::class, 'incomeStatement'])->name('reports.income-statement');
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
    Route::get('/reports/cash-flow', [ReportController::class, 'cashFlow'])->name('reports.cash-flow');
    Route::get('/reports/ratios', [ReportController::class, 'ratios'])->name('reports.ratios');
    Route::get('/reports/trial-balance/pdf', [ReportController::class, 'exportTrialBalancePdf'])->name('reports.trial-balance.pdf');
    Route::get('/reports/income-statement/pdf', [ReportController::class, 'exportIncomeStatementPdf'])->name('reports.income-statement.pdf');
    Route::get('/reports/balance-sheet/pdf', [ReportController::class, 'exportBalanceSheetPdf'])->name('reports.balance-sheet.pdf');
    Route::get('/reports/trial-balance/excel', [ReportController::class, 'exportTrialBalanceExcel'])->name('reports.trial-balance.excel');
    Route::get('/reports/income-statement/excel', [ReportController::class, 'exportIncomeStatementExcel'])->name('reports.income-statement.excel');
    Route::get('/reports/balance-sheet/excel', [ReportController::class, 'exportBalanceSheetExcel'])->name('reports.balance-sheet.excel');

    // Lampiran bukti transaksi (hak akses menambah diperiksa di controller sesuai jenis dokumen)
    Route::post('/attachments', [AttachmentController::class, 'store'])->middleware('throttle:30,1')->name('attachments.store');
    Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    // Modul Piutang Usaha (AR)
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:ar.manage')->name('customers.store');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:ar.manage')->name('customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:ar.manage')->name('customers.destroy');

    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [InvoiceController::class, 'create'])->middleware('permission:ar.manage')->name('invoices.create');
    Route::post('/invoices', [InvoiceController::class, 'store'])->middleware('permission:ar.manage')->name('invoices.store');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'pay'])->middleware('permission:ar.manage')->name('invoices.pay');
    Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->middleware('permission:ar.manage')->name('invoices.void');
    Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');

    // Modul Utang Usaha (AP)
    Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index');
    Route::post('/vendors', [VendorController::class, 'store'])->middleware('permission:ap.manage')->name('vendors.store');
    Route::put('/vendors/{vendor}', [VendorController::class, 'update'])->middleware('permission:ap.manage')->name('vendors.update');
    Route::delete('/vendors/{vendor}', [VendorController::class, 'destroy'])->middleware('permission:ap.manage')->name('vendors.destroy');

    Route::get('/bills', [BillController::class, 'index'])->name('bills.index');
    Route::get('/bills/create', [BillController::class, 'create'])->middleware('permission:ap.manage')->name('bills.create');
    Route::post('/bills', [BillController::class, 'store'])->middleware('permission:ap.manage')->name('bills.store');
    Route::get('/bills/{bill}', [BillController::class, 'show'])->name('bills.show');
    Route::post('/bills/{bill}/pay', [BillController::class, 'pay'])->middleware('permission:ap.manage')->name('bills.pay');
    Route::post('/bills/{bill}/void', [BillController::class, 'void'])->middleware('permission:ap.manage')->name('bills.void');

    // Modul Kas & Bank
    Route::get('/cash-bank', [CashBankController::class, 'index'])->name('cashbank.index');
    Route::post('/cash-bank/transfer', [CashBankController::class, 'transfer'])->middleware('permission:cashbank.manage')->name('cashbank.transfer');

    // Modul Dana Pengadaan (terhubung ke sistem PROCURA lewat REST API)
    Route::prefix('procurement-funds')->name('procurement.')->group(function () {
        Route::get('/', [ProcurementFundingController::class, 'index'])->name('index');
        Route::get('/{funding}', [ProcurementFundingController::class, 'show'])->whereNumber('funding')->name('show');
        Route::post('/reports/read-all', [ProcurementFundingController::class, 'readAll'])->name('reports.read-all');
        Route::post('/reports/{report}/read', [ProcurementFundingController::class, 'read'])->whereNumber('report')->name('reports.read');

        Route::middleware('permission:procurement.decide|procurement.disburse')->group(function () {
            Route::post('/sync', [ProcurementFundingController::class, 'sync'])->middleware('throttle:20,1')->name('sync');
            Route::post('/note', [ProcurementFundingController::class, 'note'])->middleware('throttle:20,1')->name('note');
            Route::post('/{funding}/refresh', [ProcurementFundingController::class, 'refresh'])->whereNumber('funding')->middleware('throttle:30,1')->name('refresh');
        });
        Route::post('/{funding}/decide', [ProcurementFundingController::class, 'decide'])->whereNumber('funding')->middleware('permission:procurement.decide')->name('decide');
        Route::post('/{funding}/disburse', [ProcurementFundingController::class, 'disburse'])->whereNumber('funding')->middleware('permission:procurement.disburse')->name('disburse');
        Route::post('/{funding}/settle', [ProcurementFundingController::class, 'settle'])->whereNumber('funding')->middleware('permission:procurement.disburse')->name('settle');
    });

    // Modul Dana Payroll (terhubung ke PAYBRIDGE lewat REST API + webhook)
    Route::prefix('payroll-funds')->name('payroll.')->group(function () {
        Route::get('/', [PayrollFundingController::class, 'index'])->name('index');
        Route::get('/{funding}', [PayrollFundingController::class, 'show'])->whereNumber('funding')->name('show');
        Route::middleware('permission:payroll.decide|payroll.disburse')->group(function () {
            Route::post('/sync', [PayrollFundingController::class, 'sync'])->middleware('throttle:20,1')->name('sync');
            Route::post('/{funding}/refresh', [PayrollFundingController::class, 'refresh'])->whereNumber('funding')->middleware('throttle:30,1')->name('refresh');
        });
        Route::post('/{funding}/decide', [PayrollFundingController::class, 'decide'])->whereNumber('funding')->middleware('permission:payroll.decide')->name('decide');
        Route::post('/{funding}/disburse', [PayrollFundingController::class, 'disburse'])->whereNumber('funding')->middleware('permission:payroll.disburse')->name('disburse');
        Route::post('/remittances/{remittance}/pay', [PayrollFundingController::class, 'payRemittance'])->whereNumber('remittance')->middleware('permission:payroll.disburse')->name('remittance.pay');
    });

    // Modul Aset Tetap
    Route::get('/fixed-assets', [FixedAssetController::class, 'index'])->name('fixed-assets.index');
    Route::get('/fixed-assets/create', [FixedAssetController::class, 'create'])->middleware('permission:fixed-asset.manage')->name('fixed-assets.create');
    Route::post('/fixed-assets', [FixedAssetController::class, 'store'])->middleware('permission:fixed-asset.manage')->name('fixed-assets.store');
    Route::get('/fixed-assets/{fixedAsset}', [FixedAssetController::class, 'show'])->name('fixed-assets.show');
    Route::post('/fixed-assets/{fixedAsset}/run-depreciation', [FixedAssetController::class, 'runDepreciation'])->middleware('permission:fixed-asset.manage')->name('fixed-assets.run-depreciation');

    // Modul Anggaran (Budgeting)
    Route::get('/budgets', [BudgetController::class, 'index'])->name('budgets.index');
    Route::get('/budgets/edit', [BudgetController::class, 'edit'])->middleware('permission:budget.manage')->name('budgets.edit');
    Route::put('/budgets', [BudgetController::class, 'update'])->middleware('permission:budget.manage')->name('budgets.update');

    // Modul Rekonsiliasi Bank
    Route::get('/bank-reconciliation', [BankReconciliationController::class, 'index'])->name('bank-reconciliation.index');
    Route::post('/bank-reconciliation/mark', [BankReconciliationController::class, 'markReconciled'])->middleware('permission:bank-reconciliation.manage')->name('bank-reconciliation.mark');

    // Pengaturan (khusus admin; dijaga middleware role:admin - $this->middleware() di constructor tidak ada di Laravel 11)

    // Kelola pengguna & backup (khusus Manager)
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users/{user}/approve', [UserManagementController::class, 'approve'])->name('users.approve');
        Route::post('/users/{user}/reject', [UserManagementController::class, 'reject'])->name('users.reject');
        Route::put('/users/{user}/access', [UserManagementController::class, 'updateAccess'])->name('users.access');
        Route::post('/users/{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('users.deactivate');
        Route::post('/users/{user}/activate', [UserManagementController::class, 'activate'])->name('users.activate');
        Route::post('/users/{user}/message', [UserManagementController::class, 'message'])->middleware('throttle:10,1')->name('users.message');

        Route::get('/periods', [PeriodController::class, 'index'])->name('periods.index');
        Route::post('/periods/close', [PeriodController::class, 'close'])->name('periods.close');
        Route::post('/periods/reopen', [PeriodController::class, 'reopen'])->name('periods.reopen');

        Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
        Route::post('/backup', [BackupController::class, 'store'])->middleware('throttle:6,1')->name('backup.store');
        Route::get('/backup/{filename}/download', [BackupController::class, 'download'])->name('backup.download');
        Route::delete('/backup/{filename}', [BackupController::class, 'destroy'])->name('backup.destroy');
    });

    // Profil Perusahaan
    Route::get('/settings/company', [CompanyProfileController::class, 'edit'])->middleware('role:admin')->name('settings.company.edit');
    Route::put('/settings/company', [CompanyProfileController::class, 'update'])->middleware('role:admin')->name('settings.company.update');
    Route::post('/settings/company/logo', [CompanyProfileController::class, 'uploadLogo'])->middleware('role:admin')->name('settings.company.logo.upload');
    Route::delete('/settings/company/logo', [CompanyProfileController::class, 'deleteLogo'])->middleware('role:admin')->name('settings.company.logo.delete');
    Route::get('/settings/company/logo', [CompanyProfileController::class, 'logo'])->middleware('role:admin')->name('settings.company.logo');

    // Activity Log (audit trail)
    Route::get('/settings/activity-log', [ActivityLogController::class, 'index'])->middleware('role:admin')->name('settings.activity-log.index');
});

// Profil pengguna: cukup login (tidak butuh role), supaya akun baru pun bisa melengkapi datanya.
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto'])->name('profile.photo.upload');
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto'])->name('profile.photo.delete');
    Route::get('/profile/photo/{user}', [ProfileController::class, 'photo'])->name('profile.photo');
});

require __DIR__.'/auth.php';
