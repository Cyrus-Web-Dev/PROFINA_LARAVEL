<?php

namespace App\Services;

use App\Exceptions\WorkflowException;
use App\Models\PayrollRun;
use App\Models\Remittance;
use Carbon\Carbon;

/** Kewajiban setoran PPh 21 & BPJS: dibuat saat putaran dikunci, dibayar oleh Keuangan, dipantau jatuh temponya di sini. */
class RemittanceService
{
    public function __construct(private Regulations $regs, private WebhookService $webhooks)
    {
    }

    public function createFor(PayrollRun $run): void
    {
        $t = $run->totals['bpjs'] ?? [];
        $reg = $this->regs->snapshot($run->period_end->toDateString());
        $due = $reg->get('remit.due_days');
        $next = Carbon::create($run->period_year, $run->period_month, 1)->addMonth();

        $amounts = [
            'pph21' => [(float) $run->total_pph21, $due['pph21_pay'], $due['pph21_report']],
            'bpjs_kes' => [(float) (($t['kes_employee'] ?? 0) + ($t['kes_company'] ?? 0)), $due['bpjs_kes'], null],
            'bpjs_tk' => [(float) (($t['jht_employee'] ?? 0) + ($t['jht_company'] ?? 0) + ($t['jkk_company'] ?? 0) + ($t['jkm_company'] ?? 0) + ($t['jp_employee'] ?? 0) + ($t['jp_company'] ?? 0)), $due['bpjs_tk'], null],
        ];
        foreach ($amounts as $type => [$amt, $payDay, $reportDay]) {
            if ($amt <= 0.005) {
                continue;
            }
            $r = Remittance::updateOrCreate(['payroll_run_id' => $run->id, 'type' => $type], [
                'period_year' => $run->period_year, 'period_month' => $run->period_month, 'amount' => $amt,
                'due_date' => $next->copy()->day(min($payDay, $next->daysInMonth)), 'report_due_date' => $reportDay ? $next->copy()->day(min($reportDay, $next->daysInMonth)) : null,
                'status' => 'pending',
            ]);
            $this->webhooks->dispatch('remittance.created', $this->payload($r));
        }
    }

    public function payload(Remittance $r): array
    {
        $r->loadMissing('run');

        return ['id' => $r->id, 'type' => $r->type, 'label' => Remittance::TYPE_LABELS[$r->type], 'run_number' => $r->run->run_number, 'period_year' => (int) $r->period_year, 'period_month' => (int) $r->period_month,
            'amount' => $r->amount, 'due_date' => $r->due_date->toDateString(), 'report_due_date' => $r->report_due_date?->toDateString(), 'status' => $r->status, 'payment_ref' => $r->payment_ref];
    }

    public function markPaid(Remittance $r, string $by, ?string $ref, ?string $financeRef, ?string $journalRef): Remittance
    {
        if ($r->status === 'paid') {
            throw new WorkflowException('Setoran ini sudah dicatat terbayar.');
        }
        $r->update(['status' => 'paid', 'paid_at' => now(), 'paid_by_name' => $by, 'payment_ref' => $ref, 'finance_ref' => $financeRef, 'journal_ref' => $journalRef]);
        AuditLedger::record('remittance.paid', $r, ['type' => $r->type, 'amount' => $r->amount, 'ref' => $ref, 'by' => $by], Remittance::TYPE_LABELS[$r->type].' '.$r->period_month.'/'.$r->period_year);

        return $r->fresh();
    }
}
