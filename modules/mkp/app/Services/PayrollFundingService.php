<?php

namespace App\Services;

use App\Exceptions\PayrollException;
use App\Models\ChartOfAccount;
use App\Models\PayrollFunding;
use App\Models\PayrollRemittance;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sisi Keuangan dari alur payroll (PAYBRIDGE):
 *
 *   Pengajuan masuk → SETUJUI (jurnal akrual) → [Payroll membuat file transfer] → CAIRKAN (jurnal kas) → SETOR pajak/BPJS
 *
 * Pembukuan (semua jurnal otomatis & langsung diposting, seperti modul lain):
 *   Setujui : Dr Beban Gaji/Tunjangan/Lembur/Bonus/THR/BPJS perusahaan   Kr Utang Gaji, Utang PPh 21, Utang BPJS, Piutang Karyawan (kasbon), Utang potongan
 *   Cairkan : Dr Utang Gaji                                              Kr Kas/Bank
 *   Setor   : Dr Utang PPh 21 / BPJS                                     Kr Kas/Bank
 * Keuangan hanya menerima TOTAL per akun - bukan gaji per orang. Nominal pencairan DIKUNCI sama dengan total transfer di file bank.
 */
class PayrollFundingService
{
    private const RANK = ['submitted' => 1, 'revision' => 1, 'rejected' => 1, 'approved' => 2, 'disbursed' => 3, 'closed' => 4, 'cancelled' => 9];

    public function __construct(private PayrollClient $client, private JournalService $journals)
    {
    }

    // ---------------------------------------------------------------- sinkron

    public function syncAll(?string $updatedSince = null): int
    {
        $count = 0;
        $page = 1;
        do {
            $res = $this->client->listFunding(['submitted', 'approved', 'revision', 'rejected', 'disbursed', 'confirmed', 'cancelled'], $page, $updatedSince);
            foreach ($res['data'] as $remote) {
                $this->upsert($remote) && $count++;
            }
            $last = (int) ($res['meta']['last_page'] ?? 1);
            $page++;
        } while ($page <= $last && $page <= 20);

        foreach ($this->client->remittances() as $r) {
            $this->upsertRemittance($r);
        }

        return $count;
    }

    public function syncOne(PayrollFunding $f): bool
    {
        try {
            $this->upsert($this->client->find($f->payroll_number));

            return true;
        } catch (PayrollException $e) {
            $f->update(['last_error' => $e->getMessage()]);

            return false;
        }
    }

    public function upsert(array $r): ?PayrollFunding
    {
        if (empty($r['number']) || empty($r['status'])) {
            return null;
        }
        $f = PayrollFunding::firstOrNew(['payroll_number' => $r['number']]);
        $remote = $r['status'] === 'confirmed' ? 'closed' : $r['status'];
        // jangan mundur: bila lokal sudah lebih maju (mis. baru dicairkan, remote belum menyusul) pertahankan lokal
        $status = $f->exists && (self::RANK[$f->status] ?? 0) > (self::RANK[$remote] ?? 0) && $remote !== 'cancelled' ? $f->status : $remote;

        $f->fill([
            'run_number' => $r['run_number'], 'run_type' => $r['run_type'] ?? 'regular', 'title' => $r['title'] ?? null, 'period_year' => $r['period_year'], 'period_month' => $r['period_month'],
            'pay_date' => $r['pay_date'] ?? null, 'status' => $status, 'employee_count' => $r['employee_count'] ?? 0,
            'amount_net_total' => $r['amount_net_total'], 'amount_disbursable' => $r['amount_disbursable'], 'breakdown' => $r['breakdown'] ?? ['debit' => [], 'credit' => []],
            'bank_batches' => $r['bank_batches'] ?? [], 'snapshot_hash' => $r['snapshot_hash'] ?? null, 'snapshot' => $r, 'synced_at' => now(), 'last_error' => null,
            'finance_ref' => $f->finance_ref ?: ($r['decision']['finance_ref'] ?? null),
        ])->save();

        foreach ($r['remittances'] ?? [] as $rem) {
            $this->upsertRemittance($rem + ['run_number' => $r['run_number'], 'period_year' => $r['period_year'], 'period_month' => $r['period_month']]);
        }

        return $f;
    }

    public function upsertRemittance(array $r): ?PayrollRemittance
    {
        if (empty($r['id']) || empty($r['type'])) {
            return null;
        }
        $m = PayrollRemittance::firstOrNew(['remote_id' => $r['id']]);
        if ($m->exists && $m->status === 'paid') {
            return $m;
        }
        $m->fill(['type' => $r['type'], 'run_number' => $r['run_number'] ?? $m->run_number, 'period_year' => $r['period_year'] ?? $m->period_year, 'period_month' => $r['period_month'] ?? $m->period_month,
            'amount' => $r['amount'], 'due_date' => $r['due_date'], 'report_due_date' => $r['report_due_date'] ?? null, 'status' => $r['status'] ?? 'pending', 'payment_ref' => $r['payment_ref'] ?? $m->payment_ref])->save();

        return $m;
    }

