<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\DisbursementBatch;
use App\Models\Employee;
use App\Models\PayrollFinding;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * File transfer gaji massal & rekonsiliasi hasil bank. SHA-256 file dicatat di jejak audit dan dikirim ke Keuangan,
 * sehingga file yang diunggah ke bank bisa dicocokkan (file yang diubah setelah dibuat = hash beda).
 * Format CSV generik; sesuaikan kolom dengan template bank/H2H yang dipakai perusahaan.
 */
class BankFileService
{
    public function generate(PayrollRun $run, User $by): DisbursementBatch
    {
        if (! in_array($run->status, ['funded', 'disbursing'], true)) {
            throw new WorkflowException('File transfer hanya bisa dibuat setelah dana disetujui Keuangan.');
        }
        $items = $run->items()->with('employee')->where('is_held', false)->where('status', 'calculated')->orderBy('id')->get();
        if ($items->isEmpty()) {
            throw new WorkflowException('Tidak ada item yang bisa ditransfer.');
        }

        $fh = fopen('php://temp', 'w+');
        fputcsv($fh, ['no', 'nip', 'nama_rekening', 'bank', 'no_rekening', 'nominal', 'berita', 'ref']);
        $n = 0;
        $total = 0.0;
        foreach ($items as $i) {
            $e = $i->employee;
            if (! $e->bank_account_number || Employee::hashAccount($e->bank_account_number) !== $i->bank_account_hash) {
                throw new WorkflowException("Rekening {$e->full_name} berbeda dari saat gaji dihitung atau kosong. Hitung ulang/tahan item tersebut.");
            }
            fputcsv($fh, [++$n, $e->nip, $e->bank_account_name ?: $e->full_name, $e->bank_name, preg_replace('/\D+/', '', $e->bank_account_number), number_format($i->net_pay, 2, '.', ''), 'Gaji '.$run->periodLabel(), $i->id]);
            $total += $i->net_pay;
        }
        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        if (abs($total - (float) $run->total_disbursable) > 0.01) {
            throw new WorkflowException('Total file tidak sama dengan total yang boleh ditransfer. Hitung ulang total (tahan/lepas item).');
        }

        $seq = $run->batches()->count() + 1;
        $number = 'BNK-'.$run->run_number.'-'.$seq;
        $path = 'disbursement/'.$number.'.csv';
        Storage::disk('local')->put($path, $csv);
        $sha = hash('sha256', $csv);

        $b = DisbursementBatch::create(['payroll_run_id' => $run->id, 'batch_number' => $number, 'bank_code' => 'ALL', 'item_count' => $n, 'total_amount' => $total,
            'file_path' => $path, 'file_sha256' => $sha, 'status' => 'generated', 'generated_by' => $by->id, 'generated_at' => now()]);
        $run->update(['status' => 'disbursing']);
        AuditLedger::record('bank.generated', $b, ['run' => $run->run_number, 'items' => $n, 'total' => $total, 'sha256' => $sha], $number, $by->id);

        return $b;
    }

    public function content(DisbursementBatch $b): string
    {
        $csv = Storage::disk('local')->get($b->file_path);
        if (! hash_equals($b->file_sha256, hash('sha256', (string) $csv))) {
            AuditLedger::record('bank.file_tampered', $b, ['expected' => $b->file_sha256], $b->batch_number);
            throw new WorkflowException('File batch '.$b->batch_number.' tidak lagi cocok dengan hash yang dicatat (file diubah di server). Buat batch baru dan laporkan ke Auditor.');
        }

        return (string) $csv;
    }

