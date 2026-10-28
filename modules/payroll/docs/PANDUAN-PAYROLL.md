# Panduan: Payroll di dalam ERP

## 1. Peran sistem payroll
SDM tahu *siapa* bekerja dan *berapa hari*; Keuangan tahu *uang apa yang ada*. Payroll adalah **penerjemah dan penjaga** di antaranya:
mengubah kontrak + kehadiran menjadi angka gaji, pajak, dan iuran yang sah, lalu memastikan uang yang keluar **persis** sama dengan yang disetujui.
Karena gaji adalah pengeluaran rutin terbesar dan paling mudah dimanipulasi (karyawan fiktif, rekening dialihkan, bonus bulat), PAYBRIDGE dirancang
bukan hanya "menghitung", tetapi **mempersulit kecurangan**.

## 2. Alur satu bulan
```
SDM: absensi & cuti ─┐
Kontrak aktif ───────┼─► HITUNG ─► TEMUAN (kontrol) ─► AJUKAN ─► VERIFIKASI ─► SETUJUI (+Direksi) ─► 🔒 KUNCI
Input variabel (OT, bonus, kasbon, disetujui orang lain) ─┘                                                │
                                                                                                          ▼
Slip gaji ◄─ CAIRKAN (Keuangan, jurnal kas) ◄─ FILE BANK (hash) ◄─ DANA DISETUJUI (jurnal akrual) ◄─ PENGAJUAN DANA
   │                                                                                       └► SETORAN PPh 21 & BPJS (jatuh tempo)
   └► rekonsiliasi hasil bank ─► TUTUP putaran
```

## 3. Peta peran (pemisahan tugas)
| Peran | Boleh | Tidak boleh |
|---|---|---|
| `hr-partner` | lihat karyawan/kontrak, usulkan kontrak | menyetujui apa pun |
| `payroll-admin` (pembuat) | usulkan kontrak, buat input, hitung & ajukan putaran, buat file bank, kirim slip | memverifikasi/menyetujui, menyelesaikan temuan kritis |
| `payroll-manager` (pemeriksa) | verifikasi kontrak & putaran, setujui input, tahan item, selesaikan temuan, atur regulasi, rekonsiliasi | memeriksa putaran yang ia buat/hitung/ajukan |
| `finance-approver` | setujui kontrak/putaran/input besar/anggaran | menyetujui yang ia buat atau yang sudah ia pegang langkah lain |
| `direksi` | setujui kontrak & putaran eksekutif/nominal besar | — |
| `auditor` | baca semua, ekspor, verifikasi rantai audit | mengubah data |
| `karyawan` | slip miliknya | — |

Aturan dipaksa di **layanan** (`ApprovalService`, `PayrollRunService`), bukan hanya disembunyikan di menu: satu orang tidak bisa memegang dua langkah
pada dokumen yang sama; pengaju tidak bisa menyetujui; pegawai tidak bisa memutuskan input/kasbon untuk dirinya sendiri.

## 4. Skema kecurangan payroll → kontrol yang menangkapnya
| Skema | Kontrol |
|---|---|
| **Karyawan fiktif** (digaji tanpa bekerja) | Hanya SDM yang menciptakan karyawan; `GHOST_NO_ACTIVITY` (absensi kosong sebulan penuh); `NO_CONTRACT`; verifikasi independen |
| **Rekening dialihkan / ganda** | `DUP_BANK` (HMAC rekening), `BANK_RECENT` (rekening baru diubah wajib diverifikasi), rekening dibekukan pada snapshot, file bank di-hash |
| **Kenaikan gaji gelap** | gaji hanya dari kontrak berhash; `CONTRACT_TAMPER`; `SDM_DRIFT` (gaji SDM ≠ kontrak); persetujuan berlapis di luar rentang golongan; trigger menolak ubah kontrak aktif |
| **Lembur fiktif / digelembungkan** | upah dihitung sistem dari jam; dicocokkan dengan jam pulang absensi SDM (`OT_NOT_ATTENDED`); batas 4 jam/hari & 18 jam/minggu |
| **Bonus/koreksi bulat** | persetujuan orang lain; `INPUT_ROUND`, `INPUT_LARGE`; nominal besar butuh level Keuangan |
| **Ubah angka setelah disetujui** | hash item & snapshot, trigger PostgreSQL menolak UPDATE/DELETE pada putaran terkunci, verifikasi integritas independen |
| **Transfer ≠ yang disetujui** | nominal pencairan dikunci = total file bank; hash file dikirim ke Keuangan; rekonsiliasi hasil bank (`amount/missing/unexpected`) |
| **Menghapus jejak** | audit rantai-hash append-only + jangkar email harian + verifikasi malam |
| **Anggaran bocor** | *budget gate* per departemen; temuan `BUDGET_EXCEEDED` harus dijelaskan penyetuju anggaran |

