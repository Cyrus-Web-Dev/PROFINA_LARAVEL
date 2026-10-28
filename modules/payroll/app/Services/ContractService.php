<?php

namespace App\Services;

use App\Models\CompensationContract;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Siklus hidup kontrak kompensasi. Aturan emas: gaji yang dibayar = kontrak AKTIF yang sudah disetujui, tidak ada jalan lain.
 * HR (SDM) mengusulkan -> Payroll memverifikasi -> Keuangan/Direksi menyetujui. Versi baru tidak menimpa versi lama.
 */
class ContractService
{
    public function __construct(private ApprovalService $approvals, private WebhookService $webhooks)
    {
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  array<int,array{pay_component_id:int,amount:float|int|string}>  $components
     */
    public function propose(array $data, array $components, ?User $by = null, string $source = 'payroll'): CompensationContract
    {
        $employee = Employee::findOrFail($data['employee_id']);
        if ((float) $data['base_salary'] <= 0) {
            throw ValidationException::withMessages(['base_salary' => 'Gaji pokok harus lebih dari 0.']);
        }

        $data = array_merge([
            'contract_type' => 'pkwt', 'jkk_class' => 1, 'bpjs_kes_enrolled' => true, 'bpjs_tk_enrolled' => true,
            'tax_scheme' => 'ter', 'thr_eligible' => true, 'overtime_eligible' => false, 'end_date' => null, 'grade_id' => null,
        ], $data);

        $data['effective_date'] = \Carbon\Carbon::parse($data['effective_date'])->toDateString();
        $data['end_date'] = $data['end_date'] ? \Carbon\Carbon::parse($data['end_date'])->toDateString() : null;
        $data['jkk_class'] = (int) $data['jkk_class'];

        $grade = $data['grade_id'] ? Grade::find($data['grade_id']) : null;
        $outOfBand = $grade && ! $grade->inBand((float) $data['base_salary']);
        $dual = $outOfBand || ($grade && $grade->requires_dual_approval);

        return DB::transaction(function () use ($employee, $data, $components, $by, $source, $outOfBand, $dual) {
            $version = (int) CompensationContract::where('employee_id', $employee->id)->max('version') + 1;

            $contract = CompensationContract::create([
                ...collect($data)->only(['employee_id', 'grade_id', 'contract_type', 'base_salary', 'effective_date', 'end_date', 'jkk_class',
                    'bpjs_kes_enrolled', 'bpjs_tk_enrolled', 'tax_scheme', 'thr_eligible', 'overtime_eligible', 'document_ref', 'document_sha256', 'notes', 'reason', 'sdm_ref'])->all(),
                'contract_number' => 'KTR-'.now()->format('Y').'-'.str_pad((string) (CompensationContract::whereYear('created_at', now()->year)->count() + 1), 5, '0', STR_PAD_LEFT),
                'version' => $version, 'status' => 'proposed', 'source' => $source,
                'proposed_by' => $by?->id, 'proposed_by_name' => $by?->name ?? ($data['proposed_by_name'] ?? 'SDM'),
                'terms_hash' => CompensationContract::hashTerms($data, $components),
            ]);

            foreach ($components as $c) {
                $contract->components()->create(['pay_component_id' => $c['pay_component_id'], 'amount' => $c['amount'], 'note' => $c['note'] ?? null]);
            }

            $steps = [['contracts.verify', 'Verifikasi Payroll'], ['contracts.approve', 'Persetujuan Keuangan']];
            if ($dual) {
                $steps[] = ['contracts.approve-executive', $outOfBand ? 'Persetujuan Direksi (di luar rentang grade)' : 'Persetujuan Direksi (golongan eksekutif)'];
            }
            $this->approvals->start($contract, $steps);

            AuditLedger::record('contract.proposed', $contract, [
                'employee' => $employee->nip, 'version' => $version, 'base_salary' => $contract->base_salary,
                'effective_date' => (string) $data['effective_date'], 'source' => $source, 'out_of_band' => $outOfBand, 'terms_hash' => $contract->terms_hash,
            ], $contract->contract_number, $by?->id);

            return $contract;
        });
    }

    public function decide(CompensationContract $contract, User $user, string $decision, ?string $note): CompensationContract
    {
        if (! in_array($contract->status, ['proposed', 'verified'], true)) {
            throw ValidationException::withMessages(['status' => 'Kontrak ini tidak sedang menunggu keputusan.']);
        }
        if (! $contract->intact()) {
            throw ValidationException::withMessages(['status' => 'Isi kontrak tidak cocok dengan hash-nya (ada perubahan di luar alur resmi). Tolak dan laporkan ke Auditor.']);
        }

        return DB::transaction(function () use ($contract, $user, $decision, $note) {
            $res = $this->approvals->act($contract, $user, $decision, $note, $contract->proposed_by);

            if ($decision === 'rejected') {
                $contract->update(['status' => 'rejected', 'rejected_by' => $user->id, 'rejected_at' => now(), 'rejection_note' => $note]);
                $this->approvals->pending($contract)?->delete();
                $this->webhooks->dispatch('contract.rejected', $this->payload($contract->fresh(), ['note' => $note]));

                return $contract->fresh();
            }

            $step = $res['approval'];
            if ($step->step === 1) {
                $contract->update(['status' => 'verified', 'verified_by' => $user->id, 'verified_at' => now(), 'verification_note' => $note]);
            } elseif ($step->step === 2) {
                $contract->update(['approved_by' => $user->id, 'approved_at' => now()]);
            } else {
                $contract->update(['second_approved_by' => $user->id, 'second_approved_at' => now()]);
            }

            if ($res['complete']) {
                $contract->update(['status' => 'approved']);
                if ($contract->effective_date->lte(today())) {
                    $this->activate($contract->fresh());
                }
            }

            return $contract->fresh();
        });
    }

    /** Aktifkan kontrak yang sudah disetujui (dipanggil saat disetujui atau oleh pay:activate-contracts). */
    public function activate(CompensationContract $contract): void
    {
        if ($contract->status !== 'approved' || ! $contract->intact()) {
            throw ValidationException::withMessages(['status' => 'Kontrak tidak bisa diaktifkan (belum disetujui atau hash tidak cocok).']);
        }

        DB::transaction(function () use ($contract) {
            $prev = CompensationContract::where('employee_id', $contract->employee_id)->where('status', 'active')->where('id', '!=', $contract->id)->first();
            if ($prev) {
                $prev->update(['status' => 'superseded', 'superseded_on' => $contract->effective_date]);
            }
            $contract->update(['status' => 'active', 'activated_at' => now()]);
            AuditLedger::record('contract.activated', $contract, ['employee_id' => $contract->employee_id, 'base_salary' => $contract->base_salary, 'supersedes' => $prev?->contract_number], $contract->contract_number);
        });

        $this->webhooks->dispatch('contract.activated', $this->payload($contract->fresh()));
    }

    /** Harian: aktifkan kontrak terjadwal yang sudah jatuh tempo & tandai kontrak berakhir sebagai expired. */
    public function sweep(): array
    {
        $activated = 0;
        foreach (CompensationContract::where('status', 'approved')->whereDate('effective_date', '<=', today())->get() as $c) {
            try {
                $this->activate($c);
                $activated++;
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $expired = CompensationContract::where('status', 'active')->whereNotNull('end_date')->whereDate('end_date', '<', today())->update(['status' => 'expired']);

        return ['activated' => $activated, 'expired' => $expired];
    }

    private function payload(CompensationContract $c, array $extra = []): array
    {
        $c->loadMissing('employee');

        return array_merge([
            'contract_number' => $c->contract_number, 'nip' => $c->employee?->nip, 'version' => $c->version, 'status' => $c->status,
            'base_salary' => $c->base_salary, 'effective_date' => $c->effective_date->toDateString(), 'end_date' => $c->end_date?->toDateString(),
        ], $extra);
    }
}
