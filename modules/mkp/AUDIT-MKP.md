# Laporan Audit MKP

Cakupan: seluruh isi `mkp-app` (PHP, Vue, konfigurasi, migrasi, README), dibandingkan dengan proyek Inventaris yang berhasil memakai Gmail API.

**Metode & batasan.** Audit dilakukan dengan membaca seluruh kode dan skrip pemeriksa statis
(kecocokan route ↔ controller ↔ halaman Vue ↔ `route()` di frontend, keseimbangan kurung/tag, import class PHP,
sintaks blok `<script setup>` via `node --check`).
**PHP, Composer, dan npm tidak bisa dijalankan** di lingkungan audit, jadi hasil perbaikan **belum pernah dieksekusi**.
Temuan bertanda *[pasti]* terbukti dari kode; *[dugaan]* adalah kemungkinan yang perlu kamu konfirmasi.

---

## A. Kenapa loading lama dan error

| # | Temuan | Tingkat | Status |
|---|---|---|---|
| A1 | Route `/` merender komponen Vue `Welcome` yang tidak ada → layar putih *[pasti]* | Kritis | Diganti redirect ke dashboard/login |
| A2 | `Route::get('/dashboard', DashboardController::class)` tetapi kelas tak punya `__invoke` → "Invalid route action" *[pasti]* | Kritis | Diubah ke `[DashboardController::class, 'index']` |
| A3 | `"undefined" is not valid JSON` di `app.js`: klien Inertia tidak menemukan `data-page`. Biasanya versi `inertia-laravel` (PHP) dan `@inertiajs/vue3` (JS) tidak sejajar, mis. setelah `composer require` tanpa versi *[dugaan kuat]* | Kritis | `app.js` kini membaca dua format data halaman + menampilkan panel diagnosis; versi dipin (`^1.x`) |
| A4 | Tidak ada `postcss.config.js` → Tailwind tidak diproses *[pasti]* | Tinggi | Ditambahkan |
| A5 | `tailwind.config.js` memakai `require()` di file ESM *[pasti berisiko]* | Sedang | Diganti `import` |
| A6 | Font eksternal (fonts.bunny.net) di `<head>` menahan render *[pasti]* | Sedang | Dihapus, memakai font sistem |
| A7 | Vite memantau `vendor/` & `storage/` (ribuan file, lambat di Windows) *[pasti]* | Sedang | `server.watch.ignored` |
| A8 | Vite bind `localhost` → bisa IPv6 `[::1]` padahal `artisan serve` IPv4; `public/hot` basi *[dugaan]* | Sedang | `host: 127.0.0.1`, `strictPort`, panduan hapus `public/hot` |
| A9 | Dashboard menjalankan >150 query (1 per akun, berulang) *[pasti]* | Sedang | Laporan memakai 1 query teragregasi |
| A10 | `SESSION_DRIVER/CACHE_STORE/QUEUE=database` → 500 bila migrasi belum jalan | Sedang | Default `file` / `sync` |
| A11 | HTTP ke Google tanpa timeout → login/daftar menggantung | Sedang | Timeout 15 dtk / connect 8 dtk |
| A12 | Zip berisi folder sampah `{database/...}` dan hanya "overlay" ke skeleton; `composer install` dengan `composer.lock` skeleton gagal / tidak memasang paket | Sedang | Proyek dibuat lengkap & mandiri |
| A13 | Tidak ada indikator muat; saat lambat layar kosong | UX | Loader + petunjuk setelah 12 dtk |
| A14 | Dependensi tak terpakai (`@vueup/vue-quill`, `@heroicons/vue`, `laravel/sanctum`, breeze) memperlambat install | Rendah | Dibuang |

## B. Gmail API

