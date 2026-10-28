# MKP — Manajemen Keuangan Perusahaan

Laravel 11 + Inertia.js (v1) + Vue 3 + Tailwind. Proyek ini **lengkap** (sudah termasuk `artisan`, `public/`, `config/`, dll.) —
**jangan** menyalinnya ke atas skeleton Laravel dan **jangan** jalankan `laravel new` / `breeze:install`.

Kebutuhan: PHP 8.2+ (ekstensi `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo`, `gd`, `zip`), Composer 2, Node 18+ / 20+, MySQL/MariaDB.

## Instalasi (sekali saja)

```bat
:: 1. Buat database kosong "mkp_db" (phpMyAdmin), collation utf8mb4_unicode_ci

copy .env.example .env
composer install
php artisan key:generate
npm install
npm run build

:: 2. Sesuaikan DB_USERNAME / DB_PASSWORD di .env bila perlu, lalu:
php artisan migrate --seed
```

Sudah pernah menginstal versi sebelumnya? Jalankan `php artisan migrate` lagi untuk menambah kolom profil pengguna.

> Gunakan `composer install` dan `npm install` **apa adanya**. Jangan `composer require inertiajs/inertia-laravel`
> atau `npm install @inertiajs/vue3` tanpa versi: versi terbaru tidak sejajar (penyebab error `"undefined" is not valid JSON`).
> Versi yang benar: `inertiajs/inertia-laravel` 1.x dan `@inertiajs/vue3` 1.x.

## Menjalankan

Mode paling stabil (satu terminal):

```bat
php artisan serve --host=127.0.0.1 --port=8000
```

lalu buka **http://127.0.0.1:8000** (persis alamat ini, sama dengan `APP_URL`).
Setelah mengubah tampilan (file `resources/`), jalankan lagi `npm run build`.

Mode pengembangan (hot reload) — dua terminal: `php artisan serve --host=127.0.0.1` dan `npm run dev`.
Kalau `npm run dev` dihentikan paksa, hapus file `public/hot` sebelum memakai `npm run build` lagi.

## Menyiapkan Gmail API (kode OTP & reset password)

Semua email sistem dikirim lewat satu akun Gmail memakai Gmail API. Aplikasi membaca dua file di `storage/app/credentials/`:
`credentials.json` (identitas aplikasi Google) dan `token.json` (izin akun pengirim). Tidak ada halaman pengaturan di dalam aplikasi.

**Cara tercepat:** kalau proyek Inventaris sudah berhasil mengirim email, salin kedua file itu dari
`inventaris/storage/app/credentials/` ke `mkp/storage/app/credentials/`. Selesai.

