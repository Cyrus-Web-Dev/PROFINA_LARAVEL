<?php

namespace App\Services;

use App\Models\Journal;
use App\Models\JournalDetail;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalService
{
    public function __construct(private PeriodService $periods)
    {
    }

    /**
     * Simpan jurnal baru (status draft) beserta baris-barisnya.
     * $lines = [['account_id' => .., 'debit' => .., 'credit' => .., 'description' => ..], ...]
     * $autoApprove: true untuk jurnal yang dihasilkan otomatis dari transaksi terdokumentasi
     * (invoice, bill, pembayaran, transfer, depresiasi) - transaksi ini punya audit trail sendiri
     * (nomor invoice/bill/aset) sehingga tidak perlu approval berjenjang seperti jurnal manual.
     */
    public function create(array $header, array $lines, bool $autoApprove = false): Journal
    {
        $this->assertBalanced($lines);
        $this->periods->assertOpen($header['journal_date'] ?? null);

        return DB::transaction(function () use ($header, $lines, $autoApprove) {
            $journal = Journal::create([
                ...$header,
                'journal_number' => $header['journal_number'] ?? Journal::generateJournalNumber($header['type'] ?? 'umum'),
                'status' => 'draft',
            ]);

            foreach ($lines as $order => $line) {
                JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                    'line_order' => $order,
                ]);
            }

            $journal->load('details');
            $journal->recalculateTotals();

            // Tandai butuh approval jika total di atas threshold (lihat config/mkp.php)
            if (! $autoApprove && $journal->requiresApproval()) {
                $journal->update(['approval_status' => 'pending']);
            }

            return $journal;
        });
    }

    /**
     * Posting jurnal: mengunci jurnal agar tidak bisa diubah, dan saldo resmi masuk buku besar.
     */
    public function post(Journal $journal, int $userId): Journal
    {
        if ($journal->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya jurnal draft yang dapat diposting.']);
        }

        $this->periods->assertOpen($journal->journal_date);

        if ($journal->approval_status === 'pending') {
            throw ValidationException::withMessages(['approval' => 'Jurnal ini melebihi batas nilai dan wajib disetujui (approval) sebelum dapat diposting.']);
        }
        if ($journal->approval_status === 'rejected') {
            throw ValidationException::withMessages(['approval' => 'Jurnal ini telah ditolak dan tidak dapat diposting.']);
        }

        $journal->load('details');
        if (! $journal->isBalanced()) {
            throw ValidationException::withMessages(['balance' => 'Total debit dan kredit tidak seimbang. Jurnal tidak dapat diposting.']);
        }

        $journal->update([
            'status' => 'posted',
            'posted_by' => $userId,
            'posted_at' => now(),
        ]);

        ActivityLogger::log('journal.posted', "Memposting jurnal {$journal->journal_number} senilai Rp".number_format($journal->total_debit, 0, ',', '.'), $journal, $userId);

        return $journal;
    }

    /**
     * Setujui jurnal yang menunggu approval (di atas threshold). Setelah disetujui, jurnal boleh diposting.
     */
    public function approve(Journal $journal, int $approverId): Journal
    {
        if ($journal->approval_status !== 'pending') {
            throw ValidationException::withMessages(['approval' => 'Jurnal ini tidak dalam status menunggu approval.']);
        }

        $journal->update([
            'approval_status' => 'approved',
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);

        ActivityLogger::log('journal.approved', "Menyetujui jurnal {$journal->journal_number} untuk diposting", $journal, $approverId);

        return $journal;
    }

    /**
     * Tolak jurnal yang menunggu approval. Jurnal yang ditolak tidak dapat diposting.
     */
    public function reject(Journal $journal, int $approverId, ?string $reason = null): Journal
    {
        if ($journal->approval_status !== 'pending') {
            throw ValidationException::withMessages(['approval' => 'Jurnal ini tidak dalam status menunggu approval.']);
        }

        $journal->update([
            'approval_status' => 'rejected',
            'approved_by' => $approverId,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        ActivityLogger::log('journal.rejected', "Menolak jurnal {$journal->journal_number}".($reason ? ": {$reason}" : ''), $journal, $approverId);

        return $journal;
    }

    /**
     * Batalkan jurnal (void). Jurnal yang sudah posted tidak dihapus demi audit trail,
     * melainkan ditandai void agar tidak dihitung dalam laporan.
     */
    public function void(Journal $journal): Journal
    {
        if ($journal->status !== 'posted') {
            throw ValidationException::withMessages(['status' => 'Hanya jurnal yang sudah diposting yang bisa di-void.']);
        }

        $this->periods->assertOpen($journal->journal_date);

        // Jurnal otomatis dari Invoice / Bill / Pembayaran / Aset Tetap tidak boleh di-void langsung:
        // dokumen sumbernya masih tercatat "aktif" sehingga buku pembantu (piutang/utang/aset)
        // menjadi tidak cocok dengan buku besar. Batalkan dokumen sumbernya (ia akan membuat
        // jurnal pembalik), atau untuk jurnal biasa gunakan Jurnal Pembalik.
        if ($this->isDocumentJournal($journal)) {
            throw ValidationException::withMessages(['status' => 'Jurnal ini dibuat otomatis oleh sebuah transaksi (invoice/bill/pembayaran/aset tetap/pembalik). Batalkan transaksi sumbernya, bukan jurnalnya.']);
        }

        $journal->update(['status' => 'void']);
        ActivityLogger::log('journal.voided', "Membatalkan (void) jurnal {$journal->journal_number}", $journal);

        return $journal;
    }

    private function isDocumentJournal(Journal $journal): bool
    {
        if ($journal->type === 'pembalik') {
            return true;
        }

        foreach ([
            ['invoices', 'journal_id'], ['invoice_payments', 'journal_id'],
            ['bills', 'journal_id'], ['bill_payments', 'journal_id'],
            ['fixed_assets', 'acquisition_journal_id'], ['fixed_asset_depreciations', 'journal_id'],
        ] as [$table, $column]) {
            if (DB::table($table)->where($column, $journal->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Buat jurnal PEMBALIK (reversing entry) dari jurnal yang sudah posted: baris debit dan
     * kredit ditukar. Dipakai saat membatalkan transaksi yang sudah tercatat (invoice/bill/dst)
     * TANPA mengedit histori asli - prinsip dasar audit trail akuntansi.
     */
    public function reverse(Journal $original, string $date, int $userId, ?string $note = null): Journal
    {
        if ($original->status !== 'posted') {
            throw ValidationException::withMessages(['status' => 'Hanya jurnal yang sudah posted yang bisa dibalik (reverse).']);
        }

        $lines = $original->details->map(fn ($d) => [
            'account_id' => $d->account_id,
            'debit' => (float) $d->credit,
            'credit' => (float) $d->debit,
            'description' => 'Pembalik: '.($d->description ?? ''),
        ])->toArray();

        $journal = $this->create([
            'journal_date' => $date,
            'type' => 'pembalik',
            'reference' => $original->journal_number,
            'description' => $note ?? "Jurnal pembalik dari {$original->journal_number}",
            'created_by' => $userId,
        ], $lines, true); // autoApprove: jurnal pembalik sistem, bukan input manual

        return $this->post($journal, $userId);
    }

    /**
     * Hapus jurnal yang MASIH DRAFT (belum pernah posted). Aman karena belum pernah
     * masuk buku besar - berbeda dengan void yang untuk jurnal yang SUDAH posted.
     */
    public function deleteDraft(Journal $journal): void
    {
        if ($journal->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya jurnal berstatus draft yang bisa dihapus. Jurnal yang sudah posted harus di-void, bukan dihapus.']);
        }

        $journal->details()->delete();
        $journal->delete();
    }

    /**
     * Perbarui isi jurnal yang MASIH DRAFT (ganti seluruh baris & header). Tidak berlaku
     * untuk jurnal yang sudah posted.
     */
    public function updateDraft(Journal $journal, array $header, array $lines): Journal
    {
        if ($journal->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Hanya jurnal berstatus draft yang bisa diedit.']);
        }

        $this->periods->assertOpen($header['journal_date'] ?? $journal->journal_date);

        $this->assertBalanced($lines);

        return DB::transaction(function () use ($journal, $header, $lines) {
            $journal->update($header);
            $journal->details()->delete();

            foreach ($lines as $order => $line) {
                JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                    'line_order' => $order,
                ]);
            }

            $journal->load('details');
            $journal->recalculateTotals();

            // Nilai mungkin berubah - evaluasi ulang apakah butuh approval
            $journal->update(['approval_status' => $journal->requiresApproval() ? 'pending' : 'not_required']);

            return $journal;
        });
    }

    private function assertBalanced(array $lines): void
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages(['lines' => 'Jurnal minimal memiliki 2 baris (debit dan kredit).']);
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach (array_values($lines) as $i => $line) {
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);
            $no = $i + 1;

            if ($debit > 0 && $credit > 0) {
                throw ValidationException::withMessages(['lines' => "Baris {$no}: isi salah satu saja, debit ATAU kredit."]);
            }
            if ($debit <= 0 && $credit <= 0) {
                throw ValidationException::withMessages(['lines' => "Baris {$no}: debit atau kredit harus diisi lebih dari 0."]);
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw ValidationException::withMessages(['balance' => 'Total debit ('.number_format($totalDebit, 2).') tidak sama dengan total kredit ('.number_format($totalCredit, 2).').']);
        }
    }
}