    // ---------------------------------------------------------------- keputusan + jurnal akrual

    /** @param 'approve'|'reject'|'revision' $decision */
    public function decide(PayrollFunding $f, string $decision, ?string $note, ?string $journalDate, User $user): void
    {
        $this->assertStatus($f, ['submitted'], 'Pengajuan ini tidak sedang menunggu keputusan.');
        if (! in_array($decision, ['approve', 'reject', 'revision'], true)) {
            $this->fail('Pilih keputusan: setujui, tolak, atau minta revisi.');
        }
        if ($decision !== 'approve' && blank($note)) {
            $this->fail('Isi alasan/catatan agar Payroll tahu apa yang harus diperbaiki.');
        }
        if ($decision === 'approve' && $f->amount_net_total >= (float) config('payroll.approval_threshold') && ! $user->can('journal.approve')) {
            $this->fail('Pengajuan ≥ Rp '.number_format((float) config('payroll.approval_threshold'), 0, ',', '.').' hanya boleh disetujui pemegang hak "Setujui jurnal bernilai besar" (Manager).');
        }
        $ref = $f->finance_ref ?: 'KPY-'.now()->format('ym').'-'.str_pad((string) $f->id, 4, '0', STR_PAD_LEFT);

        if ($decision !== 'approve') {
            $this->callPayroll(fn () => $this->client->decide($f->payroll_number, ['decision' => $decision, 'note' => $note, 'decided_by' => $user->name, 'finance_ref' => $ref]), $f);
            $f->update(['status' => $decision === 'reject' ? 'rejected' : 'revision', 'decided_by' => $user->id, 'decided_by_name' => $user->name, 'decided_at' => now(), 'decision_note' => $note, 'finance_ref' => $ref]);
            ActivityLogger::log('payroll.'.$decision, "Keputusan dana payroll {$f->payroll_number}: {$decision}", $f, $user->id);

            return;
        }

        $date = $journalDate ?: ($f->pay_date?->toDateString() ?? now()->toDateString());
        $lines = $this->accrualLines($f);

        DB::transaction(function () use ($f, $user, $ref, $date, $lines, $note) {
            $f = PayrollFunding::lockForUpdate()->findOrFail($f->id);
            $this->assertStatus($f, ['submitted'], 'Pengajuan ini sudah diputuskan.');

            $journal = $this->journals->create([
                'journal_date' => $date, 'type' => 'umum', 'reference' => $ref,
                'description' => "Akrual {$f->title} ({$f->run_number}) — {$f->employee_count} karyawan", 'created_by' => $user->id,
            ], $lines, true);
            $this->journals->post($journal, $user->id);

            $f->update(['status' => 'approved', 'approved_amount' => $f->amount_net_total, 'decided_by' => $user->id, 'decided_by_name' => $user->name, 'decided_at' => now(),
                'decision_note' => $note, 'finance_ref' => $ref, 'accrual_journal_id' => $journal->id]);

            // Terakhir: beri tahu PAYBRIDGE. Bila gagal, seluruh transaksi (termasuk jurnal) dibatalkan.
            $this->callPayroll(fn () => $this->client->decide($f->payroll_number, ['decision' => 'approve', 'approved_amount' => $f->amount_net_total, 'note' => $note, 'decided_by' => $user->name, 'finance_ref' => $ref]), $f);
            ActivityLogger::log('payroll.approve', "Menyetujui dana payroll {$f->payroll_number} Rp ".number_format($f->amount_net_total, 0, ',', '.')." (jurnal {$journal->journal_number})", $f, $user->id);
        });
    }

    /** @return array<int,array{account_id:int,debit:float,credit:float,description:string}> */
    private function accrualLines(PayrollFunding $f): array
    {
        $b = $f->breakdown;
        $lines = [];
        $debit = 0.0;
        $credit = 0.0;
        foreach (['debit' => $b['debit'] ?? [], 'credit' => $b['credit'] ?? []] as $side => $items) {
            foreach ($items as $key => $amount) {
                $amount = round((float) $amount, 2);
                if (abs($amount) < 0.005) {
                    continue;
                }
                $acc = $this->account($key);
                $isDebit = ($side === 'debit') === ($amount > 0);   // nominal negatif berpindah sisi (mis. PPh 21 lebih potong)
                $abs = abs($amount);
                $lines[] = ['account_id' => $acc->id, 'debit' => $isDebit ? $abs : 0, 'credit' => $isDebit ? 0 : $abs, 'description' => $acc->name.' — '.$f->run_number];
                $isDebit ? $debit += $abs : $credit += $abs;
            }
        }
        if (! $lines || abs($debit - $credit) > 1) {
            $this->fail('Rincian jurnal dari Payroll tidak seimbang (debit Rp '.number_format($debit, 2, ',', '.').' vs kredit Rp '.number_format($credit, 2, ',', '.').'). Minta Payroll memeriksa.');
        }

        return $lines;
    }

