<?php

namespace App\Services;

use App\Exceptions\ProcuraException;
use App\Models\ChartOfAccount;
use App\Models\ProcurementFunding;
use App\Models\ProcurementReport;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sisi Keuangan dari alur pengadaan:
 *
 *   Pengajuan masuk (webhook / sinkron)  →  KEPUTUSAN (setujui / tolak / revisi)  →  PENCAIRAN  →  LPJ dari Pengadaan → JURNAL LPJ
 *
 * Pembukuan:
 *   Pencairan  : Dr Uang Muka Pengadaan            Kr Kas/Bank
 *   Terima LPJ : Dr Aset/Beban (sesuai kategori)   Kr Uang Muka Pengadaan   (+ Dr Kas/Bank untuk sisa dana yang dikembalikan)
 *
 * Semua jurnal dibuat otomatis (autoApprove) dan langsung diposting seperti transaksi dokumen lain di MKP;
 * kendali nilai besar dilakukan pada langkah KEPUTUSAN (hak "Setujui jurnal bernilai besar").
 */
class ProcurementFundingService
{
    public function __construct(private ProcuraClient $client, private JournalService $journals)
    {
    }

    // =====================================================================
    //  Sinkron dari PROCURA
    // =====================================================================

    /** Tarik semua pengajuan dari PROCURA. $updatedSince (ISO) membatasi yang berubah sejak waktu tertentu. Mengembalikan jumlah yang diproses. */
    public function syncAll(?string $updatedSince = null): int
    {
        $count = 0;
        $page = 1;
        do {
            $res = $this->client->listFunding(['submitted', 'approved', 'revision', 'rejected', 'disbursed', 'settled', 'cancelled'], $page, $updatedSince);
            foreach ($res['data'] as $remote) {
                if ($this->upsert($remote)) {
                    $count++;
                }
            }
            $last = (int) ($res['meta']['last_page'] ?? 1);
            $page++;
        } while ($page <= $last && $page <= 20);

        return $count;
    }

    public function syncOne(ProcurementFunding $f): bool
    {
        try {
            $this->upsert($this->client->find($f->procura_number));

            return true;
        } catch (ProcuraException $e) {
            $f->update(['last_error' => $e->getMessage()]);

            return false;
        }
    }