| # | Temuan | Tingkat | Status |
|---|---|---|---|
| B1 | `GmailConnectController` & `ActivityLogController` memakai `$this->middleware()` — tidak ada di controller dasar Laravel 11 → halaman **selalu error 500** *[pasti]* | Kritis | Diganti middleware route `role:admin` (alias Spatie didaftarkan di `bootstrap/app.php`). *Pembaruan: halaman Pengaturan Gmail kemudian dihapus seluruhnya atas permintaan; panduan pindah ke README dan pengujian lewat `php artisan mkp:mail-test`* |
| B2 | Mailer `gmail_api` tidak didefinisikan di `mail.mailers` → reset password: "Mailer [gmail_api] is not defined" *[pasti]* | Kritis | `config/mail.php` + jaring pengaman di `AppServiceProvider` |
| B3 | Deadlock: login butuh OTP email → butuh Gmail terhubung → butuh login. Tak ada admin awal *[pasti]* | Kritis | Pendaftar pertama = admin; `php artisan mkp:user`; seeder admin opsional; fallback log OTP di mode lokal; kredensial dari `token.json` |
| B4 | `doSend` menelan error (`report()`), UI mengaku terkirim padahal gagal *[pasti]* | Tinggi | Semua kegagalan dilempar (`GmailSendException`) dengan pesan yang bisa ditindaklanjuti |
| B5 | Redirect URI `localhost` vs browser `127.0.0.1`: cookie sesi berbeda host → state OAuth hilang *[sangat mungkin bagi kamu]* | Tinggi | Pengecekan host + pesan jelas + `APP_URL` default `127.0.0.1` |
| B6 | Hanya alur OAuth dalam aplikasi; tidak mendukung `credentials.json`/`token.json` seperti Inventaris | Tinggi | Tiga sumber kredensial (DB → file → `.env`) |
| B7 | `From: me`, isi 8-bit tanpa encoding → rawan rusak untuk karakter non-ASCII | Sedang | MIME dibuat dengan Symfony Mime |
| B8 | Pesan error mentah dari Google (JSON) ditampilkan ke pengguna | Sedang | Pesan bersih; detail hanya di log |
| B9 | Rate limit register terpakai oleh salah ketik form & kegagalan Gmail | Sedang | Dihitung setelah validasi |
| B10 | Access token diminta ke Google tiap email | Rendah | Di-cache sampai kedaluwarsa |
| B11 | `token.json` berstatus "Testing" hangus 7 hari (`invalid_grant`) | Info | Dijelaskan di pesan error, halaman & README |
| B12 | Lupa password membocorkan apakah email terdaftar | Sedang | Jawaban selalu sama |
| B13 | Sistem tidak punya cara menguji pengiriman | UX | Tombol "Kirim email percobaan" + diagnosis |

**Kenapa Inventaris berhasil:** memakai `credentials.json` + `token.json` langsung dan melempar error saat gagal; tidak butuh alur OAuth
di dalam aplikasi. `token.json` berasal dari alur persetujuan OAuth Google satu kali (mis. skrip quickstart Google);
MKP kini bisa memakai file yang sama — cukup disalin ke `storage/app/credentials/`.

## C. Logika akuntansi & data

| # | Temuan | Status |
|---|---|---|
| C1 | Neraca: akun kontra (Akumulasi Depresiasi, Prive) dijumlahkan positif → tidak seimbang. Sekarang saldo bertanda menurut **jenis** akun | Diperbaiki |
| C2 | Laporan hanya memuat akun aktif; akun nonaktif bersaldo hilang dari Neraca Saldo | Diperbaiki (`reportable`) |
| C3 | Void jurnal diizinkan untuk jurnal otomatis invoice/bill/pembayaran/aset/pembalik → buku pembantu ≠ buku besar; juga tanpa cek status | Diblokir, pesan mengarahkan ke pembatalan dokumen sumber |
| C4 | Baris jurnal boleh mengisi debit dan kredit sekaligus / dua-duanya kosong | Divalidasi |
| C5 | Pembulatan per-item vs total → selisih Rp0,01 membuat jurnal ditolak | Dibulatkan ke 2 desimal |
| C6 | Pajak > 0 tanpa akun pajak → jurnal tidak seimbang | Validasi jelas |
| C7 | Pembayaran tanpa penguncian → dua pembayaran bersamaan menimpa saldo | `lockForUpdate` dalam transaksi |
| C8 | Depresiasi bisa dijalankan dua kali dengan tanggal berbeda dalam bulan sama; boleh sebelum bulan perolehan | Dinormalisasi ke akhir bulan + dikunci |
| C9 | Pencarian COA: `name OR code AND type` (prioritas operator salah) | Dikelompokkan |
| C10 | `hasPermissionTo()` melempar exception bila seeder role belum jalan (halaman jurnal & notifikasi 500) | Diganti `can()` |
| C11 | Nomor jurnal diurut berdasarkan `id`, bukan nomor | Diurut nomor |
| C12 | Item invoice/bill harga 0 menghasilkan baris jurnal 0 | `gt:0` |
| C13 | Zona waktu UTC → "hari ini" salah sebelum 07:00 WIB | `Asia/Jakarta` di server & tanggal lokal di browser |
| C14 | Log aktivitas menampilkan ISO-UTC mentah | Diformat `Y-m-d H:i:s` |
| C15 | Akun COA baru tanpa kategori tidak ikut Arus Kas/Rasio | Pilihan kategori + saldo normal otomatis |