    // ---------------------------------------------------------------- pencairan (nominal terkunci)

    /** @param array{cash_account_id:int,date:string,method:string,reference?:?string} $data */
    public function disburse(PayrollFunding $f, array $data, User $user): void
    {
        $this->assertStatus($f, ['approved'], 'Dana hanya bisa dicairkan untuk pengajuan yang sudah disetujui.');
        $this->syncOne($f);          // pastikan data file bank terbaru
        $f->refresh();

        if (empty($f->bank_batches)) {
            $this->fail('Payroll belum membuat file transfer bank. Minta Payroll membuat batch transfer dulu, lalu muat ulang.');
        }
        $amount = round((float) $f->amount_disbursable, 2);   // DIKUNCI: sama dengan total file bank
        $batchTotal = round((float) collect($f->bank_batches)->sum('total_amount'), 2);
        if (abs($batchTotal - $amount) > 0.01) {
            $this->fail('Total file transfer (Rp '.number_format($batchTotal, 0, ',', '.').') tidak sama dengan nominal yang boleh dicairkan (Rp '.number_format($amount, 0, ',', '.').'). Jangan cairkan; hubungi Payroll.');
        }
        $cash = ChartOfAccount::cashBank()->find($data['cash_account_id']);
        if (! $cash) {
            $this->fail('Pilih akun Kas/Bank sumber dana yang aktif.');
        }
        $saldo = $cash->getBalance(null, $data['date']);
        if ($saldo + 0.005 < $amount) {
            $this->fail("Saldo {$cash->name} per ".Carbon::parse($data['date'])->format('d/m/Y').' hanya Rp '.number_format($saldo, 0, ',', '.').', tidak cukup untuk Rp '.number_format($amount, 0, ',', '.').'.');
        }

        DB::transaction(function () use ($f, $data, $user, $amount, $cash) {
            $f = PayrollFunding::lockForUpdate()->findOrFail($f->id);
            $this->assertStatus($f, ['approved'], 'Dana ini sudah dicairkan.');

            $journal = $this->journals->create([
                'journal_date' => $data['date'], 'type' => 'umum', 'reference' => $f->finance_ref ?: $f->payroll_number,
                'description' => "Pembayaran gaji {$f->title} ({$f->run_number})", 'created_by' => $user->id,
            ], [
                ['account_id' => $this->account('net_pay_liability')->id, 'debit' => $amount, 'credit' => 0, 'description' => "Pelunasan utang gaji {$f->run_number}"],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => $amount, 'description' => "Transfer gaji {$f->run_number}"],
            ], true);
            $this->journals->post($journal, $user->id);

            $f->update(['status' => 'disbursed', 'disbursed_amount' => $amount, 'disbursed_at' => Carbon::parse($data['date'])->setTimeFrom(now()), 'disbursement_method' => $data['method'],
                'disbursement_ref' => $data['reference'] ?? null, 'cash_account_id' => $cash->id, 'disbursement_journal_id' => $journal->id]);

            $this->callPayroll(fn () => $this->client->disburse($f->payroll_number, array_filter([
                'amount' => $amount, 'reference' => $data['reference'] ?? null, 'disbursed_by' => $user->name, 'journal_refs' => ['accrual' => $f->accrual_journal_id, 'disbursement' => $journal->journal_number],
            ], fn ($v) => $v !== null)), $f);
            ActivityLogger::log('payroll.disbursed', "Mencairkan gaji {$f->payroll_number} Rp ".number_format($amount, 0, ',', '.')." (jurnal {$journal->journal_number})", $f, $user->id);
        });
    }

    // ---------------------------------------------------------------- setoran pajak/BPJS

    /** @param array{cash_account_id:int,date:string,reference:string} $data */
    public function payRemittance(PayrollRemittance $r, array $data, User $user): void
    {
        if ($r->status === 'paid') {
            $this->fail('Setoran ini sudah dibayar.');
        }
        $cash = ChartOfAccount::cashBank()->find($data['cash_account_id']);
        if (! $cash) {
            $this->fail('Pilih akun Kas/Bank sumber dana yang aktif.');
        }
        $amount = round((float) $r->amount, 2);
        $saldo = $cash->getBalance(null, $data['date']);
        if ($saldo + 0.005 < $amount) {
            $this->fail("Saldo {$cash->name} hanya Rp ".number_format($saldo, 0, ',', '.').'; tidak cukup untuk Rp '.number_format($amount, 0, ',', '.').'.');
        }
        $label = config("payroll.remittance_labels.{$r->type}");

        DB::transaction(function () use ($r, $data, $user, $cash, $amount, $label) {
            $r = PayrollRemittance::lockForUpdate()->findOrFail($r->id);
            if ($r->status === 'paid') {
                $this->fail('Setoran ini sudah dibayar.');
            }
            $journal = $this->journals->create([
                'journal_date' => $data['date'], 'type' => 'umum', 'reference' => $data['reference'],
                'description' => "Setor {$label} masa {$r->period_month}/{$r->period_year} ({$r->run_number})", 'created_by' => $user->id,
            ], [
                ['account_id' => $this->account(config("payroll.remittance_accounts.{$r->type}"))->id, 'debit' => $amount, 'credit' => 0, 'description' => "Pelunasan utang {$label}"],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => $amount, 'description' => "Setoran {$label} — {$data['reference']}"],
            ], true);
            $this->journals->post($journal, $user->id);
            $r->update(['status' => 'paid', 'paid_at' => now(), 'payment_ref' => $data['reference'], 'cash_account_id' => $cash->id, 'journal_id' => $journal->id, 'paid_by' => $user->id]);

            $this->callPayroll(fn () => $this->client->remittancePaid($r->remote_id, ['paid_by' => $user->name, 'reference' => $data['reference'], 'journal_ref' => $journal->journal_number]), null);
            ActivityLogger::log('payroll.remittance', "Menyetor {$label} Rp ".number_format($amount, 0, ',', '.')." (jurnal {$journal->journal_number})", $r, $user->id);
        });
    }

    // ---------------------------------------------------------------- anggaran SDM dari PAYBRIDGE

    /** @param array{fiscal_year:int,lines:array<int,array{gl_key:string,month:int,amount:float}>} $payload */
    public function applyBudget(array $payload): int
    {
        $svc = app(BudgetService::class);
        $n = 0;
        foreach ($payload['lines'] ?? [] as $l) {
            $key = config("payroll.budget_map.{$l['gl_key']}", $l['gl_key']);
            if (! config("payroll.accounts.$key")) {
                continue;
            }
            $svc->setBudget($this->account($key)->id, (int) $payload['fiscal_year'], (int) $l['month'], (float) $l['amount']);
            $n++;
        }
        ActivityLogger::log('payroll.budget', "Anggaran SDM {$payload['fiscal_year']} (v".($payload['version'] ?? '?').") dari PAYBRIDGE diterapkan: {$n} baris", null, null);

        return $n;
    }

    // ---------------------------------------------------------------- pembantu

    /** Akun buku besar untuk kunci semantik PAYBRIDGE; dibuat otomatis bila belum ada. */
    public function account(string $key): ChartOfAccount
    {
        $cfg = config("payroll.accounts.$key");
        if (! $cfg) {
            $this->fail("Kunci akun '$key' dari Payroll tidak dikenal di config/payroll.php.");
        }
        [$code, $name, $type, $category, $normal, $parent] = $cfg;
        $acc = ChartOfAccount::where('code', $code)->first();
        if (! $acc) {
            $acc = ChartOfAccount::create([
                'code' => $code, 'name' => $name, 'type' => $type, 'category' => $category, 'normal_balance' => $normal,
                'parent_id' => $parent ? ChartOfAccount::where('code', $parent)->value('id') : null, 'is_active' => true, 'is_header' => false, 'is_cash_bank' => false,
                'description' => 'Dibuat otomatis oleh integrasi PAYBRIDGE.',
            ]);
        }
        if ($acc->is_header || ! $acc->is_active) {
            $this->fail("Akun {$acc->code} {$acc->name} tidak bisa dipakai (header/nonaktif). Aktifkan atau ubah pemetaan di config/payroll.php.");
        }

        return $acc;
    }

    private function callPayroll(callable $fn, ?PayrollFunding $f): mixed
    {
        try {
            return $fn();
        } catch (PayrollException $e) {
            if ($e->status === 422 && $f) {
                $this->syncOne($f);   // mungkin sudah diputuskan di tempat lain
            }
            throw $e;
        }
    }

    private function assertStatus(PayrollFunding $f, array $allowed, string $message): void
    {
        if (! in_array($f->status, $allowed, true)) {
            $this->fail($message);
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['payroll' => $message]);
    }
}