    /** @return array{matched:int,failed:int,mismatches:array<int,array>} */
    public function reconcile(DisbursementBatch $b, string $csv, User $by): array
    {
        $run = $b->run;
        $expected = $run->items()->where('is_held', false)->get()->keyBy(fn ($i) => (string) ($i->employee_snapshot['nip'] ?? ''));
        $rows = array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim($csv)));
        $head = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($rows) ?: []);
        foreach (['nip', 'amount', 'status'] as $need) {
            if (! in_array($need, $head, true)) {
                throw new WorkflowException("Kolom '{$need}' tidak ada di file hasil bank. Kolom yang dibutuhkan: nip, amount, status, reference.");
            }
        }

        $tol = (float) config('pay.controls.reconcile_tolerance', 0) + 0.005;
        $mismatch = [];
        $seen = [];
        $matched = 0;
        $failed = 0;

        foreach ($rows as $r) {
            if (count($r) < count($head)) {
                continue;
            }
            $row = array_combine($head, array_slice($r, 0, count($head)));
            $nip = trim($row['nip']);
            $amount = (float) str_replace(',', '', $row['amount']);
            $ok = in_array(strtoupper(trim($row['status'])), ['SUCCESS', 'SUKSES', 'BERHASIL', 'OK', 'S'], true);
            $item = $expected[$nip] ?? null;

            if (! $item) {
                $mismatch[] = ['type' => 'unexpected', 'nip' => $nip, 'detail' => 'NIP tidak ada di batch ini (transfer ke pihak tak terdaftar).', 'amount' => $amount];
                continue;
            }
            $seen[$nip] = true;
            if (abs($amount - $item->net_pay) > $tol) {
                $mismatch[] = ['type' => 'amount', 'nip' => $nip, 'detail' => 'Nominal bank Rp '.number_format($amount, 0, ',', '.').' ≠ gaji bersih Rp '.number_format($item->net_pay, 0, ',', '.'), 'amount' => $amount];
                continue;
            }
            if ($ok) {
                $item->update(['status' => 'paid', 'paid_at' => now(), 'payment_ref' => mb_substr((string) ($row['reference'] ?? ''), 0, 80)]);
                $matched++;
            } else {
                $item->update(['status' => 'failed', 'payment_note' => 'Gagal di bank']);
                $failed++;
                $mismatch[] = ['type' => 'failed', 'nip' => $nip, 'detail' => 'Transfer gagal di bank.', 'amount' => $amount];
            }
        }
        foreach ($expected as $nip => $item) {
            if (! isset($seen[$nip])) {
                $mismatch[] = ['type' => 'missing', 'nip' => $nip, 'detail' => 'Tidak ada di hasil bank (belum ditransfer).', 'amount' => $item->net_pay];
            }
        }

        $clean = empty($mismatch);
        $b->update(['status' => $clean ? 'reconciled' : 'mismatch', 'reconciled_by' => $by->id, 'reconciled_at' => now(), 'reconciliation' => ['matched' => $matched, 'failed' => $failed, 'mismatches' => $mismatch]]);
        AuditLedger::record('bank.reconciled', $b, ['matched' => $matched, 'mismatches' => count($mismatch)], $b->batch_number, $by->id);

        if (! $clean) {
            PayrollFinding::updateOrCreate(['payroll_run_id' => $run->id, 'fingerprint' => sha1('RECON_MISMATCH'.$b->id)], [
                'code' => 'RECON_MISMATCH', 'severity' => 'critical', 'is_blocking' => false, 'title' => count($mismatch).' selisih pada rekonsiliasi bank '.$b->batch_number,
                'detail' => 'Hasil bank tidak sama dengan gaji yang disetujui. Selidiki sebelum menutup putaran.', 'context' => ['batch' => $b->batch_number], 'status' => 'open',
            ]);
        } elseif ($run->items()->where('is_held', false)->where('status', '!=', 'paid')->doesntExist()) {
            $run->update(['status' => 'closed', 'closed_at' => now()]);
            $run->funding?->update(['status' => 'confirmed']);
            AuditLedger::record('run.closed', $run, ['paid_items' => $matched], $run->run_number, $by->id);
        }

        return ['matched' => $matched, 'failed' => $failed, 'mismatches' => $mismatch];
    }
}
