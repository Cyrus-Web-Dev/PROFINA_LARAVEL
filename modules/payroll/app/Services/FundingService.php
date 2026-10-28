<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\FundingRequest;
use App\Models\IntegrationMessage;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Jembatan Payroll -> Keuangan (MKP). Pola sama dengan pengajuan dana PROCURA -> MKP:
 * Payroll mengajukan, Keuangan memutuskan & mencairkan (jurnal dibuat di MKP), Payroll diberi tahu lewat webhook/REST API.
 * Keuangan hanya melihat TOTAL & rincian per kunci buku besar - bukan gaji per orang.
 */
class FundingService
{
    public function __construct(private WebhookService $webhooks, private PayslipService $payslips)
    {
    }

    public function submit(PayrollRun $run, ?User $by = null): FundingRequest
    {
        $breakdown = $this->breakdown($run);
        $debit = array_sum($breakdown['debit']);
        $credit = array_sum($breakdown['credit']);
        if (abs($debit - $credit) > 1) {
            throw ValidationException::withMessages(['funding' => 'Rincian jurnal tidak seimbang (debit '.number_format($debit, 2).' vs kredit '.number_format($credit, 2).'). Periksa komponen gaji.']);
        }

        $f = FundingRequest::updateOrCreate(['payroll_run_id' => $run->id], [
            'number' => 'PFD-'.$run->run_number, 'status' => 'submitted', 'breakdown' => $breakdown,
            'amount_net_total' => $run->total_net, 'amount_disbursable' => $run->total_disbursable,
            'amount_approved' => null, 'decided_by_name' => null, 'decided_at' => null, 'decision_note' => null,
        ]);
        $run->update(['status' => 'funding']);
        AuditLedger::record('funding.submitted', $f, ['run' => $run->run_number, 'net_total' => $run->total_net, 'disbursable' => $run->total_disbursable], $f->number, $by?->id);

        $this->webhooks->dispatch('funding.submitted', $this->payload($f->fresh()));
        $f->update(['delivered_at' => now()]);

        return $f;
    }

    /** @return array{debit:array<string,float>,credit:array<string,float>,meta:array} */
    public function breakdown(PayrollRun $run): array
    {
        $t = $run->totals ?? [];
        $b = $t['bpjs'] ?? [];
        $debit = [];
        foreach (($t['by_gl'] ?? []) as $gl => $amt) {
            if (abs($amt) > 0.005) {
                $debit[$gl] = round((float) $amt, 2);
            }
        }
        $kesCo = (float) ($b['kes_company'] ?? 0);
        $tkCo = (float) (($b['jht_company'] ?? 0) + ($b['jkk_company'] ?? 0) + ($b['jkm_company'] ?? 0) + ($b['jp_company'] ?? 0));
        if ($kesCo > 0) {
            $debit['bpjs_kes_company_expense'] = round($kesCo, 2);
        }
        if ($tkCo > 0) {
            $debit['bpjs_tk_company_expense'] = round($tkCo, 2);
        }

        $credit = array_filter([
            'net_pay_liability' => round((float) $run->total_net, 2),
            'pph21_liability' => round((float) $run->total_pph21, 2),
            'bpjs_kes_liability' => round((float) (($b['kes_employee'] ?? 0) + $kesCo), 2),
            'bpjs_tk_liability' => round((float) (($b['jht_employee'] ?? 0) + ($b['jp_employee'] ?? 0)) + $tkCo, 2),
            'employee_receivable' => round((float) ($t['loan'] ?? 0), 2),
            'other_deduction_payable' => round((float) ($t['other_deductions'] ?? 0), 2),
        ], fn ($v) => abs($v) > 0.005);

        return ['debit' => $debit, 'credit' => $credit, 'meta' => ['employee_count' => $run->employee_count, 'gross' => $run->total_gross, 'employer_cost' => $run->total_employer_cost]];
    }

    public function payload(FundingRequest $f): array
    {
        $f->loadMissing('run.batches', 'run.remittances');
        $run = $f->run;

        return [
            'number' => $f->number, 'run_number' => $run->run_number, 'run_type' => $run->type, 'title' => $run->title,
            'period_year' => (int) $run->period_year, 'period_month' => (int) $run->period_month, 'pay_date' => $run->pay_date->toDateString(),
            'status' => $f->status, 'employee_count' => $run->employee_count,
            'amount_net_total' => $f->amount_net_total, 'amount_disbursable' => $f->amount_disbursable, 'amount_approved' => $f->amount_approved,
            'breakdown' => $f->breakdown, 'snapshot_hash' => $run->snapshot_hash,
            'bank_batches' => $run->batches->map(fn ($x) => ['batch_number' => $x->batch_number, 'item_count' => $x->item_count, 'total_amount' => $x->total_amount, 'file_sha256' => $x->file_sha256])->values(),
            'remittances' => $run->remittances->map(fn ($r) => ['id' => $r->id, 'type' => $r->type, 'amount' => $r->amount, 'due_date' => $r->due_date->toDateString(), 'status' => $r->status])->values(),
            'decision' => ['by' => $f->decided_by_name, 'at' => $f->decided_at?->toIso8601String(), 'note' => $f->decision_note, 'finance_ref' => $f->finance_ref],
            'disbursement' => ['amount' => $f->disbursed_amount, 'at' => $f->disbursed_at?->toIso8601String(), 'by' => $f->disbursed_by_name, 'reference' => $f->disbursement_ref],
            'updated_at' => $f->updated_at?->toIso8601String(),
        ];
    }

