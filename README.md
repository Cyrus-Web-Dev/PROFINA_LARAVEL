# PROFINA — Production · Marketing · Finance

**1 pintu masuk, 5 pintu tugas.** Semua orang login di satu tempat (Portal/Core). Setelah login, akun
hanya diarahkan ke modul yang ditugaskan kepadanya. Akun dengan role Marketing tidak pernah melihat,
apalagi bisa masuk, ke modul SDM/Keuangan, dan sebaliknya.

| Pintu | Folder | Port dev | DB | Asal |
|---|---|---|---|---|
| **Inti** — portal login, RBAC, pemantau semua modul | `core/` | 8000 | PostgreSQL `profina_core` | baru (kerangka auth dari SDM) |
| **MKP** — keuangan & akuntansi | `modules/mkp/` | 8001 | MySQL (seperti aslinya) | dari erp.zip |
| **SDM / HRIS** | `modules/sdm/` | 8002 | PostgreSQL | dari erp.zip |
| **Payroll & Budgeting** (PAYBRIDGE) | `modules/payroll/` | 8003 | PostgreSQL | dari erp.zip |
| **Marketing & Sales** | `modules/marketing/` | 8004 | PostgreSQL `profina_marketing` | baru |

`packages/profina-sso/` = paket klien SSO yang dipasang di keempat modul (token, middleware sesi, health check).

Modul Inventaris dan Procura dari erp.zip **tidak** ikut (sesuai permintaan: hanya SDM, Payroll & Budgeting, MKP).

# Menjalankan PROFINA (development)

Prasyarat: PHP 8.2+ (ext pdo_pgsql, pdo_mysql untuk MKP, mbstring, gd), Composer, Node 18+, PostgreSQL.

## 0. Alamat
Pakai subdomain `*.localhost` (Chrome/Edge/Firefox otomatis mengarahkannya ke 127.0.0.1) supaya cookie sesi
tiap aplikasi terpisah: `core.localhost:8000`, `mkp.localhost:8001`, `sdm.localhost:8002`,
`payroll.localhost:8003`, `marketing.localhost:8004`. Bila browsermu tidak mendukung, isi `hosts` manual.

## 1. Core
```bash
cd core
cp .env.example .env            # isi DB_*, PROFINA_ADMIN_EMAIL / PROFINA_ADMIN_PASSWORD, Gmail API (OTP)
composer install && npm install && npm run build
php artisan key:generate
php artisan migrate --seed      # membuat role Portal + admin awal
```
## 2. Hubungkan semua modul (sekali saja)
```bash
php artisan profina:provision   # buat PROFINA_MASTER_KEY & isi PROFINA_* + APP_URL + SESSION_COOKIE di modules/*/.env
```
(salin `.env.example` → `.env` tiap modul dilakukan otomatis bila `.env` belum ada).

## 3. Tiap modul (sdm, payroll, mkp, marketing)
```bash
cd modules/<kode>
# buka .env: isi DB_* (MKP = MySQL, lainnya PostgreSQL), Gmail/integrasi bila perlu
composer install && npm install && npm run build
php artisan key:generate
php artisan migrate --seed       # seeder membuat role yang sama dengan katalog Core
```
Marketing: `php artisan db:seed` membuat level harga umum/reseller/grosir.

## 4. Jalankan
```bash
# 5 terminal (atau scripts/serve-all.sh)
php artisan serve --host=127.0.0.1 --port=8000     # core
php artisan serve --host=127.0.0.1 --port=8001     # mkp
php artisan serve --host=127.0.0.1 --port=8002     # sdm
php artisan serve --host=127.0.0.1 --port=8003     # payroll
php artisan serve --host=127.0.0.1 --port=8004     # marketing
```
Buka `http://core.localhost:8000`, login sebagai admin awal → **Pengguna & Penugasan** → buat akun/beri penugasan.
Contoh uji cepat: `php artisan profina:user budi@contoh.id --name=Budi --password='Rahasia123' --assign=marketing:sales`
→ login sebagai Budi: langsung masuk Marketing, tidak bisa membuka modul lain.
# 1. Akun utama (semua modul) — paling penting
php artisan profina:user demo@profina.test --name="Demo PROFINA" --password="DemoProfina123!" --core-role=super-admin --assign=sdm:super-admin --assign=payroll:super-admin --assign=mkp:admin

# 2. Akun khusus SDM
php artisan profina:user hr@profina.test --name="Admin HR Demo" --password="DemoProfina123!" --assign=sdm:hr-admin

# 3. Akun khusus Payroll
php artisan profina:user payroll@profina.test --name="Admin Payroll Demo" --password="DemoProfina123!" --assign=payroll:super-admin