## D. Keamanan & hak akses

| # | Temuan | Status |
|---|---|---|
| D1 | Izin (`journal.post`, `coa.manage`, ...) tidak pernah ditegakkan; siapa pun yang login bisa semuanya | Middleware `permission:` di setiap route tulis |
| D2 | Pendaftaran publik memberi akses penuh ke data keuangan | Ditutup default; user tanpa role → 403; pendaftar pertama = admin |
| D3 | Menu Gmail/Activity Log tampil untuk semua, lalu 403 | Disembunyikan bagi non-admin |
| D4 | Sesi habis (419) memunculkan modal HTML mentah | Kembali dengan pesan ramah |
| D5 | Perbandingan waktu password tidak seragam untuk email tak dikenal | Hash dummy |

## E. UI/UX

| # | Temuan | Status |
|---|---|---|
| E1 | Pesan error server (gagal posting, tidak bisa hapus akun, dll.) tidak tampil di mana pun | Banner global + ringkasan error di form |
| E2 | Error baris jurnal/item (`lines.0.account_id`) tidak tampil | `ErrorSummary` |
| E3 | Mode gelap: teks tak terbaca (kartu login putih dengan teks terang, angka tanpa warna) | Warna dasar + input ikut tema; tema diterapkan sebelum render |
| E4 | Jurnal, invoice, bill hanya 15 data pertama (tanpa paginasi) | Paginasi + filter dipertahankan |
| E5 | Rasio Keuangan tidak berubah saat filter diganti | `computed` |
| E6 | Tabel meluber di layar kecil | Dibungkus scroll horizontal |
| E7 | Tombol OTP "kirim ulang" tidak sinkron dengan jeda server | Hitung mundur awal dari server |
| E8 | Nominal bayar memakai nilai usang | Diisi ulang saat modal dibuka |
| E9 | Klik di luar dropdown notifikasi tidak menutup (selektor `.relative` terlalu umum); polling 60 dtk walau tab tersembunyi | Diperbaiki |
| E10 | Tombol aksi tampil walau user tak berhak | Disembunyikan menurut permission |

## F. Belum tercakup / perlu kamu perhatikan

- Belum ada UI kelola user (dipakai `php artisan mkp:user`).
- Tidak ada penutupan buku/periode fiskal yang mengunci jurnal lama.
- Belum ada tes otomatis. Setelah `composer install`, jalankan alur: daftar admin → jurnal → posting → laporan.
- CashFlow & Rasio masih menghitung per akun (banyak query) — cukup untuk data kecil.
- `vendor/`, `node_modules/`, dan `public/build` tidak disertakan; buat lewat perintah di README.

## G. Pembaruan setelah audit

- **Halaman Pengaturan Gmail dihapus** (controller, route, menu, halaman Vue, model & migrasi `mail_accounts`, alur OAuth di dalam aplikasi). Kredensial kini hanya dari
  `credentials.json` + `token.json` atau `.env`. Pengujian: `php artisan mkp:mail-test`.
- **Profil Saya** (`/profile`): kolom baru di tabel `users` (username, phone, employee_id, job_title, department, birth_date, address, bio, education JSON, avatar_path),
  ganti password, dan ganti email dengan konfirmasi password. Email/username disimpan huruf kecil.
