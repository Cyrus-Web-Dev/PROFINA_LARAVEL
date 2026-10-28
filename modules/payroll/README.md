# PAYBRIDGE — Sistem Payroll & Budgeting Terintegrasi

Jembatan antara **SDM** (siapa, kapan, berapa lama bekerja) dan **Keuangan** (uang & buku besar) di dalam satu ERP.
Prinsipnya: *gaji hanya dibayar sesuai kontrak yang sudah disetujui, setiap rupiahnya bisa ditelusuri, dan tidak ada satu orang pun yang bisa
membuat, menyetujui, dan mencairkan gaji sendirian.*

**Stack:** Laravel 11 · Vue 3 · Inertia · PostgreSQL · REST API + webhook ber-HMAC · Gmail API · password **argon2id**
(fondasi autentikasi/OTP/Gmail/RBAC diambil dari SDM yang sudah berjalan).

> ⚠️ **Status verifikasi.** Kode ditulis dan diperiksa secara statis (keseimbangan sintaks 155 berkas PHP, sintaks `<script>` 41 halaman Vue, silang
> impor kelas / nama rute / halaman Inertia / izin, serta tabel TER diuji terhadap titik uji hitungan manual). **Belum dijalankan** (lingkungan pembuatnya tidak
> memiliki PHP/Composer/jaringan). Jalankan `php artisan test` dan satu putaran percobaan dengan `DemoSeeder` dulu; lihat
> bagian *Yang belum teruji* di bawah.

## Fitur utama

| Area | Isi |
|---|---|
| **Kontrak kompensasi** | Versi per karyawan, berlaku per tanggal, hash isi kontrak, rentang gaji per golongan, usulan dari SDM → verifikasi Payroll → persetujuan Keuangan → (+Direksi bila eksekutif/di luar rentang). Hanya satu kontrak aktif per karyawan (dijamin index database). |
| **Mesin hitung** | Prorata, potongan alpha/cuti tak berbayar (dari SDM), lembur **dihitung sistem** (1/173, PP 35/2021), BPJS (Kes, JHT, JKK, JKM, JP dengan batas upah), **PPh 21 TER (PMK 168/2023, tabel A/B/C)** + masa pajak terakhir (Pasal 17), NPWP +20%, THR (Permenaker 6/2016), kasbon (maks 50%), biaya perusahaan. |
| **Regulasi bertanggal** | Tarif/batas disimpan di database per tanggal efektif, tidak pernah ditimpa (contoh: batas JP naik 1 Maret 2026 menjadi Rp11.086.300). |
| **Kontrol anti-kecurangan** | Pemisahan tugas dipaksa kode, mesin temuan (karyawan fiktif, rekening ganda/baru, absensi kosong, lonjakan gaji, nominal bulat, lembur tak sesuai absensi, anggaran), integritas independen, putaran terkunci di level database, **jejak audit rantai-hash append-only** + jangkar email harian. |
| **Pembayaran** | File transfer CSV ber-SHA-256 (dikirim ke Keuangan sebagai pembanding), rekonsiliasi hasil bank, tahan item, nominal pencairan dikunci. |
| **Keuangan terintegrasi** | Pengajuan dana ke MKP → jurnal akrual, jurnal kas, setoran PPh 21/BPJS dengan jatuh tempo. |
| **Anggaran SDM** | Rencana per departemen × bulan × kategori, persetujuan, anggaran vs realisasi, proyeksi akhir tahun, simulasi kenaikan, *budget gate* saat payroll, dorong ke modul Anggaran MKP. |
| **Slip & laporan** | Slip PDF (dikirim via **Gmail API**, PDF terenkripsi tanggal lahir), register, rekap PPh 21/BPJS, biaya departemen, ringkasan pajak tahunan. |

## Instalasi cepat

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
# 1) buat database:  CREATE DATABASE paybridge;   2) isi DB_* dan PAY_ADMIN_EMAIL/PAY_ADMIN_PASSWORD di .env
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder      # opsional, HANYA non-production: data contoh + anomali
npm run build                                # atau: npm run dev
php artisan serve --port=8003
php artisan schedule:work                    # penjadwal (sinkron SDM, retry webhook, verifikasi audit, pengingat)
php artisan test
```

Email (OTP/slip) memakai Gmail API: salin `credentials.json` + `token.json` dari SDM/MKP ke `storage/app/credentials/` (cara yang sama).
Cek: `php -r 'var_dump(defined("PASSWORD_ARGON2ID"));'` harus `true` (argon2id).

Sambungkan ke SDM & Keuangan: ikuti **docs/INTEGRASI.md** (urutan langkah, endpoint, webhook, pemetaan jurnal).

## Dokumen
- `docs/PANDUAN-PAYROLL.md` — apa itu payroll di ERP, alur, istilah (PTKP/TER/BPJS/THR), skema kecurangan & kontrolnya, peta peran, saran API.
- `docs/INTEGRASI.md` — REST API, webhook, kontrak data SDM ↔ PAYBRIDGE ↔ Keuangan, pemetaan jurnal.
- `docs/KEAMANAN.md` — argon2id, enkripsi, role database aplikasi, trigger, rantai audit, checklist production.

## Yang belum teruji / perlu keputusan Anda
1. **Belum dijalankan di PHP/PostgreSQL nyata.** Migrasi (trigger PL/pgSQL), `dompdf` enkripsi PDF, dan Gmail attachment perlu dicoba sekali.
2. **Perhitungan pajak:** minta konsultan pajak/akuntan memeriksa 3 asumsi: (a) premi JKK/JKM/BPJS Kesehatan perusahaan masuk bruto TER (`PAY_TAXABLE_EMPLOYER_PREMIUMS`); (b) PTKP dan biaya jabatan proporsional untuk karyawan masuk/keluar tengah tahun; (c) lebih-potong Desember dikembalikan lewat gaji.
3. **Belum ada:** gross-up/tunjangan pajak, TER harian untuk harian lepas, pesangon/uang penghargaan, batas usia JP, kontrak yang berganti di tengah bulan (ditandai, bukan dipecah), format impor Coretax resmi (ekspor yang ada adalah data kerja), H2H bank (CSV generik), NIK pada bukti potong.
4. Uji dengan **angka nyata perusahaan** (satu bulan paralel dengan sistem lama) sebelum dipakai membayar.