    /** Simpan/perbarui satu pengajuan dari data PROCURA (bentuk sama untuk GET /funding dan webhook). */
    public function upsert(array $r, ?string $deliveryId = null): ?ProcurementFunding
    {
        if (empty($r['number']) || empty($r['status']) || $r['status'] === 'pending_internal') {
            return null;   // belum sampai ke Keuangan
        }

        $f = ProcurementFunding::firstOrNew(['procura_number' => $r['number']]);
        $isNew = ! $f->exists;
        $before = $f->status;

        $status = $r['status'];
        if ($f->settlement_booked_at && $status === 'settled') {
            $status = 'closed';   // LPJ sudah dijurnal di sisi kita
        }

        $f->fill([
            'request_number' => $r['request_number'] ?? $f->request_number,
            'request_title' => $r['request_title'] ?? $f->request_title,
            'category' => $r['category'] ?? $f->category,
            'amount' => $r['amount'],
            'purpose' => $r['purpose'] ?? $f->purpose,
            'status' => $status,
            'submitted_at' => $this->date($r['submitted_at'] ?? null) ?? $f->submitted_at,
            'approved_amount' => $r['approved_amount'] ?? $f->approved_amount,
            'decided_by_name' => $r['decided_by'] ?? $f->decided_by_name,
            'decided_at' => $this->date($r['decided_at'] ?? null) ?? $f->decided_at,
            'decision_note' => $r['decision_note'] ?? $f->decision_note,
            'finance_ref' => $f->finance_ref ?: ($r['finance_ref'] ?? null),
            'disbursed_amount' => $r['disbursed_amount'] ?? $f->disbursed_amount,
            'disbursed_at' => $this->date($r['disbursed_at'] ?? null) ?? $f->disbursed_at,
            'actual_spent' => $r['actual_spent'] ?? $f->actual_spent,
            'returned_amount' => $r['returned_amount'] ?? $f->returned_amount,
            'settled_at' => $this->date($r['settled_at'] ?? null) ?? $f->settled_at,
            'snapshot' => $r,
            'synced_at' => now(),
            'last_error' => null,
        ])->save();

        $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');
        if ($isNew && $status === 'submitted') {
            ProcurementReport::record($f, 'masuk', 'pengajuan', "Pengajuan dana baru {$f->procura_number}: {$rp($f->amount)}",
                trim(($f->request_title ? "Untuk: {$f->request_title} ({$f->request_number})\n" : '').($f->purpose ? "Tujuan: {$f->purpose}" : '')), 'sub:'.$f->procura_number, $deliveryId);
        } elseif (! $isNew && $before !== $f->status) {
            match (true) {
                $f->status === 'settled' => ProcurementReport::record($f, 'masuk', 'lpj', "LPJ diserahkan Pengadaan: {$f->procura_number}",
                    "Dana cair {$rp($f->disbursed_amount)}, terpakai {$rp($f->actual_spent)}, sisa dikembalikan {$rp($f->returned_amount)}. Siap dijurnal.", 'lpj:'.$f->procura_number, $deliveryId),
                $f->status === 'cancelled' => ProcurementReport::record($f, 'masuk', 'catatan', "Pengajuan {$f->procura_number} dibatalkan oleh Pengadaan", null, 'cancel:'.$f->procura_number, $deliveryId),
                in_array($f->status, ['approved', 'rejected', 'revision'], true) && $before === 'submitted'
                    => ProcurementReport::record($f, 'masuk', 'keputusan', "Keputusan {$f->procura_number} tercatat di Pengadaan: ".(config("procura.status.{$f->status}.0")), $f->decision_note, 'dec:'.$f->procura_number.':'.$f->status, $deliveryId),
                default => null,
            };
        }

        return $f;
    }

    // =====================================================================
    //  Keputusan
    // =====================================================================

    /** @param 'approve'|'reject'|'revision' $decision */
    public function decide(ProcurementFunding $f, string $decision, ?float $approvedAmount, ?string $note, User $user): void
    {
        $this->assertStatus($f, ['submitted'], 'Pengajuan ini tidak sedang menunggu keputusan.');
        if (! in_array($decision, ['approve', 'reject', 'revision'], true)) {
            $this->fail('Pilih keputusan: setujui, tolak, atau minta revisi.');
        }
        if ($decision !== 'approve' && blank($note)) {
            $this->fail('Isi alasan / catatan keputusan agar Pengadaan tahu apa yang harus diperbaiki.');
        }

        $approved = null;
        if ($decision === 'approve') {
            $approved = $approvedAmount ?? (float) $f->amount;
            if ($approved <= 0) {
                $this->fail('Nominal yang disetujui harus lebih dari nol.');
            }
            if ($approved > (float) $f->amount + 0.005) {
                $this->fail('Nominal yang disetujui tidak boleh melebihi yang diajukan (Rp '.number_format((float) $f->amount, 0, ',', '.').').');
            }
            if ($approved >= (float) config('procura.approval_threshold') && ! $user->can('journal.approve')) {
                $this->fail('Nominal ≥ Rp '.number_format((float) config('procura.approval_threshold'), 0, ',', '.').' hanya boleh disetujui oleh pemegang hak "Setujui jurnal bernilai besar" (Manager).');
            }
        }

        $ref = $this->reserveRef($f);

        try {
            $this->client->decide($f->procura_number, array_filter([
                'decision' => $decision, 'approved_amount' => $approved, 'note' => $note, 'decided_by' => $user->name, 'finance_ref' => $ref,
            ], fn ($v) => $v !== null));
        } catch (ProcuraException $e) {
            if ($e->status === 422) {
                $this->syncOne($f);   // mungkin sudah diputuskan di tempat lain: selaraskan data lokal
            }
            throw $e;
        }

        DB::transaction(function () use ($f, $decision, $approved, $note, $user) {
            $status = ['approve' => 'approved', 'reject' => 'rejected', 'revision' => 'revision'][$decision];
            $f->update([
                'status' => $status, 'approved_amount' => $approved, 'decided_by' => $user->id, 'decided_by_name' => $user->name,
                'decided_at' => now(), 'decision_note' => $note,
            ]);

            $label = ['approve' => 'DISETUJUI', 'reject' => 'DITOLAK', 'revision' => 'diminta REVISI'][$decision];
            ProcurementReport::record($f, 'keluar', 'keputusan', "Pengajuan {$f->procura_number} {$label}",
                "No. dokumen Keuangan: {$f->finance_ref}".($approved ? "\nDisetujui: Rp ".number_format($approved, 0, ',', '.') : '').($note ? "\nCatatan: {$note}" : ''), null, null, $user->id);
            ActivityLogger::log('procurement.'.$decision, "Keputusan dana pengadaan {$f->procura_number} ({$f->finance_ref}): {$label}", $f, $user->id);
        });
    }

