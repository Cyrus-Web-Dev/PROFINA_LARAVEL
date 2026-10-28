# MKP (Keuangan) — overlay integrasi PAYBRIDGE

Menambah modul **Dana Payroll**: menerima pengajuan dana gaji dari PAYBRIDGE, membuat **jurnal akrual**, **jurnal pencairan**, dan **jurnal setoran PPh 21/BPJS**,
serta menerapkan anggaran SDM ke modul Anggaran. Dibuat dari zip `mkp-app-fixed` yang kamu kirim.

## Pasang
```bash
cp -r <overlay>/* .
php artisan migrate                                  # payroll_fundings, payroll_remittances, payroll_events
php artisan db:seed --class=RolePermissionSeeder     # izin payroll.decide & payroll.disburse
php artisan config:clear && npm run build
```
Isi `.env` (blok `# --- Integrasi PAYBRIDGE ---`): `PAYROLL_URL`, `PAYROLL_TOKEN`, `PAYROLL_WEBHOOK_SECRET`. Beri izin `payroll.decide`/`payroll.disburse` ke orang yang tepat di Kelola Pengguna (posisi "Finance" otomatis mendapat keduanya pada pengguna baru).
Akun buku besar baru (5-1105…5-1125, 2-1310…2-1340, 1-1800) **dibuat otomatis** saat pertama dipakai (`config/payroll.php`); ubah pemetaan di sana bila bagan akunmu berbeda.

## Berkas BARU
- `app/Console/Commands/PayrollSync.php`
- `app/Exceptions/PayrollException.php`
- `app/Http/Controllers/Api/PayrollWebhookController.php`
- `app/Http/Controllers/PayrollFundingController.php`
- `app/Models/PayrollEvent.php`
- `app/Models/PayrollFunding.php`
- `app/Models/PayrollRemittance.php`
- `app/Services/PayrollClient.php`
- `app/Services/PayrollFundingService.php`
- `config/payroll.php`
- `database/migrations/2026_10_01_000001_create_payroll_integration_tables.php`
- `resources/js/Pages/Payroll/Index.vue`
- `resources/js/Pages/Payroll/Show.vue`

## Berkas DIUBAH
- `.env.example`
- `config/mkp.php`
- `database/seeders/RolePermissionSeeder.php`
- `resources/js/Layouts/AppLayout.vue`
- `routes/api.php`
- `routes/console.php`
- `routes/web.php`

> ⚠ Berkas "diubah" adalah versi penuh dari zip yang kamu kirim. Bila project berjalanmu sudah berbeda, gunakan `git apply PERUBAHAN-berkas-lama.patch`.

## Catatan temuan pada zip yang dikirim (tidak saya ubah)
- `app/Http/Controllers/Controller.php` (kelas dasar standar Laravel) **tidak ada** di zip; project jalanmu pasti memilikinya. Modul baru tidak memakai `$this->middleware()` di constructor sehingga tidak bergantung pada isinya.
- `app/Http/Controllers/FinanceDeskController.php` merujuk model yang tidak ada (`FundingRequest`, `PurchaseRequest`, `Report`, `Workflow`, `ProcurementService`) dan tidak terdaftar di rute: tampaknya sisa lama; aman dihapus bila memang tidak dipakai.
- Sisi MKP tetap memakai database aslinya; hanya PAYBRIDGE yang wajib PostgreSQL (trigger integritas).

## Alur di MKP
Menu **Dana Payroll** → *Menunggu keputusan* (setujui → jurnal akrual; tolak/revisi memberi catatan ke Payroll) → *Siap dicairkan* (nominal **terkunci** sama dengan total file transfer bank; hash file ditampilkan untuk dicocokkan) → *Setoran pajak & BPJS* (bayar → jurnal; Payroll diberi tahu).