# 4. Akun khusus MKP
php artisan profina:user mkp@profina.test --name="Admin MKP Demo" --password="DemoProfina123!" --assign=mkp:admin
#5. Akun khusus Marketing (level tertinggi)
php artisan profina:user marketing@profina.test --name="Admin Marketing Demo" --password="DemoProfina123!" --assign=marketing:marketing-manager
## Production
- Samakan `PROFINA_URL_*` dan `PROFINA_INTERNAL_URL_*` (alamat HTTPS publik/internal), `SESSION_SECURE_COOKIE=true`.
- Jalankan `php artisan schedule:run` tiap menit di Core dan Marketing; queue worker bila `QUEUE_CONNECTION` bukan `sync`.
- Backup `PROFINA_MASTER_KEY`; mengganti kunci ⇒ jalankan ulang `profina:provision`.

## Catatan integrasi lama
Port asli dipertahankan agar `SDM_URL=…:8002`, `PAYROLL_URL=…:8003` di `.env` modul lama tetap benar.
Alamat server-ke-server tetap `127.0.0.1`; jangan diganti ke `*.localhost` (PHP/Windows tidak selalu me-resolve-nya).
# Arsitektur RBAC & SSO PROFINA

## Alur masuk
```
Browser ──login (email+password+OTP)──▶ CORE (satu-satunya halaman login)
CORE: cek penugasan user ──▶ 0 pintu : halaman "belum ada penugasan"
                           ├▶ 1 pintu : langsung diarahkan ke modul itu
                           └▶ >1      : halaman Pintu Tugas (pilih)
CORE membuat sesi SSO + token sekali pakai (60 dtk, HMAC, khusus 1 modul)
Browser ──GET modul/sso/consume?token=…──▶ MODUL
MODUL: verifikasi tanda tangan, audience, kedaluwarsa, anti-replay (jti)
       → buat/ambil user lokal berdasarkan email → set role dari token → login
```
Modul **tidak punya login sendiri**. `/login`, `/register`, `/forgot-password` lama di modul hanya
mengalihkan ke Core. Satu-satunya cara membuat sesi di modul adalah `/sso/consume`.

## Model RBAC (dua tingkat)
1. **Penugasan modul (Core)** — tabel `module_assignments`: satu user boleh punya beberapa modul, tetapi
   **hanya satu role per modul**. Tanpa baris penugasan, token tidak pernah diterbitkan.
2. **Role & izin di dalam modul** — tetap dipegang tiap modul (Spatie Permission, seperti sebelumnya).
   Core hanya menentukan *role mana* yang dipakai; nama role di katalog Core
   (`core/config/portal.php`) harus sama dengan seeder modul. Untuk MKP role `staff`, Admin Portal
   juga bisa mencentang hak tambahan per orang.

Peran di Core sendiri (pintu "Inti"): `super-admin`, `admin-portal`, `pemantau`.

## Keamanan
- Kunci per modul = `HMAC(master_key, "profina-module:<kode>")`. Modul hanya tahu kuncinya sendiri →
  modul yang bocor tidak bisa memalsukan token modul lain. Kunci induk hanya di Core.
- Token: payload JSON + HMAC-SHA256, `aud` = kode modul, `exp` 60 dtk, `jti` sekali pakai.
- **Pencabutan akses nyata**: tiap sesi modul menanyakan ke Core (`/api/sso/introspect`, bertanda tangan)
  setiap `PROFINA_REVALIDATE_SECONDS` (default 60 dtk). Dicabut/dinonaktifkan/logout di Core ⇒ modul
  menendang user pada permintaan berikutnya. Perubahan role ikut tersinkron. Bila Core mati, sesi
  berjalan tetap boleh `PROFINA_GRACE_SECONDS` (300) lalu diputus (fail-closed).
- Sesi modul tanpa jejak SSO (mis. sisa login lama) otomatis ditolak.
- Logout di modul = logout dari Core + semua sesi modulnya dicabut.
- Email adalah identitas SSO (modul mengenali user dari email) — karena itu email tidak bisa diubah dari halaman Profil Core. Bila harus ganti: buat akun baru, pindahkan penugasan.
- API mobile SDM (`/api/v1/login`, login password langsung) **dimatikan** secara default (`PROFINA_ALLOW_API_LOGIN=false`) supaya tidak jadi pintu belakang di luar Portal. Aktifkan hanya bila sadar risikonya.

## Modul Inti = pengelola & pemantau
- Kelola pengguna (setujui pendaftar, akun baru, reset password, nonaktifkan) & **penugasan modul/role**.
- Dashboard kesehatan modul: UP/DOWN, latensi, antrean job, metrik per modul (cuti menunggu, jurnal draft, dst.),
  sesi modul aktif + tombol **Cabut sesi**.