    // =====================================================================
    //  Pencairan  (Dr Uang Muka Pengadaan / Kr Kas-Bank)
    // =====================================================================

    /** @param array{cash_account_id:int,amount:float,date:string,method:string,reference?:?string} $data */
    public function disburse(ProcurementFunding $f, array $data, User $user): void
    {
        $this->assertStatus($f, ['approved'], 'Dana hanya bisa dicairkan untuk pengajuan yang sudah disetujui.');

        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0 || $amount > (float) $f->approved_amount + 0.005) {
            $this->fail('Nominal pencairan harus lebih dari nol dan tidak boleh melebihi yang disetujui (Rp '.number_format((float) $f->approved_amount, 0, ',', '.').').');
        }

        $cash = ChartOfAccount::cashBank()->find($data['cash_account_id']);
        if (! $cash) {
            $this->fail('Pilih akun Kas/Bank sumber dana yang aktif.');
        }
        $saldo = $cash->getBalance(null, $data['date']);
        if ($saldo + 0.005 < $amount) {
            $this->fail("Saldo {$cash->name} per ".Carbon::parse($data['date'])->format('d/m/Y').' hanya Rp '.number_format($saldo, 0, ',', '.').', tidak cukup untuk mencairkan Rp '.number_format($amount, 0, ',', '.').'.');
        }

