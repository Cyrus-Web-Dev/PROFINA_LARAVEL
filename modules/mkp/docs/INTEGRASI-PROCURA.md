# Integrasi Keuangan (MKP) ⇄ PROCURA (Pengadaan)

Menu baru **Dana Pengadaan**: pengajuan dana dari Bagian Pengadaan masuk ke sini, Keuangan memutuskan (setujui / tolak / minta revisi), mencairkan dana,
lalu menerima dan menjurnal LPJ. Semua langkah otomatis membuat jurnal yang seimbang dan mengabari Pengadaan lewat REST API.

| Tab | Isi |
|---|---|
| **Menunggu keputusan** | Pengajuan baru dari Pengadaan. Tombol *Beri keputusan*. |
| **Sudah diputuskan** | Setuju / tolak / revisi, dengan nama pemutus dan catatan. |
| **Pencairan** | Disetujui (siap dicairkan) dan sudah cair. |
| **LPJ** | Laporan pertanggungjawaban dari Pengadaan, siap dijurnal. |
| **Laporan Pengadaan** | Kotak masuk kabar dari Pengadaan + form menulis laporan ke Pengadaan. |

## Hak akses (baru)
| Hak | Fungsi |
|---|---|
| `procurement.decide` | Memutuskan pengajuan (setujui/tolak/revisi) |
| `procurement.disburse` | Mencairkan dana & menjurnal LPJ |

Preset posisi **Finance** otomatis mendapat keduanya. Keputusan **setujui** dengan nominal ≥ `PROCURA_APPROVAL_THRESHOLD` (bawaan = `MKP_APPROVAL_THRESHOLD`, Rp 50 juta)
hanya boleh oleh pemegang hak *Setujui jurnal bernilai besar* (Manager) — pemisahan tugas bawaan.

## Pembukuan
| Langkah | Jurnal (otomatis, langsung diposting) |
|---|---|
| Pencairan | **Dr** Uang Muka Pengadaan (1-1700) **Kr** Kas/Bank |
| Terima LPJ | **Dr** Aset/Beban (dipilih akuntan; disarankan sesuai kategori) + **Dr** Kas/Bank (sisa dana kembali) **Kr** Uang Muka Pengadaan |

Saldo akun *Uang Muka Pengadaan* = dana yang masih di tangan Pengadaan dan belum dipertanggungjawabkan — harus 0 bila semua LPJ sudah dijurnal.
Akun 1-1700 dibuat otomatis bila belum ada (atau `php artisan db:seed --class=ChartOfAccountSeeder`, aman diulang).
Pencairan diblokir bila saldo Kas/Bank tidak cukup atau periode akuntansi sudah ditutup. Kategori aset (elektronik, perabotan, kendaraan, gedung) menampilkan pengingat untuk mendaftarkannya juga di **Aset Tetap** agar disusutkan.

**Konsistensi dua sistem:** pencairan dijalankan dalam satu transaksi — jurnal dibuat, lalu PROCURA diberi tahu. Jika PROCURA menolak/mati, seluruh pencairan dibatalkan
(tidak ada jurnal setengah jadi). Keputusan memanggil PROCURA lebih dulu, baru menyimpan lokal.

## Pengaturan (sekali saja)
1. **Di PROCURA** (Manager) → **Integrasi API** → *Klien API baru* → sistem **Keuangan**. Isi alamat webhook, contoh:
   `http://127.0.0.1:8001/api/procura/webhook` (port berbeda dari PROCURA; jalankan MKP dengan `php artisan serve --port=8001`).
   Event `funding.submitted` dan `funding.settled` sudah terpilih. Salin **token** `pcr_…` dan **rahasia webhook** `whsec_…`.
2. **Di MKP** isi `.env`:
   ```
   PROCURA_URL=http://127.0.0.1:8000/api/v1
   PROCURA_TOKEN=pcr_...
   PROCURA_WEBHOOK_SECRET=whsec_...
   ```
   lalu `php artisan config:clear`.
3. `php artisan migrate` dan `php artisan db:seed --class=RolePermissionSeeder` (mendaftarkan 2 hak baru; aman diulang), lalu beri hak ke staf lewat **Kelola Pengguna**.
4. `npm run build`. Di PROCURA klik **Uji webhook** → "Ping diterima klien". Di MKP buka **Dana Pengadaan → Muat dari Pengadaan** untuk menarik pengajuan yang sudah ada.

Cadangan webhook: `php artisan procura:sync` (terjadwal tiap 10 menit lewat `php artisan schedule:work`).

## Catatan penting
- Server PHP bawaan (`artisan serve`) hanya melayani satu permintaan sekali waktu. Webhook MKP **tidak** memanggil balik PROCURA, jadi aman; tetapi jalankan MKP dan PROCURA di **port berbeda**.
- Token PROCURA untuk Keuangan hanya perlu scope `funding:read` dan `funding:decide`.
- Bila PROCURA sedang mati: pengajuan yang sudah masuk tetap bisa dilihat; keputusan/pencairan akan ditolak dengan pesan jelas (bukan diam-diam gagal).
- Bila keputusan sudah diambil di sisi PROCURA (Meja Keuangan bawaan PROCURA), MKP otomatis menyelaraskan datanya saat aksi ditolak dengan status 422 atau saat sinkron.

## Pemecahan masalah
| Gejala | Solusi |
|---|---|
| "PROCURA menolak token API" | Token salah/dibuat ulang → perbarui `.env`, `config:clear`. |
| "Token … tidak punya izin" | Tambah scope `funding:read` + `funding:decide` di klien Keuangan. |
| Pengajuan tidak muncul otomatis | Cek log pengiriman di PROCURA → Integrasi API; klik *Muat dari Pengadaan*. |
| "Periode … sudah ditutup" | Pilih tanggal di periode terbuka atau buka kembali periode (Manager). |
| "Saldo … tidak cukup" | Pilih akun Kas/Bank lain atau kurangi nominal; saldo dihitung per tanggal pencairan. |