**Membuat dari awal** (nama menu di Google bisa sedikit berbeda dari waktu ke waktu):
1. Buka [Google Cloud Console](https://console.cloud.google.com), buat/pilih project, lalu **APIs & Services > Library > Gmail API > Enable**.
2. **OAuth consent screen**: pilih *External*, isi nama aplikasi, tambahkan Gmail pengirim sebagai *Test user*.
3. **Credentials > Create credentials > OAuth client ID** bertipe *Web application*. Pada *Authorized redirect URIs* tambahkan
   `https://developers.google.com/oauthplayground`. Unduh JSON-nya dan simpan sebagai `storage/app/credentials/credentials.json`.
4. Buka [OAuth 2.0 Playground](https://developers.google.com/oauthplayground): klik ikon gerigi (kanan atas) dan centang
   **Use your own OAuth credentials**, lalu isi **OAuth Client ID** & **OAuth Client secret** — nilainya ada di `credentials.json` yang sudah kamu punya
   (buka file itu dengan Notepad: field `client_id` dan `client_secret`).
5. Di **Step 1 Select & authorize APIs**: pada kolom "Input your own scopes" ketik persis `https://www.googleapis.com/auth/gmail.send`, klik **Authorize APIs**,
   lalu login dengan akun Gmail yang ingin dipakai mengirim email dan klik **Allow**.
6. Di **Step 2 Exchange authorization code for tokens**: klik **Exchange authorization code for tokens**. Setelah muncul hasilnya, salin nilai **Refresh token**
   (barisnya diawali `1//`).
7. Buat `storage/app/credentials/token.json` berisi:
   ```json
   { "refresh_token": "1//0gxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx", "scope": "https://www.googleapis.com/auth/gmail.send", "token_type": "Bearer" }
   ```
   (Alternatif tanpa file: isi `GMAIL_CLIENT_ID`, `GMAIL_CLIENT_SECRET`, `GMAIL_REFRESH_TOKEN` di `.env` — nilai `GMAIL_REFRESH_TOKEN` = refresh token dari langkah 6.)
8. Uji dari terminal: `php artisan mkp:mail-test emailkamu@gmail.com`. Perintah ini menampilkan file yang terbaca dan **penyebab error yang sebenarnya** bila gagal.

### Membuat token tahan lama (tidak hangus dalam hitungan hari)

Refresh token dari langkah 6 dipakai berulang oleh aplikasi untuk meminta *access token* baru secara **otomatis** setiap kali mengirim email —
kamu tidak perlu membuatnya ulang setiap kali. **Masalahnya cuma satu:** selama OAuth consent screen di Google Cloud masih berstatus **Testing**,
Google MEMAKSA refresh token itu hangus dalam **7 hari**, siapa pun cara pembuatannya. Supaya tahan lama (bertahun-tahun, sampai kamu cabut sendiri):

1. Di Google Cloud Console, buka **APIs & Services > OAuth consent screen**.
2. Klik **PUBLISH APP** untuk mengubah status dari *Testing* ke **In production**.
   - Untuk scope `gmail.send` saja (bukan baca inbox), Google biasanya **tidak mewajibkan proses verifikasi keamanan** — cukup klik publish, akan muncul dialog konfirmasi, klik **Confirm**.
   - Kalaupun muncul peringatan "unverified app" saat login di langkah 5, itu wajar untuk aplikasi internal; klik **Advanced > Go to (nama app) (unsafe)** lalu **Allow**. Ini aman karena aplikasinya kamu buat sendiri.
3. Setelah App berstatus **In production**, ulangi langkah 4-7 di atas untuk membuat refresh token yang **baru** (yang lama dibuat saat masih Testing, jadi tetap ikut hangus 7 hari).
4. Refresh token baru ini tidak punya batas waktu, dan hanya berhenti berfungsi bila: kamu mencabut aksesnya di
   [myaccount.google.com/permissions](https://myaccount.google.com/permissions), kamu ganti password akun Gmail-nya, atau **token tidak dipakai sama sekali selama 6 bulan berturut-turut**
   (login OTP MKP otomatis memakainya, jadi selama aplikasi dipakai secara wajar ini tidak akan terjadi).

Aplikasi **sudah otomatis memperbarui access token** setiap kali kedaluwarsa (sekitar tiap 1 jam) menggunakan refresh token ini — tidak ada langkah manual tambahan.
Yang membuatnya berhenti bekerja hanya empat hal di atas. Jika suatu saat berhenti bekerja, error `invalid_grant` akan muncul dan `php artisan mkp:mail-test`
menjelaskan penyebabnya.

**Belum ada Gmail sama sekali?** Selama `APP_ENV=local` dan `APP_DEBUG=true`, kode OTP ditulis ke
`storage/logs/laravel.log` (cari `OTP-LOKAL`). Mode ini otomatis mati di production.

## Profil pengguna & logo

- Klik foto/nama di pojok kiri bawah untuk membuka **Profil Saya**: nama, username, email, telepon, jabatan, divisi, NIP, tanggal lahir,
  alamat, bio, riwayat pendidikan, foto, dan ganti password. Mengganti email meminta password saat ini.
- Foto profil dan logo perusahaan bisa **di-zoom dan digeser** sebelum disimpan; klik gambar untuk memperbesar (penampil dengan zoom).
- Berkas disimpan di `storage/app/private` dan disajikan lewat aplikasi, jadi **tidak perlu** `php artisan storage:link`.
- Logo perusahaan (menu Profil Perusahaan, admin) tampil di kop PDF Invoice.

## Akun, role, dan persetujuan Manager

- **Pendaftar pertama** (saat belum ada user) otomatis menjadi **Manager** (role internal `admin`).
- Pendaftar berikutnya mendaftar sendiri di halaman login, memverifikasi email lewat kode OTP, lalu berstatus **menunggu persetujuan**.
  Selama menunggu, mereka tidak bisa masuk dan tidak dikirimi OTP login; halaman login menjelaskan statusnya.
- Manager melihat lencana di menu **Kelola Pengguna** dan di ikon lonceng, dan menerima email tiap ada pendaftar baru.
  Di halaman itu Manager bisa: **menyetujui** (memilih role, posisi, hak akses, dan menulis pesan yang ikut terkirim ke email pendaftar),
  **menolak** (dengan alasan, dikirim ke email), **mengubah akses**, **menonaktifkan/mengaktifkan** akun (langsung berlaku, termasuk yang sedang login),
  dan **mengirim pesan** ke email siapa pun yang terdaftar. Semua tercatat di Activity Log.
- Role: **Manager** (semua fitur), **Staff** (hak akses diatur per orang), **Viewer** (hanya melihat).
  Posisi Staff: Accounting, Finance, Pajak, Payroll, Lainnya. Tiap posisi punya preset hak akses yang bisa disesuaikan (lihat `config/mkp.php`).
- Manager terakhir yang aktif tidak bisa dinonaktifkan atau diturunkan rolenya.
- Menutup pendaftaran total: `MKP_REGISTRATION=closed` di `.env` (lalu `php artisan config:clear`).
- Tanpa antarmuka (darurat): `php artisan mkp:user email@perusahaan.com --name="Nama" --password=Rahasia123 --role=admin`
  (`--role=staff --position=finance` untuk staf). Lupa password saat email belum berfungsi: `php artisan mkp:user email --password=PasswordBaru123 --role=admin`.

**Sudah pernah memakai versi sebelumnya?** Jalankan `php artisan migrate` lalu `php artisan db:seed --class=RolePermissionSeeder`.
Perintah kedua mengubah role lama `accountant` menjadi `staff` dan menyalin hak aksesnya ke tiap orang, jadi tidak ada yang kehilangan akses.
Semua akun lama otomatis berstatus aktif.

## Backup & restore

Menu **Backup Data** (Manager) membuat file `.sql` di `storage/app/private/backups/`; yang terbaru disimpan sesuai `MKP_BACKUP_KEEP` (default 10).
- **Lengkap** (`..._full.sql`): memakai `mysqldump` / `pg_dump` bila ditemukan (XAMPP ditemukan otomatis di `C:\xampp\mysql\bin`; bila tidak, isi `MKP_MYSQLDUMP_PATH` di `.env`).
- **Data saja** (`..._data.sql`): dibuat lewat PHP bila program di atas tidak ada. Bekerja untuk MySQL dan PostgreSQL.
- Dari terminal / dijadwalkan: `php artisan mkp:backup`. Di Windows: Task Scheduler > Create Basic Task > Daily > program `php`, argumen `artisan mkp:backup`, "Start in" folder proyek.

**Restore (sengaja tidak dibuat di web):**
- File *Lengkap*: buat database kosong lalu impor. MySQL: phpMyAdmin > Import, atau `mysql -u root -p mkp_db < file.sql`. PostgreSQL: `psql -U postgres -d mkp_db -f file.sql`.
- File *Data saja*: `php artisan migrate:fresh` (mengosongkan database target!), lalu impor file itu dengan cara yang sama.
- Setelah restore: `php artisan optimize:clear`.
- Salin juga folder `storage/app/private` (foto profil & logo) dan simpan salinan backup di tempat lain (flashdisk/cloud).

## Periode akuntansi, lampiran, dan ekspor Excel

- **Periode Akuntansi** (menu Manager): tutup bulan setelah laporannya final. Jurnal bertanggal di bulan tertutup tidak bisa dibuat, diubah,
  diposting, atau di-void — termasuk lewat invoice/bill/pembayaran/aset tetap. Hanya bisa ditutup bila tidak ada jurnal draft di bulan itu.
  Membuka kembali wajib disertai alasan dan tercatat di Activity Log.
- **Lampiran bukti transaksi**: di halaman detail Jurnal, Invoice, dan Bill ada panel untuk mengunggah foto nota / PDF (maks 5 MB, foto besar
  dikecilkan otomatis di browser sebelum diunggah). Berkas hanya bisa dibuka lewat aplikasi (harus login), bukan link publik.
- **Ekspor Excel**: tombol "Unduh Excel" di halaman Neraca Saldo, Laba Rugi, dan Neraca — di samping tombol PDF yang sudah ada.

## Menjalankan tes otomatis

```bat
composer install
php artisan test
```

Tes memakai database SQLite sementara di memori (bukan `mkp_db`), jadi aman dijalankan kapan pun tanpa menyentuh data asli, dan tidak mengirim
email sungguhan. Mencakup: jurnal harus seimbang, Neraca tetap seimbang saat ada akun kontra (mis. Akumulasi Penyusutan), jurnal otomatis tidak
bisa di-void langsung, periode tertutup mengunci jurnal (termasuk kasus batas tanggal 1 dan tanggal terakhir bulan), alur pendaftaran dan
persetujuan Manager, hak akses per role/posisi, backup, dan lampiran. Jalankan `php artisan test` setelah mengubah kode di `app/` untuk memastikan
tidak ada yang rusak.

**Kalau folder `tests/` proyekmu sudah ada isinya SEBELUM menyalin file ini** (mis. dari percobaan `laravel new` / Breeze sebelumnya), kamu mungkin
melihat test tambahan seperti `Tests\Feature\Auth\*`, `ProfileTest`, atau `ExampleTest` yang GAGAL. File-file itu bukan bagian dari proyek ini -
mereka menguji alur bawaan Breeze (verifikasi email lewat link, konfirmasi ulang password, registrasi langsung login tanpa OTP) yang berbeda dari
desain MKP (OTP, persetujuan Manager). Aman dihapus:
```bat
rmdir /s /q tests\Feature\Auth
del tests\Feature\ProfileTest.php
del tests\Feature\ExampleTest.php
del tests\Unit\ExampleTest.php
```
Setelah dihapus, `php artisan test` seharusnya hanya menjalankan test milik MKP sendiri dan semuanya lolos.

## Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| Layar putih + `"undefined" is not valid JSON` | Versi Inertia PHP & JS tidak sejajar. `composer show inertiajs/inertia-laravel` dan `npm ls @inertiajs/vue3` harus sama-sama 1.x. Lalu `composer install`, `npm install`, `php artisan optimize:clear`, Ctrl+F5. |
| Layar putih, konsol: gagal memuat `127.0.0.1:5173` / `[::1]:5173` | Ada `public/hot` tetapi `npm run dev` tidak berjalan. Hapus `public/hot` dan `npm run build`. |
| Halaman polos tanpa gaya | `npm run build` belum dijalankan / `postcss.config.js` hilang. |
| `Class "..." not found` / `Route ... not defined` | `composer dump-autoload` lalu `php artisan optimize:clear`. |
| `SQLSTATE ... Connection refused` | MySQL belum menyala atau `DB_*` di `.env` salah. |
| `ext-gd` / `ext-zip` saat `composer install` | Aktifkan `extension=gd` dan `extension=zip` di `php.ini`, restart Apache/terminal. |
| `cURL error 60` saat kirim email | Sertifikat CA PHP hilang. Unduh `cacert.pem`, isi `curl.cainfo` di `php.ini` atau `GMAIL_CA_BUNDLE` di `.env`. |
| Kode OTP tidak sampai | Jalankan `php artisan mkp:mail-test emailkamu@gmail.com`; pesan errornya menyebut penyebab tepatnya. Detail: `storage/logs/laravel.log`. |
| Error 419 / sesi habis | Muat ulang halaman. Jangan membuka `localhost` dan `127.0.0.1` bergantian. Dua aplikasi Laravel di `127.0.0.1` berbagi cookie — hapus cookie situs bila aneh. |
| Setelah mengubah `.env` tidak berpengaruh | `php artisan config:clear`. Jangan pakai `config:cache` saat pengembangan. |

Jangan pernah membagikan `.env`, `credentials.json`, `token.json`. Ubah `APP_DEBUG=false` dan `APP_ENV=production` bila dipublikasikan.

Catatan: bila sebelumnya sudah menjalankan migrasi lama, tabel `mail_accounts` yang kosong boleh dibiarkan atau dihapus manual (`DROP TABLE mail_accounts`).

Dokumentasi fitur: `PANDUAN-FITUR.txt`. Laporan audit & daftar perbaikan: `AUDIT-MKP.md`.


---

## Modul Dana Pengadaan (terhubung ke PROCURA)

Menu **Dana Pengadaan**: menerima pengajuan dana dari sistem Pengadaan, memutuskan (setujui/tolak/revisi), mencairkan dana (jurnal Uang Muka Pengadaan / Kas),
menerima LPJ dan menjurnalnya, serta buku laporan Pengadaan ⇄ Keuangan. Setelah menimpa file: `php artisan migrate`, `php artisan db:seed --class=RolePermissionSeeder`,
isi `PROCURA_*` di `.env`, `npm run build`. Panduan lengkap: **docs/INTEGRASI-PROCURA.md**.
