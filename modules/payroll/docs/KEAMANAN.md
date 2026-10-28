# Keamanan PAYBRIDGE

## 1. Password: argon2id (bukan bcrypt)
`config/hashing.php` → `HASH_DRIVER=argon2id` (memori 37888 KiB, 3 iterasi, 1 thread = rekomendasi minimum OWASP). Pastikan PHP mendukung:
`php -r 'var_dump(defined("PASSWORD_ARGON2ID"));'` → `bool(true)`. Hash bcrypt lama (mis. dari impor) diverifikasi lalu otomatis di-*rehash* ke argon2id saat login.
Login memakai OTP email (2 langkah), pendaftaran memerlukan verifikasi + persetujuan Admin, sesi diikat cookie `HttpOnly`/`SameSite`, dan `SESSION_SECURE_COOKIE=true` di HTTPS.

## 2. Data sensitif
- NPWP, nomor rekening, nomor BPJS, tanggal lahir: **terenkripsi di database** (cast `encrypted`, kunci `APP_KEY`). **Cadangkan `APP_KEY`** secara terpisah — tanpa itu data tak terbaca.
- Perbandingan rekening memakai **HMAC-SHA256** (bukan teks asli).
- Token API & rahasia klien: token hanya tampil sekali, disimpan sebagai **SHA-256**; rotasi dari halaman Integrasi.
- Keuangan tidak menerima gaji per orang; hanya total per akun.
- PDF slip yang dikirim email dienkripsi (kata sandi = tanggal lahir DDMMYYYY, atau NIP). Bila enkripsi gagal, lampiran **tidak** dikirim (tidak ada fallback tanpa sandi).
- File backup berisi seluruh data: simpan di luar server dan enkripsi.

## 3. Lapisan integritas di database (PostgreSQL)
| Mekanisme | Perlindungan |
|---|---|
| `audit_ledger` + trigger `BEFORE UPDATE/DELETE/TRUNCATE` | jejak audit **append-only**; tiap baris menyimpan hash baris sebelumnya |
| trigger `compensation_contracts` & `contract_components` | kontrak berstatus approved/active/superseded/expired tak bisa diubah isinya/dihapus |
| trigger `payroll_runs` & `payroll_items` | putaran terkunci: total, hash, daftar item, nilai kotor/bersih/PPh 21, baris komponen, rekening tak bisa diubah/dihapus |
| index unik parsial | satu kontrak aktif per karyawan; satu putaran reguler aktif per bulan |
| jangkar audit harian (`pay:audit-anchor`) | hash kepala rantai dikirim ke email auditor: penulisan ulang seluruh rantai tetap ketahuan |
| `pay:audit-verify` (01:00) | verifikasi seluruh rantai; email peringatan bila rusak |

### Role database aplikasi (WAJIB di production)
Trigger hanya berguna bila aplikasi **tidak** bisa mematikannya. Jalankan migrasi dengan role pemilik, aplikasi dengan role terbatas:
```sql
CREATE ROLE paybridge_app LOGIN PASSWORD '…kuat…';
GRANT CONNECT ON DATABASE paybridge TO paybridge_app;
GRANT USAGE ON SCHEMA public TO paybridge_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO paybridge_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO paybridge_app;
REVOKE UPDATE, DELETE, TRUNCATE ON audit_ledger FROM paybridge_app;   -- lapis ganda di atas trigger
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO paybridge_app;
```
`.env` aplikasi memakai `paybridge_app`; `php artisan migrate` dijalankan dengan `.env` pemilik tabel (role lain). Dengan begitu `DROP TRIGGER`/`ALTER TABLE` tidak bisa dilakukan lewat aplikasi.

## 4. Kontrol aplikasi
Pemisahan tugas dipaksa di layanan (`PAY_SOD_ENFORCED=true`, jangan dimatikan); semua keputusan penting tercatat di rantai audit lengkap dengan pelaku & IP;
REST API dibatasi laju (`PAY_API_RATE_LIMIT`), ber-scope, dan dapat dinonaktifkan per klien; webhook masuk wajib tanda tangan HMAC + idempoten.

## 5. Checklist production
- [ ] HTTPS, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `PAY_FORCE_HTTPS=true`, `PAY_CSP=enforce` (setelah uji `report-only`)
- [ ] `PAY_SOD_ENFORCED=true`, minimal 3 orang berbeda memegang peran admin/manager/penyetuju
- [ ] Role database terbatas (bagian 3), backup terenkripsi di luar server, `APP_KEY` tersimpan terpisah
- [ ] `PAY_AUDIT_ANCHOR_EMAILS` diisi (auditor/direksi); `php artisan schedule:work` (atau cron `schedule:run`) aktif
- [ ] Token klien API hanya dengan scope seperlunya; rotasi berkala
- [ ] Verifikasi hitungan satu bulan paralel dengan sistem lama + tinjauan konsultan pajak sebelum go-live

## 6. Batasan yang perlu diketahui
- Webhook belum memeriksa umur tanda tangan (pencegahan *replay* bergantung pada idempotensi `Delivery`). Untuk jaringan tak tepercaya tambahkan cap waktu bertanda tangan.
- Super-admin tetap tunduk pada pemisahan tugas, tetapi pengelola database yang memegang role pemilik tetap bisa membongkar pengaman; itulah gunanya jangkar eksternal.
- Persetujuan dua orang bergantung pada akun yang tidak dibagi pakai; terapkan MFA/SSO untuk peran penyetuju.
