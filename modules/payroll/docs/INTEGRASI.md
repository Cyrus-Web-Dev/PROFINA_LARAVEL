# Integrasi SDM ⇄ PAYBRIDGE ⇄ Keuangan (MKP)

Pola yang dipakai sama dengan integrasi PROCURA ⇄ MKP yang sudah berjalan: **token bearer ber-scope** (disimpan sebagai hash SHA-256),
**webhook ber-HMAC-SHA256**, log pengiriman + **retry eksponensial**, **idempotensi**, dan **sinkron cadangan** lewat penjadwal.

## 1. Siapa memegang apa

| Data | Sumber kebenaran | Mengalir ke |
|---|---|---|
| Karyawan, absensi, cuti, kalender libur, rekening, NPWP | **SDM** | PAYBRIDGE (tarik via REST, dikabari via webhook) |
| Kontrak kompensasi (gaji yang sah), golongan, komponen, regulasi | **PAYBRIDGE** | SDM (gaji pokok disamakan setelah kontrak aktif) |
| Putaran gaji, pajak, BPJS, anggaran SDM, jejak audit | **PAYBRIDGE** | Keuangan (hanya total per akun) |
| Buku besar, kas/bank, periode akuntansi | **MKP** | PAYBRIDGE (status keputusan/pencairan) |

```
 SDM (:8002) ──REST token──►  PAYBRIDGE (:8003) ──webhook funding/budget──► MKP (:8001)
     ▲  ◄──webhook employee.*──┘   ▲ ◄────REST token: decision/disbursement/remittance────┘
     └────── webhook contract.*, payslip.published ◄───┘
```
Port contoh: PROCURA 8000 · MKP 8001 · SDM 8002 · PAYBRIDGE 8003. Buka tiap aplikasi lewat alamat persis sama dengan `APP_URL`.

## 2. Urutan pemasangan (sekali saja)

1. **PAYBRIDGE**: instalasi (README), `migrate --seed`, login sebagai super-admin.
2. **SDM**: salin berkas *overlay* (`sdm-hris-paybridge-overlay.zip`), `php artisan migrate`, `php artisan db:seed --class=RolePermissionSeeder`, `npm run build`.
   Buka **Integrasi Payroll → + Klien Payroll**: nama "PAYBRIDGE", scope semua, alamat webhook `http://127.0.0.1:8003/api/sdm/webhook`, event semua.
   Salin **token** → `SDM_TOKEN` dan **rahasia** → `SDM_WEBHOOK_SECRET` di `.env` PAYBRIDGE (`SDM_URL=http://127.0.0.1:8002/api/payroll/v1`).
3. **PAYBRIDGE → Integrasi API**: buat klien **"SDM"** (scope `contracts:read`,`payslips:read`; webhook `http://127.0.0.1:8002/api/payroll/webhook`; event `contract.*`,`payslip.published`,`run.paid`).
   Salin rahasianya → `PAYROLL_WEBHOOK_SECRET` di `.env` SDM.
4. **PAYBRIDGE → Integrasi API**: buat klien **"Keuangan"** (preset Keuangan; webhook `http://127.0.0.1:8001/api/payroll/webhook`).
   Salin token → `PAYROLL_TOKEN`, rahasia → `PAYROLL_WEBHOOK_SECRET` di `.env` MKP (`PAYROLL_URL=http://127.0.0.1:8003/api/v1`).
5. **MKP**: salin overlay, `php artisan migrate`, `db:seed --class=RolePermissionSeeder`, `npm run build`. Beri hak `payroll.decide` / `payroll.disburse` ke orang yang tepat (Kelola Pengguna).
6. `php artisan config:clear` di ketiganya. Tes: tombol **Tes koneksi** / **Ping webhook** di halaman Integrasi masing-masing.
7. PAYBRIDGE: **Karyawan → Sinkron dari SDM**, isi **Saldo Awal Tahun** (bila go-live tengah tahun), buat **kontrak** tiap karyawan (atau terima usulan dari SDM), aktifkan **Anggaran SDM**.
8. Setelah satu bulan paralel cocok, set `SDM_PAYROLL_EXTERNAL=true` di SDM (kunci modul payroll lama).

