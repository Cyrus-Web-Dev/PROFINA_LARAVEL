# SDM HRIS — Sistem Informasi Manajemen Sumber Daya Manusia

Laravel 11 + Inertia.js (v1) + Vue 3 + Tailwind + **PostgreSQL**. Proyek ini **lengkap** (sudah termasuk `artisan`,
`public/`, `config/`, dll.) — **jangan** menyalinnya ke atas skeleton Laravel dan **jangan** jalankan `laravel new` /
`breeze:install`.

Kebutuhan: PHP 8.2+ (ekstensi `pdo_pgsql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `gd`, `zip`), Composer 2,
Node 18+/20+, **PostgreSQL 13+**.

## Modul yang tersedia

Karyawan (master data lengkap) · Departemen & Jabatan · Absensi (check-in/out + koreksi manual) · Cuti & Izin
(pengajuan + approval berjenjang) · Payroll (BPJS Kesehatan/Ketenagakerjaan + PPh 21, slip gaji PDF) · Penilaian
Kinerja/KPI · Rekrutmen (lowongan publik + pipeline pelamar internal) · Pelatihan & Pengembangan · Manajemen Dokumen
(dengan pengingat kedaluwarsa otomatis) · Pengumuman · Employee Self-Service Portal · Kelola Pengguna & Role (RBAC) ·
Backup Database · REST/Excel Export.

## Instalasi (sekali saja)

```bash
# 1. Buat database kosong di PostgreSQL
psql -U postgres -c "CREATE DATABASE sdm_hris;"

cp .env.example .env
composer install
php artisan key:generate
npm install
npm run build

# 2. Sesuaikan DB_USERNAME / DB_PASSWORD di .env bila perlu, lalu:
php artisan migrate --seed
```

> Gunakan `composer install` dan `npm install` **apa adanya**. Jangan `composer require inertiajs/inertia-laravel`
> atau `npm install @inertiajs/vue3` tanpa versi: versi terbaru tidak selalu sejajar (penyebab error
> `"undefined" is not valid JSON`). Versi yang benar sudah dikunci di `composer.json`/`package.json`.

### Membuat akun admin pertama

Sistem ini **tidak punya pendaftaran publik** — akun dibuat oleh Admin HR. Untuk akun pertama, pilih salah satu:

**Opsi A — otomatis saat seeding** (paling mudah): isi di `.env` **sebelum** menjalankan `migrate --seed`:
```
SDM_ADMIN_EMAIL=admin@perusahaan.com
SDM_ADMIN_PASSWORD=passwordRahasia123
```
Akun ini langsung aktif dengan role `super-admin`, tanpa perlu OTP untuk pertama kali (OTP tetap berlaku di login berikutnya).

**Opsi B — lewat terminal, kapan saja**:
```bash
php artisan sdm:user admin@perusahaan.com --name="Admin HR" --password=passwordRahasia123 --role=super-admin
```
Perintah yang sama juga dipakai untuk reset password darurat (`--password=...`) atau ubah role user yang sudah ada.

## Menjalankan

Mode paling stabil (satu terminal):
```bash
php artisan serve --host=127.0.0.1 --port=8000
```
lalu buka **http://127.0.0.1:8000** (persis alamat ini, sama dengan `APP_URL`). Setelah mengubah tampilan
(file `resources/`), jalankan lagi `npm run build`.

Mode pengembangan (hot reload) — dua terminal: `php artisan serve --host=127.0.0.1` dan `npm run dev`.
Kalau `npm run dev` dihentikan paksa, hapus file `public/hot` sebelum memakai `npm run build` lagi.

## Menyiapkan Gmail API (OTP login, notifikasi cuti/payroll/rekrutmen)

Semua email sistem dikirim lewat satu akun Gmail memakai Gmail API. Aplikasi membaca dua file di
`storage/app/credentials/`: `credentials.json` (identitas aplikasi Google) dan `token.json` (izin akun pengirim).

**Cara tercepat:** kalau proyek `mkp` atau `inventaris` di komputer yang sama sudah berhasil mengirim email, salin
kedua file itu dari `storage/app/credentials/` proyek tersebut ke `sdm-hris/storage/app/credentials/`. Selesai.

**Membuat dari awal:**
1. Buka [Google Cloud Console](https://console.cloud.google.com), buat/pilih project, lalu **APIs & Services >
   Library > Gmail API > Enable**.
2. **OAuth consent screen**: pilih *External*, isi nama aplikasi, tambahkan Gmail pengirim sebagai *Test user*.
3. **Credentials > Create credentials > OAuth client ID** bertipe *Web application*. Pada *Authorized redirect URIs*
   tambahkan `https://developers.google.com/oauthplayground`. Unduh JSON-nya dan simpan sebagai
   `storage/app/credentials/credentials.json`.
4. Buka [OAuth 2.0 Playground](https://developers.google.com/oauthplayground): klik ikon gerigi (kanan atas), centang
   **Use your own OAuth credentials**, isi **OAuth Client ID** & **Client secret** dari `credentials.json`
   (buka dengan Notepad: field `client_id` dan `client_secret`).
5. Di **Step 1**: kolom "Input your own scopes" isi persis `https://www.googleapis.com/auth/gmail.send`, klik
   **Authorize APIs**, login dengan akun Gmail pengirim, klik **Allow**.
6. Di **Step 2**: klik **Exchange authorization code for tokens**, salin **Refresh token** (diawali `1//`).
7. Buat `storage/app/credentials/token.json`:
   ```json
   { "refresh_token": "1//0gxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx", "scope": "https://www.googleapis.com/auth/gmail.send", "token_type": "Bearer" }
   ```
   (Alternatif tanpa file: isi `GMAIL_CLIENT_ID`, `GMAIL_CLIENT_SECRET`, `GMAIL_REFRESH_TOKEN` di `.env`.)
8. Uji dari terminal: `php artisan sdm:mail-test emailkamu@gmail.com` — menampilkan file yang terbaca dan **penyebab
   error yang sebenarnya** bila gagal.

### Membuat token tahan lama (tidak hangus dalam 7 hari)

Selama OAuth consent screen berstatus **Testing**, Google memaksa refresh token hangus dalam 7 hari. Supaya tahan
lama: **APIs & Services > OAuth consent screen > PUBLISH APP** (Testing → *In production*). Untuk scope `gmail.send`
saja, Google biasanya tidak mewajibkan verifikasi keamanan. Setelah itu, ulangi langkah 4-7 untuk membuat refresh
token baru (yang tidak akan hangus, kecuali dicabut manual, password Gmail diganti, atau tidak dipakai 6 bulan
berturut-turut). Aplikasi otomatis memperbarui access token setiap kali kedaluwarsa (~1 jam) — tidak ada langkah
manual tambahan.

**Belum ada Gmail sama sekali?** Selama `APP_ENV=local` dan `APP_DEBUG=true`, kode OTP ditulis ke
`storage/logs/laravel.log` (cari `OTP-LOKAL`). Mode ini otomatis mati di production.

## Peran & Hak Akses (RBAC)

| Role | Cakupan |
|---|---|
| `super-admin` | Semua modul + pengaturan sistem (backup, log aktivitas, dll). |
| `hr-admin` | Semua modul operasional HR untuk **seluruh** karyawan (tanpa pengaturan sistem). |
| `manajer` | Lihat/approve cuti & kinerja **HANYA timnya sendiri** (bawahan langsung + departemen yang dipimpin) — dibatasi otomatis lewat `App\Support\TeamScope`, TIDAK bisa melihat payroll/gaji atau dokumen pribadi siapa pun. |
| `karyawan` | Murni self-service: absen, cuti, slip gaji, penilaian, pelatihan miliknya sendiri. Tidak diberi izin `*.view` modul apa pun (mencegah membuka halaman admin). |

Kelola lewat menu **Kelola Pengguna** (approve/reject akun baru, ubah role, aktif/nonaktifkan) atau lewat terminal:
```bash
php artisan sdm:user email@karyawan.com --name="Nama" --password=Password123 --role=karyawan
```

Setiap karyawan yang perlu login sistem sendiri harus punya **akun** (tabel `users`) yang terhubung ke **data
karyawan** (tabel `employees`) — ini dua hal terpisah secara sengaja, supaya data kepegawaian tetap rapi walau
akun logic berubah (nonaktif, reset, dsb).

## Payroll: BPJS & PPh 21

Perhitungan otomatis ada di `app/Services/PayrollCalculator.php`, dengan semua persentase/batas diatur lewat
`.env` (lihat `config/hr.php`) — **bukan** hardcode, jadi bisa disesuaikan kalau pemerintah mengubah tarif tanpa
mengubah kode. PPh 21 dihitung dengan metode tarif progresif tahunan (disetahunkan/dibagi 12); untuk kepatuhan penuh
sesuai metode TER bulanan (PP 58/2023), sebaiknya diverifikasi dengan konsultan pajak — ada catatan pengingat ini
juga di slip gaji PDF yang dihasilkan.

Alur kerja: **Payroll > Periode Baru** → **Hitung Payroll** (otomatis untuk semua karyawan aktif, bisa dikoreksi
manual per orang untuk tunjangan/lembur/potongan lain) → **Tandai Lunas** (mengunci data + mengirim email + slip
gaji PDF bisa diunduh karyawan dari menu **Slip Gaji Saya**).

## Backup & restore

Menu **Backup Data** (khusus `super-admin`/pemegang izin `settings.manage`): membuat backup ke
`storage/app/private/backups/`, otomatis pakai `pg_dump` bila terdeteksi di sistem (mode "Lengkap"), atau fallback
murni PHP (mode "Data saja") bila tidak ada — tetap bisa dipulihkan ke database kosong yang sudah dimigrasikan
(`php artisan migrate`). Backup terjadwal otomatis tiap hari jam 02:00 WIB (lihat `routes/console.php`) — aktifkan
dengan menjalankan scheduler Laravel di cron server:
```
* * * * * cd /path/ke/sdm-hris && php artisan schedule:run >> /dev/null 2>&1
```
Baris cron yang sama juga menjalankan pengingat dokumen kedaluwarsa harian.

## REST API (mobile / integrasi eksternal)

Selain aplikasi web, tersedia REST API berbasis token (Laravel Sanctum) di `/api/v1/*` — **terpisah** dari sesi
login web (yang memakai OTP email 2 langkah). Cocok untuk aplikasi mobile karyawan atau integrasi sistem lain.

```bash
# 1. Login, dapatkan token
curl -X POST http://127.0.0.1:8000/api/v1/login \
  -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"karyawan@perusahaan.com","password":"rahasia","device_name":"iPhone Budi"}'
# -> { "token": "1|xxxxxxxxxxxxxxxxxxxx", "user": {...} }

# 2. Pakai token di setiap request berikutnya
curl http://127.0.0.1:8000/api/v1/me \
  -H "Accept: application/json" -H "Authorization: Bearer 1|xxxxxxxxxxxxxxxxxxxx"
```

Endpoint yang tersedia: `POST /login`, `POST /logout`, `POST /logout-all`, `GET /me`, `GET /employees`,
`GET /employees/{id}`, `POST /attendances/check-in`, `POST /attendances/check-out`, `GET /attendances/history`,
`GET /leaves`, `GET /leaves/balances`, `POST /leaves`, `POST /leaves/{id}/cancel`, `GET /payroll/my-payslips`,
`GET /announcements`.
Semua endpoint (kecuali `/login`) butuh header `Authorization: Bearer <token>`. Login dibatasi 5 percobaan/menit
per IP; endpoint lain 60 request/menit per pengguna. Kelola token aktif lewat `$user->tokens` (belum ada UI khusus
di aplikasi — bisa ditambahkan di menu Profil bila diperlukan).

## Fitur baru (audit & pengembangan lanjutan)

### Pendaftaran mandiri, lupa password, reset password
Sudah lengkap dan aktif secara default:
- **Daftar** (`/register`): nama + email + password → kode verifikasi ke email → akun **pending** menunggu
  persetujuan Admin HR (kecuali pendaftar **pertama**, otomatis jadi `super-admin` aktif). Saat menyetujui,
  Admin HR bisa langsung menghubungkan akun ke data karyawan yang sesuai (dibantu pencocokan otomatis lewat
  NIP yang diisi pendaftar) dan memilih role.
- **Lupa Password** (`/forgot-password`) & **Reset Password**: memakai mekanisme bawaan Laravel (`Password`
  broker), token sekali pakai kedaluwarsa 60 menit, email dikirim lewat Gmail API. Semua token API (mobile)
  milik user otomatis dicabut setiap kali password diganti, di jalur manapun (reset, ganti password sendiri).
- Matikan pendaftaran mandiri kapan saja: `SDM_REGISTRATION_ENABLED=false` di `.env`.
- Form registrasi, lupa password, dan lamar kerja publik didukung **Cloudflare Turnstile** (anti-bot, opsional,
  gratis) — isi `TURNSTILE_SITE_KEY` & `TURNSTILE_SECRET_KEY` untuk mengaktifkan (lihat `.env.example` untuk
  key uji coba resmi Cloudflare yang selalu lolos, dipakai saat development).

### Keamanan tambahan (permintaan: "seminimal mungkin keamanan bawaan Laravel diaktifkan")
- **Hashing password: argon2id** (bukan bcrypt) — lihat bagian tersendiri di bawah.
- **Header keamanan HTTP** aktif di semua response: `X-Frame-Options`, `X-Content-Type-Options`,
  `Referrer-Policy`, `Permissions-Policy`, `Strict-Transport-Security` (otomatis saat HTTPS).
- **Content-Security-Policy** opsional bertahap: `SDM_CSP=off` (default) → `report-only` (dicatat browser,
  tidak memblokir) → `enforce`. Mulai dari `report-only`, pantau Console, baru pindah ke `enforce`.
- **Paksa HTTPS** di production: `SDM_FORCE_HTTPS=true` (otomatis tidak aktif di `APP_ENV=local`).
- **Enkripsi kolom sensitif**: NPWP, nomor BPJS Kesehatan/Ketenagakerjaan, dan nomor rekening karyawan
  dienkripsi AES-256 di database (transparan lewat Eloquent, otomatis terbaca normal di aplikasi). Kalau
  file database bocor, data ini tidak terbaca tanpa `APP_KEY` — **backup `APP_KEY` di tempat terpisah dari
  backup database**, kalau hilang data ini tidak bisa didekripsi lagi.
- **RBAC diperbaiki & diperketat**: sebelumnya role `karyawan` diberi izin `*.view` di banyak modul secara
  tidak sengaja, yang berarti karyawan biasa bisa membuka halaman ADMIN berisi absensi/cuti/dokumen/penilaian
  **seluruh** karyawan (bukan cuma dirinya) — ini kebocoran privasi. Sudah diperbaiki: `karyawan` sekarang
  murni self-service (tidak ada izin `*.view` modul apa pun), dan role `manajer` dibatasi hanya melihat/
  menyetujui **timnya sendiri** (lewat `App\Support\TeamScope` — cek `supervisor_id` & departemen yang
  dipimpinnya), tidak bisa melihat payroll atau dokumen pribadi karyawan.
- **Anti eskalasi hak akses**: pengguna tidak bisa mengubah role akun sendiri; hanya `super-admin` yang boleh
  memberi/mencabut role `super-admin`; role `super-admin` aktif terakhir tidak bisa dicabut/dinonaktifkan.
- **Data finansial disembunyikan dari respons JSON** (bukan cuma disembunyikan di tampilan) untuk siapa pun
  yang tidak punya izin `employees.manage` — mencegah kebocoran lewat DevTools/Network tab.
- **Wajib ganti password sementara**: akun yang dibuatkan Admin HR (atau hasil impor data lama) tidak bisa
  membuka halaman lain sampai menggantinya (`must_change_password`).
- Rate limiting ditambah di: pendaftaran (10/menit), verifikasi OTP (10/menit + kirim ulang 5/menit),
  lupa/reset password (6/menit), lamar kerja (5/jam).

### Hashing password: argon2id
Driver default diganti dari bcrypt ke **argon2id** (`config/hashing.php`, `HASH_DRIVER` di `.env`) — pemenang
Password Hashing Competition, direkomendasikan OWASP sebagai pilihan pertama karena *memory-hard* (jauh lebih
tahan akselerasi GPU/ASIC/FPGA dibanding bcrypt yang murni CPU-time). Parameter: memory 37 MiB, time cost 3,
1 thread (di atas minimum rekomendasi OWASP untuk margin keamanan ekstra) — bisa disesuaikan lewat
`ARGON_MEMORY`/`ARGON_TIME`/`ARGON_THREADS`.

Butuh PHP yang dikompilasi dengan dukungan Argon2 (umum di PHP 7.2+ modern, termasuk XAMPP versi terbaru).
Cek dengan `php -r "var_dump(defined('PASSWORD_ARGON2ID'));"` — harus `bool(true)`. Bila `false`, set
`HASH_DRIVER=bcrypt` di `.env` sebagai fallback.

**Rehash otomatis & transparan**: kalau ada password lama berformat bcrypt (mis. dari data hasil migrasi
`sdm:import-legacy`, atau kalau kamu pernah pakai `HASH_DRIVER=bcrypt` sebelumnya), begitu user tsb **login
dan passwordnya benar**, sistem otomatis menghash-ulang ke argon2id dengan parameter terbaru — tanpa user
sadar, tanpa perlu migrasi manual. Laravel mendeteksi algoritme dari format hash itu sendiri, jadi hash lama
& baru bisa hidup berdampingan di kolom yang sama selama masa transisi.

### Hari libur nasional & cuti bersama
Kalender otomatis dari dataset publik (menu **Hari Libur**), dipakai untuk menghitung **hari kerja** cuti
(akhir pekan & libur tidak mengurangi jatah). Tahan gangguan: hasil di-cache 12 jam, kalau sumber online mati
otomatis pakai cache terakhir lalu file cadangan bawaan (`database/data/holidays-id.json`) — fitur cuti
**tidak pernah error** walau internet server bermasalah. Admin HR bisa menambah libur khusus perusahaan atau
menandai tanggal tertentu **tetap** hari kerja (membatalkan libur nasional untuk kasus tertentu).
⚠️ Ini dataset publik pihak ketiga, **bukan** API resmi pemerintah — selalu cocokkan dengan SKB 3 Menteri
terbaru, terutama untuk tanggal yang ditandai "belum pasti" (Idul Fitri/Idul Adha, dst mengikuti hasil sidang
isbat).

### Saldo cuti & validasi pengajuan yang lebih ketat
Halaman **Cuti Saya** sekarang menampilkan sisa jatah per jenis cuti secara visual. Pengajuan divalidasi lebih
ketat lewat `LeaveService` (dipakai bersama oleh web & API, jadi konsisten): tidak boleh tumpang tindih dengan
pengajuan lain yang masih aktif, tidak boleh melebihi sisa jatah tahun berjalan, jenis cuti yang butuh
lampiran (mis. sakit) wajib diisi, maksimal 60 hari per pengajuan, pengajuan surut maksimal 30 hari ke
belakang.

### Geofencing absensi (opsional)
Isi `SDM_OFFICE_LAT`/`SDM_OFFICE_LNG`/`SDM_OFFICE_RADIUS_M` untuk mengaktifkan. `SDM_GEOFENCE_ENFORCE=false`
(default): check-in di luar radius tetap diterima tapi dicatat di keterangan. `=true`: ditolak. Dihitung
sendiri (rumus Haversine, tanpa API eksternal). Catatan jujur: koordinat berasal dari GPS HP/browser pengguna
sehingga bisa dipalsukan (GPS spoofing) — fitur ini pencegah & bukti pendukung, bukan pengaman mutlak.

### Notifikasi WhatsApp & Telegram (opsional)
Tambahan di luar email: peringatan ke HR saat ada pengajuan cuti baru/pendaftar baru menunggu persetujuan, dan
notifikasi ke karyawan saat cuti disetujui/ditolak. WhatsApp lewat gateway pihak ketiga **Fonnte**
(`FONNTE_TOKEN`, bukan WhatsApp Cloud API resmi Meta) dan/atau bot **Telegram** resmi (gratis,
`TELEGRAM_BOT_TOKEN`). Kegagalan kirim tidak pernah menggagalkan aksi utama (cuti tetap tersimpan walau
notifikasi gagal terkirim).

### Dashboard personal untuk karyawan
Karyawan biasa (tanpa izin admin) sekarang melihat dashboard **personal** (sapaan, tombol check-in/out
langsung, saldo cuti, slip gaji terakhir, libur mendatang, pengumuman) — bukan lagi statistik seluruh
perusahaan yang sebelumnya salah tampil ke semua orang akibat bug RBAC di atas.

### Log Aktivitas (audit trail)
Menu **Log Aktivitas** (khusus `super-admin`) menampilkan riwayat semua aksi penting di sistem (siapa,
kapan, aksi apa, dari IP mana) — bisa difilter per pengguna/aksi/tanggal. Infrastrukturnya sudah ada sejak
awal (`activity_logs`), sekarang punya antarmuka.

## Referensi perintah Artisan kustom

| Perintah | Kegunaan |
|---|---|
| `php artisan sdm:user email@x.com --name="Nama" --password=... --role=hr-admin` | Buat/ubah akun tanpa lewat UI (bikin admin awal, reset password darurat, ubah role). |
| `php artisan sdm:mail-test email@tujuan.com` | Uji konfigurasi Gmail API, tampilkan penyebab error sebenarnya bila gagal. |
| `php artisan sdm:backup-database` | Buat backup database sekarang (juga berjalan otomatis tiap hari jam 02:00 lewat scheduler). |
| `php artisan sdm:check-expiring-documents` | Kirim email pengingat dokumen yang akan kedaluwarsa (juga otomatis tiap hari jam 07:00). |
| `php artisan sdm:import-legacy --dry-run` | Pratinjau migrasi data dari aplikasi PHP native lama tanpa menyimpan apa pun. |
| `php artisan sdm:import-legacy` | Jalankan migrasi data lama sungguhan (aman diulang, berbasis NIP). |

## Migrasi data dari aplikasi PHP native lama

Kalau kamu punya data lama di MySQL (tabel `users`/`karyawan`/dll dari versi PHP native sebelumnya), isi kredensial
`LEGACY_DB_*` di `.env`, lalu jalankan:
```bash
php artisan sdm:import-legacy
```
Lihat `app/Console/Commands/ImportLegacyData.php` untuk detail pemetaan kolom — sesuaikan dulu bila struktur tabel
lamamu berbeda dari contoh.

## Keamanan yang diterapkan

**Bawaan Laravel (diaktifkan penuh, bukan dilewati):**
- Eloquent/Query Builder di semua akses database (parameter binding) — tidak ada celah SQL Injection.
- CSRF protection di semua form (token dikirim otomatis oleh Inertia); sesi 419/kedaluwarsa ditangani dengan
  pesan ramah, bukan crash.
- Escaping otomatis di semua output Vue (`{{ }}`) — tidak ada celah Stored XSS.
- Validasi request lengkap di setiap endpoint (`Illuminate\Validation`), termasuk file upload (tipe, ukuran,
  dimensi).
- Mass assignment protection: setiap model pakai `$fillable` eksplisit, tidak ada `$guarded = []`.
- `Password::defaults()` global: minimal 8 karakter, campuran huruf besar/kecil, ada angka, dicek terhadap
  daftar password bocor (Have I Been Pwned via k-anonymity, tidak mengirim password asli).
- Session: `http_only` (JS tidak bisa baca cookie sesi), `same_site=lax`, `secure` wajib `true` di production
  (lihat `.env.example`).
- Rate limiting (`RateLimiter`/`throttle`) di semua endpoint publik/sensitif: login, 2FA, registrasi, lupa
  password, lamar kerja.
- Header keamanan HTTP (`SecurityHeaders` middleware), opsi CSP & paksa-HTTPS untuk production.

**Ditambahkan khusus untuk aplikasi ini:**
- Hashing password **argon2id** dengan rehash otomatis transparan (lihat bagian tersendiri di atas).
- Login 2 tahap: password + kode OTP email (kedaluwarsa 10 menit, maks. 5 percobaan, timing-safe terhadap
  user enumeration, rate-limited per email+IP).
- Enkripsi AES-256 untuk NPWP/BPJS/nomor rekening di database.
- RBAC granular per modul (Spatie Permission) + pembatasan tim untuk manajer (`TeamScope`) + anti eskalasi
  hak akses di Kelola Pengguna.
- Data finansial disembunyikan dari payload JSON (bukan cuma UI) untuk role yang tidak berhak.
- Audit log semua aksi penting (`activity_logs`, ada UI-nya di menu Log Aktivitas).
- File (foto, dokumen, resume, backup) disimpan di `storage/app/private`, disajikan lewat aplikasi dengan
  pengecekan kepemilikan/izin per request — bukan folder publik yang bisa ditebak URL-nya.
- Semua token API (Sanctum) otomatis dicabut saat password berganti atau akun dinonaktifkan.
- Anti path-traversal di fitur backup (nama file diverifikasi dengan pola baku sebelum dipakai).

## Struktur proyek

Backend: `app/Http/Controllers` (13 controller modul), `app/Models` (18 model), `app/Services` (Gmail API, OTP,

Payroll Calculator, Activity Logger, Backup, Notification Mailer), `database/migrations` (21 file, skema PostgreSQL
lengkap). Frontend: `resources/js/Pages` (29 halaman Vue, per-modul), `resources/js/Layouts` (AppLayout dengan
sidebar/topbar, GuestLayout untuk halaman auth), desain "personnel ledger" (ink navy + kertas + aksen emas, font
Fraunces + IBM Plex) — lihat `tailwind.config.js` untuk token desainnya.