    /** Keputusan Keuangan: approve | reject | revision. */
    public function decide(FundingRequest $f, string $decision, ?string $note, string $by, ?string $financeRef = null): FundingRequest
    {
        if (! in_array($f->status, ['submitted', 'revision'], true)) {
            throw new WorkflowException("Pengajuan {$f->number} berstatus {$f->status} dan tidak bisa diputuskan.");
        }
        if (in_array($decision, ['reject', 'revision'], true) && trim((string) $note) === '') {
            throw new WorkflowException('Catatan wajib diisi untuk penolakan/revisi.');
        }

        return DB::transaction(function () use ($f, $decision, $note, $by, $financeRef) {
            $map = ['approve' => 'approved', 'reject' => 'rejected', 'revision' => 'revision'];
            $f->update(['status' => $map[$decision], 'decided_by_name' => $by, 'decided_at' => now(), 'decision_note' => $note, 'finance_ref' => $financeRef,
                'amount_approved' => $decision === 'approve' ? $f->amount_net_total : null]);
            if ($decision === 'approve') {
                $f->run->update(['status' => 'funded']);
            }
            AuditLedger::record('funding.'.$decision, $f, ['by' => $by, 'note' => $note, 'finance_ref' => $financeRef], $f->number);

            return $f->fresh();
        });
    }

    /** Pencairan: wajib sudah ada file bank dan nominal HARUS sama dengan yang boleh ditransfer. */
    public function disbursed(FundingRequest $f, float $amount, ?string $reference, string $by): FundingRequest
    {
        if ($f->status !== 'approved') {
            throw new WorkflowException("Pengajuan {$f->number} belum disetujui Keuangan atau sudah dicairkan.");
        }
        $run = $f->run;
        if (! $run->batches()->exists()) {
            throw new WorkflowException('Payroll belum membuat file transfer bank untuk putaran ini. Minta Payroll membuat batch transfer dulu.');
        }
        $expect = (float) $run->fresh()->total_disbursable;
        if (abs($amount - $expect) > (float) config('pay.controls.reconcile_tolerance', 0) + 0.005) {
            throw new WorkflowException('Nominal pencairan Rp '.number_format($amount, 2, ',', '.').' tidak sama dengan total transfer gaji Rp '.number_format($expect, 2, ',', '.').'.');
        }

        DB::transaction(function () use ($f, $amount, $reference, $by, $run) {
            $f->update(['status' => 'disbursed', 'disbursed_amount' => $amount, 'disbursed_at' => now(), 'disbursed_by_name' => $by, 'disbursement_ref' => $reference]);
            $run->update(['status' => 'paid', 'paid_at' => now()]);
            AuditLedger::record('funding.disbursed', $f, ['amount' => $amount, 'by' => $by, 'reference' => $reference], $f->number);
        });

        $this->payslips->publish($run->fresh());
        $this->webhooks->dispatch('run.paid', ['run_number' => $run->run_number, 'period_year' => (int) $run->period_year, 'period_month' => (int) $run->period_month, 'employee_count' => $run->employee_count, 'paid_at' => now()->toIso8601String()]);

        return $f->fresh();
    }

    public function note(FundingRequest $f, string $subject, ?string $body, string $author, ?string $deliveryId = null): IntegrationMessage
    {
        return IntegrationMessage::create(['direction' => 'in', 'system' => 'keuangan', 'funding_request_id' => $f->id, 'subject' => $subject, 'body' => $body, 'author' => $author, 'delivery_id' => $deliveryId]);
    }

    /** Kirim ulang pengajuan yang ditolak/direvisi (setelah Payroll memperbaiki/menjelaskan). */
    public function resubmit(FundingRequest $f, User $by, ?string $note): FundingRequest
    {
        if (! in_array($f->status, ['rejected', 'revision'], true)) {
            throw ValidationException::withMessages(['funding' => 'Hanya pengajuan ditolak/direvisi yang bisa diajukan ulang.']);
        }
        $f->update(['status' => 'submitted', 'decision_note' => null, 'decided_at' => null]);
        IntegrationMessage::create(['direction' => 'out', 'system' => 'keuangan', 'funding_request_id' => $f->id, 'subject' => 'Pengajuan diajukan ulang', 'body' => $note, 'author' => $by->name]);
        AuditLedger::record('funding.resubmitted', $f, ['note' => $note], $f->number, $by->id);
        $this->webhooks->dispatch('funding.submitted', $this->payload($f->fresh()));

        return $f;
    }

    public function cancel(PayrollRun $run, string $reason): void
    {
        $f = $run->funding;
        if ($f && ! in_array($f->status, ['disbursed', 'confirmed', 'cancelled'], true)) {
            $f->update(['status' => 'cancelled']);
            $this->webhooks->dispatch('funding.cancelled', ['number' => $f->number, 'run_number' => $run->run_number, 'reason' => $reason]);
        }
    }
}