- Audit: login, penerbitan token, perubahan penugasan, pencabutan sesi.

## Modul Marketing & Sales (baru)
Pelanggan (level harga: umum/reseller/grosir + harga khusus per pelanggan) → Produk & harga per level + MOQ →
Penawaran (PDF) → **Setujui = Sales Order otomatis** → Surat jalan (kirim parsial, PDF) → Komplain.
- Harga & MOQ dihitung **di server** (`PriceResolver`): harga khusus pelanggan > harga level > harga dasar − diskon level.
  Tanpa izin `prices.override`, harga tidak bisa diubah dan diskon dibatasi `MKT_MAX_DISCOUNT_PCT`.
- **HPP/margin disembunyikan**: kolom `cost_price` hanya dikirim ke browser untuk izin `cost.view` (hanya `marketing-manager`).
- Sales biasa hanya melihat pelanggan/penawaran/SO miliknya (`scope.all` = lihat semua).
- Uang `numeric(18,2)` + hitung dengan bilangan bulat sen (tanpa float); nomor dokumen dengan kunci baris; kirim barang dengan `lockForUpdate`.

## Yang sengaja tidak diubah
Integrasi lama antar modul (webhook/REST SDM↔Payroll↔MKP) tetap berjalan lewat alamat `127.0.0.1:<port>`
seperti sebelumnya. Halaman "Kelola Pengguna" lama di tiap modul masih ada, tetapi role dari Core menimpa
perubahan lokal saat login/sinkron berikutnya — kelola role di Core.



# Checklist pengujian setelah instalasi

Kode belum pernah dijalankan oleh pembuatnya (tanpa PHP di lingkungan penyusunan) — lakukan checklist ini sekali di mesinmu.

## A. Persiapan
- [ ] `composer install` & `npm install && npm run build` berhasil di `core` dan keempat modul (path repo `profina/sso-client` terbaca).
- [ ] `php artisan migrate --seed` sukses di semua aplikasi (MKP: MySQL; lainnya: PostgreSQL).
- [ ] `php artisan profina:provision` mencetak ✔ untuk 4 modul; `PROFINA_SSO_SECRET` terisi di tiap `modules/*/.env`.
- [ ] `php artisan route:list` jalan tanpa error di semua aplikasi (`php artisan optimize` juga: tidak ada closure di route).

## B. Satu pintu masuk
- [ ] Buka `http://sdm.localhost:8002/dashboard` tanpa login → dialihkan ke login Core.
- [ ] Login admin Core (OTP) → dashboard Inti tampil, 4 modul berstatus UP (kartu "Kesehatan modul").
- [ ] Buat akun `budi@…` → Penugasan: Marketing = sales. Login sebagai Budi → **langsung** masuk Marketing, tanpa halaman pilihan.
- [ ] Budi mencoba `http://sdm.localhost:8002/dashboard` → tidak bisa (kembali ke Core / ditolak). Menempel ulang URL `…/sso/consume?token=…` → "token sudah dipakai".
- [ ] Beri Budi dua modul → halaman Pintu Tugas menampilkan dua kartu.
- [ ] Cabut penugasan Marketing dari Budi → dalam ±1 menit halaman Marketing yang terbuka menendang Budi ke Core.
- [ ] Ganti role Budi sales→viewer → setelah ±1 menit tombol tulis di Marketing hilang.
- [ ] Nonaktifkan Budi / klik "Cabut sesi" di dashboard Inti → modul menendang Budi.
- [ ] Logout di modul → juga keluar dari Core.
- [ ] MKP: beri role staff + centang hak tambahan → hak berlaku; ganti ke viewer → hak tambahan hilang.

## C. Marketing & Sales
- [ ] Level harga umum/reseller/grosir ada (seeder). Buat produk dengan HPP sebagai marketing-manager; login sebagai `sales` → kolom HPP **tidak ada** di halaman & respons Inertia.
- [ ] Penawaran sebagai sales: harga terisi otomatis dari level pelanggan, tidak bisa diubah; diskon > 5% ditolak; qty < MOQ ditolak.
- [ ] Tandai terkirim → login manager → "Setujui" → Sales Order otomatis terbentuk; PDF penawaran terunduh.
- [ ] Surat jalan parsial: kirim 40% → SO berstatus "Kirim sebagian"; kirim sisanya → "Terkirim penuh"; kirim melebihi sisa ditolak.
- [ ] Sales A tidak bisa membuka penawaran/pelanggan milik sales B (403).
- [ ] Komplain: tutup tanpa penyelesaian ditolak.

## D. Integrasi lama
- [ ] SDM → Payroll (sync karyawan), Payroll ↔ MKP (pendanaan) masih berjalan dengan `.env` bawaan (port 8002/8003).