- **Foto & logo dengan zoom**: komponen `ImageCropper` (zoom, geser, cubit) dan `PhotoViewer` (zoom layar penuh). Dipakai untuk foto profil dan logo perusahaan (tampil di PDF invoice).
  Gambar diolah ulang di server (GD): metadata dibuang, ukuran dikecilkan. Berkas disajikan lewat route aplikasi, bukan `storage:link`.
- Belum diuji dengan menjalankan aplikasi (PHP tidak tersedia di lingkungan audit).
- **Persetujuan Manager & role baru**: kolom `status` (pending/active/rejected/inactive), `position`, `approved_by`, `approved_at`, `status_note` di tabel `users`.
  Pendaftar baru menunggu persetujuan; login diblokir dengan pesan jelas sebelum OTP dikirim; `EnsureUserIsActive` menendang akun yang dinonaktifkan pada request berikutnya.
  Role `accountant` menjadi `staff` (hak akses per orang, disalin otomatis oleh seeder), `admin` tampil sebagai "Manager".
- **Kelola Pengguna**: setujui/tolak/ubah akses/nonaktifkan/aktifkan + kirim pesan Gmail; pengaman Manager terakhir; email pemberitahuan tidak menggagalkan aksi bila gagal terkirim.
- **Backup Data**: mysqldump/pg_dump bila ada, kalau tidak dump DATA lewat PHP (MySQL & PostgreSQL, tabel diurutkan menurut foreign key, sequence PostgreSQL disetel ulang),
  password lewat environment (bukan argumen), nama file divalidasi (anti path traversal), retensi otomatis, perintah `mkp:backup`.
  Berbeda dari backup Inventaris yang memakai `SHOW TABLES` (khusus MySQL) dan memuat seluruh tabel ke memori.
- Pengaturan `MKP_OPEN_REGISTRATION` diganti `MKP_REGISTRATION` (default `open`) agar `.env` lama yang berisi `false` tidak menutup pendaftaran secara tak sengaja.
- Query `lockForUpdate()->exists()` yang meragukan di PostgreSQL pada pendaftaran sudah dihapus.
- Belum diuji dengan menjalankan aplikasi (PHP tidak tersedia di lingkungan audit); alur persetujuan & backup paling perlu diuji langsung.

## H. Pembaruan lanjutan (penutupan periode, lampiran, ekspor Excel, tes otomatis)

- **Penutupan periode** (`PeriodService`, `Admin/PeriodController`, halaman `/admin/periods`): `JournalService::create/post/void/updateDraft`
  kini memanggil `assertOpen()` sebelum menyimpan. Periode disimpan di tabel `fiscal_periods` (sudah ada sejak awal, sebelumnya tidak dipakai).
  Menutup ditolak bila masih ada jurnal draft di bulan itu; membuka kembali wajib alasan.
- **Lampiran bukti transaksi**: tabel polymorphic `attachments` + trait `HasAttachments` pada `Journal`, `Invoice`, `Bill`. Diunggah lewat
  `AttachmentController` dengan validasi tipe/ukuran file dan hak akses per jenis dokumen; disajikan lewat route (bukan symlink publik).
  Komponen `AttachmentPanel.vue` menampilkan gambar/PDF dengan `PhotoViewer` (zoom) yang sudah ada; foto dikecilkan di browser sebelum diunggah
  (`compressImage()` di `utils.js`) supaya foto nota dari HP tidak memakan bandwidth.
- **Ekspor Excel**: `ReportExport` (generik, dipakai tiga laporan) ditambahkan di samping `JournalExport` yang sudah ada; tombol di halaman
  Neraca Saldo, Laba Rugi, dan Neraca.
- **Tes otomatis** (PHPUnit + SQLite in-memory, terpisah dari database asli): mencakup keseimbangan jurnal, akun kontra pada Neraca (regresi bug
  C1 di atas), larangan void jurnal otomatis, penguncian periode, alur pendaftaran & persetujuan Manager, hak akses per role/posisi, backup, dan
  lampiran. Jalankan dengan `php artisan test`.