## 5. Istilah penting
- **PTKP** — penghasilan tidak kena pajak (TK/0 = Rp54 juta/tahun; tiap tanggungan +Rp4,5 juta). Menentukan kategori TER.
- **TER** — Tarif Efektif Rata-rata (PP 58/2023, PMK 168/2023): Jan–Nov dipotong = bruto bulan × tarif tabel (A: TK/0,TK/1,K/0 · B: TK/2,TK/3,K/1,K/2 · C: K/3). **Desember / bulan berhenti** dihitung ulang setahun dengan tarif Pasal 17 (5/15/25/30/35%) setelah biaya jabatan, iuran pensiun, dan PTKP; selisihnya dipotong/dikembalikan.
- **Tanpa NPWP** — tarif +20%.
- **BPJS Kesehatan** 4% perusahaan + 1% karyawan (batas upah Rp12 juta). **JHT** 3,7% + 2%. **JKK** 0,24–1,74% (kelas risiko) perusahaan. **JKM** 0,3% perusahaan. **JP** 2% + 1% (batas upah naik tiap Maret: Rp11.086.300 sejak 1 Mar 2026).
- **THR** — 1 bulan upah bila masa kerja ≥12 bulan, proporsional (bulan/12) bila 1–<12 bulan; dibayar paling lambat H-7 hari raya.
- **Lembur** — upah/jam = upah sebulan ÷ 173; hari kerja 1,5× jam pertama lalu 2×; hari istirahat/libur (5 hari kerja) 2× s.d. jam ke-8, 3× jam ke-9, 4× jam ke-10 dst.
- **Akrual vs kas** — beban gaji diakui saat putaran disetujui (utang gaji), kas keluar saat dicairkan, utang pajak/BPJS hilang saat disetor.
- **Saldo awal** — bila go-live di tengah tahun, Januari s.d. bulan sebelum go-live harus dimasukkan agar PPh 21 Desember benar.

## 6. Saran API & integrasi tambahan (belum dipasang; urut prioritas)
1. **Bank H2H / disbursement massal** (BCA KlikBisnis/BCA API, Mandiri Cash Management, BNI/BRI, atau penyedia seperti Xendit/Midtrans Iris/Flip) — ganti CSV generik; BI-FAST untuk transfer instan dan *account name inquiry* untuk memvalidasi nama pemilik rekening (menutup celah rekening salah/fiktif). Tambah sebagai `BankDriver` di `BankFileService`.
2. **Coretax / e-Bupot PPh 21** — format impor bukti potong resmi (BPMP/BPA1) menggantikan ekspor "data kerja"; butuh **NIK** karyawan (belum ada di SDM).
3. **BPJS e-Dabu / SIPP Online** — impor data iuran bulanan & verifikasi nomor peserta.
4. **WhatsApp (Fonnte) / Telegram** — kerangka ada (`NotificationGateway`); aktifkan untuk notifikasi persetujuan & temuan kritis.
5. **Tanda tangan elektronik** (mis. PrivyID/VIDA) untuk kontrak kompensasi & persetujuan putaran bernilai besar.
6. **SSO/IdP perusahaan** (Google Workspace / Microsoft Entra) + MFA wajib untuk peran penyetuju.
7. **Penyimpanan jangkar eksternal** (bucket tak-bisa-diubah / WORM) untuk hash audit selain email.
8. **Absensi biometrik/mobile** langsung ke SDM agar `unrecorded_days` dapat dihitung alpha dengan aman.