## 3. REST API PAYBRIDGE v1 (`/api/v1`)

Header: `Authorization: Bearer pby_…`. Sukses `{"data": …, "meta": …}`; gagal `{"error": {"code","message","details"}}`
(401 `invalid_token` · 403 `insufficient_scope` · 404 · 422 `workflow_violation`/validasi · 429).

| Metode & jalur | Scope | Fungsi |
|---|---|---|
| `GET /ping` | (token sah) | identitas klien & scope |
| `GET /funding?status=&updated_since=&page=` | `funding:read` | daftar pengajuan dana gaji |
| `GET /funding/{number}` | `funding:read` | satu pengajuan (rincian jurnal, hash file bank, setoran) |
| `POST /funding/{number}/decision` | `funding:decide` | `{decision: approve|reject|revision, note, decided_by, finance_ref, approved_amount?}` — setuju harus = total utang gaji |
| `POST /funding/{number}/disbursement` | `funding:decide` | `{amount, reference, disbursed_by, journal_refs}` — **422 bila nominal ≠ total file bank** atau file bank belum ada |
| `POST /funding/{number}/notes` | `funding:decide` | `{subject, body, author}` (pakai `Idempotency-Key`) |
| `GET /remittances?status=pending` | `remittance:read` | kewajiban setor PPh 21/BPJS + jatuh tempo |
| `POST /remittances/{id}/paid` | `remittance:update` | `{paid_by, reference, finance_ref?, journal_ref?}` |
| `GET /budgets/active?year=` | `budget:read` | anggaran SDM aktif per kunci akun per bulan |
| `GET /contracts?nip=&status=` | `contracts:read` | status kontrak |
| `GET /payslips?nip=` | `payslips:read` | ringkasan slip terbit (tanpa rincian pajak) |
| `GET /audit/head` | `audit:read` | verifikasi rantai audit + hash kepala |

Contoh keputusan:
```bash
curl -X POST http://127.0.0.1:8003/api/v1/funding/PFD-PR-2610-001/decision \
  -H "Authorization: Bearer $PAYROLL_TOKEN" -H "Content-Type: application/json" \
  -d '{"decision":"approve","approved_amount":412350000,"decided_by":"Siti (Keuangan)","finance_ref":"KPY-2610-0001"}'
```

### Webhook PAYBRIDGE → klien
Header: `X-Paybridge-Event`, `X-Paybridge-Delivery` (id unik), `X-Paybridge-Signature: sha256=<hmac_sha256(body_mentah, rahasia)>`. Isi: `{"event","occurred_at","data":{…}}`.
Respons 2xx = diterima; selain itu dicoba ulang 2,4,8,16,32 menit lalu ditandai `failed` (bisa **Kirim ulang** manual).

| Event | Penerima | Isi `data` (ringkas) |
|---|---|---|
| `funding.submitted` | Keuangan | `number, run_number, period_*, amount_net_total, amount_disbursable, breakdown{debit,credit}, bank_batches[], remittances[]` |
| `funding.cancelled` | Keuangan | `number, reason` |
| `remittance.created` | Keuangan | `id, type, amount, due_date, report_due_date` |
| `budget.approved` | Keuangan | `fiscal_year, version, lines[{gl_key,month,amount}]` |
| `contract.activated / rejected` | SDM | `nip, contract_number, base_salary, effective_date, end_date` |
| `payslip.published` | SDM | `run_number, period_*, payslips[{nip,gross,net}]` (batch 100) |
| `run.paid` | SDM | `run_number, period_*, paid_at` |

Verifikasi penerima (PHP):
```php
$ok = hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $request->header('X-Paybridge-Signature'));
```

## 4. Kontrak SDM ⇄ PAYBRIDGE

**API SDM** (`/api/payroll/v1`, token klien SDM): `GET /ping`, `/employees?updated_since=&page=&sensitive=1`, `/employees/{nip}`, `/departments`,
`/holidays?year=`, `/attendance-summary?year=&month=&detail=1&nip=`.
Data sensitif (NPWP, rekening, BPJS, tanggal lahir) hanya bila `sensitive=1` **dan** token ber-scope `employees:sensitive`.