- Belum diuji dengan menjalankan aplikasi sungguhan di luar test-suite (PHP tidak tersedia di lingkungan audit) — hasil `php artisan test` di
  komputermu adalah verifikasi nyata pertama untuk seluruh perubahan sejak audit awal.

## I. Bug nyata ditemukan lewat `php artisan test` (dikirim balik oleh pengguna)

Menjalankan tes di komputer pengguna (Windows/XAMPP) berhasil membuktikan satu bug nyata di kode yang saya tulis, dan sekaligus mengungkap tiga
bug lama pada kode yang sudah ada sebelum saya sentuh:

- **Akar masalah**: kolom bertipe cast Eloquent `'date'` (mis. `journal_date`, `start_date`, `end_date`, `period_date`) secara fisik tersimpan
  ke database sebagai `"YYYY-MM-DD 00:00:00"` (dengan jam), bukan `"YYYY-MM-DD"` polos. MySQL/PostgreSQL biasanya memotong jam itu sendiri karena
  tipe kolomnya `DATE`, tapi SQLite (dipakai test) menyimpan apa adanya - sehingga perbandingan `where('kolom', $tanggalPolos)` atau
  `whereBetween('kolom', [...])` bisa gagal cocok, terutama di tanggal 1 dan tanggal terakhir bulan.
- **Bug yang saya buat sendiri (regresi)**: `JournalController` filter "dari tanggal / sampai tanggal" sempat saya ubah dari `whereDate()` ke
  `where()` biasa pada audit paling awal - tanpa sadar ini SALAH ARAH (justru menghilangkan perlindungan yang sudah benar). Sudah dikembalikan.
- **Bug pada kode baru saya**: `PeriodService` (penutupan periode) memakai `where()`/`whereBetween()` di beberapa tempat, termasuk
  `FiscalYear::firstOrCreate()` dengan pencocokan tanggal persis - yang bisa membuat baris `fiscal_years` duplikat setiap kali menutup bulan
  berbeda di tahun yang sama. `FinancialReportService::totals()` dan `FixedAssetService` (cek duplikat depresiasi) punya masalah serupa.
- **Bug lama, ditemukan sekalian saat menyapu seluruh kode** (sudah ada sebelum saya audit, tidak pernah dilaporkan): `FinancialReportService::ledger()`
  (Buku Besar), `BankReconciliationService::transactions()` (daftar mutasi rekonsiliasi), dan `JournalExport` (ekspor Excel jurnal) semuanya memakai
  `whereBetween()` pada `journal_date` - berpotensi diam-diam **menghilangkan transaksi di tanggal terakhir** rentang yang dipilih dari Buku Besar,
  Rekonsiliasi Bank, dan file Excel yang diekspor.
- **Perbaikan**: semua titik di atas diganti memakai `whereDate()` (menerapkan fungsi SQL `DATE()`), yang benar di MySQL, PostgreSQL, maupun SQLite.
  `PeriodService::findOrCreateFiscalYear()` ditulis ulang agar tidak lagi mencocokkan tanggal secara persis.
- **Tes regresi ditambahkan**: `test_period_boundary_dates_are_handled_correctly` di `AccountingTest.php` mengunci jurnal tepat di tanggal 1 dan
  tanggal terakhir bulan, reopen, dan menutup dua bulan berbeda di tahun yang sama sambil memastikan `fiscal_years` tidak terduplikasi.
- **Bukan bug**: kegagalan pada `Tests\Feature\Auth\*`, `ProfileTest`, dan `ExampleTest` yang muncul di hasil `php artisan test` pengguna BUKAN
  bagian dari proyek yang saya kirim (dikonfirmasi: file-file itu tidak ada di zip manapun yang saya kirimkan). Itu bekas scaffolding Laravel
  Breeze standar (`User::factory()`, verifikasi email lewat link, halaman confirm-password, registrasi langsung login) dari percobaan setup
  sebelumnya di folder proyek pengguna, yang menguji alur berbeda dari desain MKP (OTP + persetujuan Manager). Langkah pembersihan ada di README.
