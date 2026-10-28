# SDM — overlay integrasi PAYBRIDGE (Payroll & Budgeting)

Menambah **halaman "Integrasi Payroll"** dan jembatan API/webhook ke PAYBRIDGE. Dibuat dari zip `sdm-hris` yang kamu kirim.

## Pasang
```bash
# dari folder project SDM (cadangkan dulu / pakai git)
cp -r <overlay>/* .          # berkas BARU aman; berkas UBAH menimpa versi lama (lihat catatan)
php artisan migrate          # 4 tabel baru: integration_clients, integration_deliveries, integration_inbox, payslip_mirrors
php artisan db:seed --class=RolePermissionSeeder     # izin baru integrations.manage (super-admin saja)
php artisan config:clear && npm run build
```
`.env` SDM: lihat blok `# --- Integrasi Payroll (PAYBRIDGE) ---` di `.env.example` (PAYROLL_WEBHOOK_SECRET, PAYROLL_PORTAL_URL, SDM_PAYROLL_EXTERNAL, SDM_COUNT_UNRECORDED_AS_ALPHA).
Langkah penyambungan lengkap: `docs/INTEGRASI.md` di project PAYBRIDGE.

## Berkas BARU
- `app/Console/Commands/IntegrationRetry.php`
- `app/Http/Controllers/Api/PayrollApiController.php`
- `app/Http/Controllers/Api/PayrollWebhookController.php`
- `app/Http/Controllers/PayrollIntegrationController.php`
- `app/Http/Middleware/AuthenticateIntegrationClient.php`
- `app/Models/IntegrationClient.php`
- `app/Models/IntegrationDelivery.php`
- `app/Models/IntegrationInbox.php`
- `app/Models/PayslipMirror.php`
- `app/Observers/EmployeePayrollObserver.php`
- `app/Services/PayrollBridge.php`
- `database/migrations/2026_10_01_000001_create_payroll_integration_tables.php`
- `resources/js/Pages/Integrations/Payroll.vue`
- `resources/js/Pages/Payroll/External.vue`

## Berkas DIUBAH
- `.env.example`
- `app/Http/Controllers/PayrollController.php`
- `app/Providers/AppServiceProvider.php`
- `bootstrap/app.php`
- `config/hr.php`
- `database/seeders/RolePermissionSeeder.php`
- `resources/js/Layouts/AppLayout.vue`
- `resources/js/Pages/Payroll/Index.vue`
- `routes/api.php`
- `routes/console.php`
- `routes/web.php`

> ⚠ Berkas "diubah" di folder ini adalah versi **penuh** berdasarkan zip yang kamu kirim. Bila project SDM yang berjalan sudah berbeda,
> jangan menimpa; terapkan perubahan saja: `git apply PERUBAHAN-berkas-lama.patch` (atau `patch -p1 < PERUBAHAN-berkas-lama.patch`).

## Apa yang berubah di perilaku SDM
1. **Halaman Integrasi Payroll** (menu Sistem, super-admin): buat klien API untuk PAYBRIDGE, token & rahasia tampil sekali, log webhook keluar/masuk, kirim ulang, status koneksi.
2. **REST API** `/api/payroll/v1/*` (karyawan, departemen, libur, ringkasan absensi/cuti) dengan token klien ber-scope; data sensitif hanya untuk scope `employees:sensitive`.
3. **Webhook keluar** saat karyawan dibuat/diubah/resign/ganti rekening/ganti **gaji pokok** (observer `EmployeePayrollObserver`). Ganti gaji di SDM menjadi **usulan kontrak** di PAYBRIDGE, bukan gaji yang langsung berlaku.
4. **Webhook masuk** `/api/payroll/webhook`: kontrak aktif menyamakan `base_salary` SDM (tanpa memicu webhook balik), kontrak ditolak memberi notifikasi ke Admin HR, slip terbit disimpan sebagai ringkasan ("Slip Gaji (Payroll)" di menu karyawan).
5. `SDM_PAYROLL_EXTERNAL=true` mengunci modul payroll lama (hitung/ubah/lunas dinonaktifkan; data lama tetap terlihat). Default `false` supaya aman.
6. Penjadwal: `sdm:integration-retry` tiap 5 menit.