`attendance-summary` per karyawan: `scheduled_days, present_days, late_count, late_minutes, alpha_days, sick_days, permit_days, paid_leave_days,
unpaid_leave_days, wfh_days, unrecorded_days, days[{date,status,check_in,check_out}]`. Cuti dihitung dari tabel cuti disetujui (bukan status absensi);
hari tanpa catatan **tidak** dihitung alpha kecuali `SDM_COUNT_UNRECORDED_AS_ALPHA=true`.

**Webhook SDM → PAYBRIDGE** (`/api/sdm/webhook`, header `X-Sdm-Event|Delivery|Signature`): *event tipis* — hanya memberi tahu siapa yang berubah; PAYBRIDGE menarik detailnya lewat REST.

| Event | Perilaku di PAYBRIDGE |
|---|---|
| `employee.created/updated/status_changed/bank_changed` | tarik ulang karyawan; perubahan rekening menandai `bank_changed_at` → temuan **BANK_RECENT** pada payroll berikutnya |
| `employee.compensation_changed` | **hanya menjadi USULAN kontrak** (salin pengaturan kontrak aktif + gaji baru, berlaku awal bulan depan). Tidak pernah mengubah gaji yang dibayar. |

Selaras balik: saat kontrak aktif di PAYBRIDGE, SDM menerima `contract.activated` dan memperbarui `base_salary` **tanpa** memicu webhook balik (guard `silently()`).

## 5. Pemetaan jurnal di Keuangan (MKP)

PAYBRIDGE mengirim **kunci semantik**, MKP yang memutuskan akunnya (`config/payroll.php`; akun yang belum ada dibuat otomatis).

| Kunci | Akun MKP | Sisi |
|---|---|---|
| `salary_expense / allowance_expense / overtime_expense / bonus_expense / thr_expense / reimbursement_expense` | 5-1100 / 5-1105 / 5-1110 / 5-1115 / 5-1116 / 5-1117 | Debit |
| `bpjs_kes_company_expense`, `bpjs_tk_company_expense` | 5-1120, 5-1125 | Debit |
| `net_pay_liability` | 2-1200 Utang Gaji | Kredit |
| `pph21_liability`, `bpjs_kes_liability`, `bpjs_tk_liability` | 2-1310, 2-1320, 2-1330 | Kredit |
| `employee_receivable` (cicilan kasbon) | 1-1800 Piutang Karyawan | Kredit |
| `other_deduction_payable` | 2-1340 | Kredit |

Contoh satu karyawan (bruto 12.000.000; BPJS pers. 470.863; PPh 627.240; BPJS perusahaan 1.210.526):
1. **Setujui** — Dr Beban Gaji+Tunjangan 12.000.000 · Dr Beban BPJS 1.210.526 / Kr Utang Gaji 10.901.897 · Kr Utang PPh 21 627.240 · Kr Utang BPJS 1.681.389. (Debit = Kredit = 13.210.526.)
2. **Cairkan** — Dr Utang Gaji 10.901.897 / Kr Bank 10.901.897.
3. **Setor** — Dr Utang PPh 21 627.240 / Kr Bank; Dr Utang BPJS 1.681.389 / Kr Bank.
Nominal negatif (mis. PPh 21 lebih-potong Desember) otomatis berpindah sisi.

## 6. Kegagalan & pemulihan
- Keputusan/pencairan di MKP: jurnal dibuat **dalam transaksi**, lalu API PAYBRIDGE dipanggil terakhir; bila PAYBRIDGE menolak (422), seluruh jurnal dibatalkan.
- Webhook gagal tidak menggagalkan aksi pengguna: tercatat, dicoba ulang, dan `pay:sync` / `payroll:sync` / `sdm:integration-retry` menutup celahnya.
- Semua peristiwa masuk disimpan (`inbound_events`/`payroll_events`/`integration_inbox`) dan idempoten; dapat **diproses ulang** dari halaman Integrasi.
- Server `php artisan serve` satu-utas: handler webhook sengaja **tidak** memanggil balik pengirimnya.