        try {
            DB::transaction(function () use ($f, $data, $user, $amount, $cash) {
                $f = ProcurementFunding::lockForUpdate()->findOrFail($f->id);
                $this->assertStatus($f, ['approved'], 'Dana ini sudah dicairkan.');   // cegah klik ganda

                $advance = $this->advanceAccount();
                $journal = $this->journals->create([
                    'journal_date' => $data['date'], 'type' => 'umum', 'reference' => $f->finance_ref ?: $f->procura_number,
                    'description' => "Pencairan dana pengadaan {$f->procura_number}".($f->request_title ? " — {$f->request_title}" : ''), 'created_by' => $user->id,
                ], [
                    ['account_id' => $advance->id, 'debit' => $amount, 'credit' => 0, 'description' => "Uang muka pengadaan {$f->procura_number}"],
                    ['account_id' => $cash->id, 'debit' => 0, 'credit' => $amount, 'description' => "Pencairan {$f->procura_number}"],
                ], true);
                $this->journals->post($journal, $user->id);

                $f->update([
                    'status' => 'disbursed', 'disbursed_amount' => $amount, 'disbursed_at' => Carbon::parse($data['date'])->setTimeFrom(now()),
                    'disbursement_method' => $data['method'], 'disbursement_ref' => $data['reference'] ?? null,
                    'cash_account_id' => $cash->id, 'disbursement_journal_id' => $journal->id,
                ]);

                // Terakhir: beri tahu PROCURA. Bila gagal, transaksi (termasuk jurnal) dibatalkan seluruhnya.
                $this->client->disburse($f->procura_number, array_filter([
                    'amount' => $amount, 'method' => $data['method'], 'reference' => $data['reference'] ?? null, 'disbursed_by' => $user->name,
                ], fn ($v) => $v !== null));

                ProcurementReport::record($f, 'keluar', 'pencairan', "Dana {$f->procura_number} dicairkan: Rp ".number_format($amount, 0, ',', '.'),
                    "Sumber: {$cash->name}\nMetode: {$data['method']}".(! empty($data['reference']) ? "\nReferensi: {$data['reference']}" : '')."\nJurnal: {$journal->journal_number}", null, null, $user->id);
                ActivityLogger::log('procurement.disbursed', "Mencairkan dana pengadaan {$f->procura_number} Rp ".number_format($amount, 0, ',', '.')." (jurnal {$journal->journal_number})", $f, $user->id);
            });
        } catch (ProcuraException $e) {
            if ($e->status === 422) {
                $this->syncOne($f);
            }
            throw $e;
        }
    }

    // =====================================================================
    //  Terima LPJ  (Dr Aset/Beban + Dr Kas-Bank (sisa) / Kr Uang Muka Pengadaan)
    // =====================================================================

    /** @param array{account_id:int,date:string,return_account_id?:?int,note?:?string} $data */
    public function bookSettlement(ProcurementFunding $f, array $data, User $user): void
    {
        $this->assertStatus($f, ['settled'], 'LPJ hanya bisa dijurnal setelah Pengadaan menyerahkannya.');
        if ($f->settlement_journal_id) {
            $this->fail('LPJ ini sudah dijurnal.');
        }

        $spent = round((float) $f->actual_spent, 2);
        $returned = round((float) $f->returned_amount, 2);
        $disbursed = round((float) $f->disbursed_amount, 2);
        if (abs($spent + $returned - $disbursed) > 0.01) {
            $this->fail('Angka LPJ tidak konsisten (terpakai + dikembalikan ≠ dana cair). Muat ulang dari Pengadaan atau minta Pengadaan memeriksanya.');
        }

        $expense = ChartOfAccount::postable()->whereIn('type', ['aset', 'beban'])->find($data['account_id']);
        if ($spent > 0 && ! $expense) {
            $this->fail('Pilih akun aset atau beban untuk pemakaian dana.');
        }

        $returnAccount = null;
        if ($returned > 0) {
            $returnAccount = ChartOfAccount::cashBank()->find($data['return_account_id'] ?? $f->cash_account_id);
            if (! $returnAccount) {
                $this->fail('Pilih akun Kas/Bank tempat sisa dana dikembalikan.');
            }
        }

        $journalNumber = DB::transaction(function () use ($f, $data, $user, $spent, $returned, $disbursed, $expense, $returnAccount) {
            $f = ProcurementFunding::lockForUpdate()->findOrFail($f->id);
            if ($f->settlement_journal_id) {
                $this->fail('LPJ ini sudah dijurnal.');
            }

            $lines = [];
            if ($spent > 0) {
                $lines[] = ['account_id' => $expense->id, 'debit' => $spent, 'credit' => 0, 'description' => "Pemakaian dana {$f->procura_number}".($f->request_title ? " — {$f->request_title}" : '')];
            }
            if ($returned > 0) {
                $lines[] = ['account_id' => $returnAccount->id, 'debit' => $returned, 'credit' => 0, 'description' => "Sisa dana dikembalikan {$f->procura_number}"];
            }
            $lines[] = ['account_id' => $this->advanceAccount()->id, 'debit' => 0, 'credit' => $disbursed, 'description' => "Penyelesaian uang muka {$f->procura_number}"];

            $journal = $this->journals->create([
                'journal_date' => $data['date'], 'type' => 'umum', 'reference' => $f->finance_ref ?: $f->procura_number,
                'description' => "LPJ dana pengadaan {$f->procura_number}".($f->request_title ? " — {$f->request_title}" : ''), 'created_by' => $user->id,
            ], $lines, true);
            $this->journals->post($journal, $user->id);

            $f->update([
                'status' => 'closed', 'settlement_journal_id' => $journal->id, 'settlement_account_id' => $expense?->id, 'settlement_booked_at' => now(),
            ]);

            ProcurementReport::record($f, 'keluar', 'lpj_dijurnal', "LPJ {$f->procura_number} diterima dan dijurnal",
                "Terpakai: Rp ".number_format($spent, 0, ',', '.')."\nDikembalikan ke kas: Rp ".number_format($returned, 0, ',', '.')."\nJurnal: {$journal->journal_number}".(! empty($data['note']) ? "\nCatatan: {$data['note']}" : ''), null, null, $user->id);
            ActivityLogger::log('procurement.settled', "Menjurnal LPJ dana pengadaan {$f->procura_number} (jurnal {$journal->journal_number})", $f, $user->id);

            return $journal->journal_number;
        });

        // Kabari Pengadaan (best-effort: jurnal sudah sah walau pemberitahuan gagal).
        try {
            $this->client->sendNote($f->procura_number, "LPJ {$f->procura_number} diterima Keuangan", "Sudah dijurnal ({$journalNumber}).".(! empty($data['note']) ? "\n{$data['note']}" : ''), $user->name);
        } catch (Throwable) {
            // diabaikan; tercatat di buku laporan lokal
        }
    }

    // =====================================================================
    //  Laporan / catatan
    // =====================================================================

    public function sendNote(?ProcurementFunding $f, string $subject, ?string $body, User $user): void
    {
        if ($f) {
            $this->client->sendNote($f->procura_number, $subject, $body, $user->name);
        }
        ProcurementReport::record($f, 'keluar', 'catatan', $subject, $body, null, null, $user->id);
    }

    // =====================================================================
    //  Pembantu
    // =====================================================================

    /** Akun "Uang Muka Pengadaan"; dibuat otomatis (di bawah Aset Lancar) bila belum ada. */
    public function advanceAccount(): ChartOfAccount
    {
        $cfg = config('procura.advance_account');
        $acc = ChartOfAccount::where('code', $cfg['code'])->first();

        if (! $acc) {
            $acc = ChartOfAccount::create([
                'code' => $cfg['code'], 'name' => $cfg['name'], 'type' => 'aset', 'category' => 'aset_lancar', 'normal_balance' => 'debit',
                'parent_id' => ChartOfAccount::where('code', '1-1000')->value('id'), 'is_active' => true, 'is_header' => false, 'is_cash_bank' => false,
                'description' => 'Dana yang dicairkan ke Pengadaan dan belum dipertanggungjawabkan (dibuat otomatis oleh integrasi PROCURA).',
            ]);
        }
        if ($acc->is_header || ! $acc->is_active) {
            $this->fail("Akun {$acc->code} {$acc->name} tidak bisa dipakai (header/nonaktif). Aktifkan atau ganti PROCURA_ADVANCE_ACCOUNT.");
        }

        return $acc;
    }

    /** Akun debit yang disarankan untuk LPJ menurut kategori. */
    public function suggestedAccountId(?string $category): ?int
    {
        $code = config("procura.category_accounts.{$category}");

        return $code ? ChartOfAccount::postable()->where('code', $code)->value('id') : null;
    }

    /** Pesan nomor dokumen Keuangan di baris pengajuan SEBELUM memanggil API (unique + coba ulang bila bentrok). */
    private function reserveRef(ProcurementFunding $f): string
    {
        if ($f->finance_ref) {
            return $f->finance_ref;
        }
        for ($i = 0; $i < 3; $i++) {
            try {
                $ref = ProcurementFunding::generateRef();
                $f->update(['finance_ref' => $ref]);

                return $ref;
            } catch (QueryException $e) {
                if ($i === 2) {
                    throw $e;
                }
            }
        }
        throw new \LogicException('unreachable');
    }

    private function assertStatus(ProcurementFunding $f, array $allowed, string $message): void
    {
        if (! in_array($f->status, $allowed, true)) {
            $this->fail($message.' (status sekarang: '.(config("procura.status.{$f->status}.0") ?? $f->status).')');
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['general' => $message]);
    }

    private function date(?string $iso): ?Carbon
    {
        return $iso ? Carbon::parse($iso) : null;
    }
}
