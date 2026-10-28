<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Rantai persetujuan + pemisahan tugas (segregation of duties).
 * Aturan: pembuat/pengaju TIDAK boleh menyetujui, dan satu orang tidak boleh memegang dua langkah pada objek yang sama.
 */
class ApprovalService
{
    /** @param array<int,array{0:string,1:string}> $steps [[permission, label], ...] */
    public function start(Model $subject, array $steps): void
    {
        $subject->morphMany(Approval::class, 'approvable', 'approvable_type', 'approvable_id')->delete();
        foreach (array_values($steps) as $i => [$perm, $label]) {
            Approval::create([
                'approvable_type' => class_basename($subject), 'approvable_id' => $subject->getKey(),
                'step' => $i + 1, 'permission' => $perm, 'label' => $label, 'status' => 'pending',
            ]);
        }
    }

    public function pending(Model $subject): ?Approval
    {
        return Approval::where('approvable_type', class_basename($subject))->where('approvable_id', $subject->getKey())
            ->where('status', 'pending')->orderBy('step')->first();
    }

    /**
     * Putuskan langkah berikutnya. @return array{approval:Approval,complete:bool}
     *
     * @param  int|null  $creatorId  pembuat/pengaju objek (dilarang menyetujui)
     */
    public function act(Model $subject, User $user, string $decision, ?string $note, ?int $creatorId = null): array
    {
        $step = $this->pending($subject);
        if (! $step) {
            throw ValidationException::withMessages(['approval' => 'Tidak ada langkah persetujuan yang menunggu.']);
        }
        if (! $user->can($step->permission)) {
            throw ValidationException::withMessages(['approval' => "Kamu tidak punya izin untuk langkah \"{$step->label}\"."]);
        }
        if ($decision === 'rejected' && trim((string) $note) === '') {
            throw ValidationException::withMessages(['note' => 'Alasan penolakan wajib diisi.']);
        }

        if (config('pay.controls.sod_enforced')) {
            if ($creatorId && $creatorId === $user->id) {
                throw ValidationException::withMessages(['approval' => 'Pemisahan tugas: pembuat tidak boleh menyetujui buatannya sendiri.']);
            }
            $already = Approval::where('approvable_type', class_basename($subject))->where('approvable_id', $subject->getKey())
                ->where('acted_by', $user->id)->exists();
            if ($already) {
                throw ValidationException::withMessages(['approval' => 'Pemisahan tugas: satu orang tidak boleh memegang dua langkah persetujuan pada dokumen yang sama.']);
            }
        }

        $step->update([
            'status' => $decision, 'acted_by' => $user->id, 'acted_name' => $user->name, 'acted_at' => now(),
            'note' => $note, 'ip' => request()->ip(),
        ]);

        AuditLedger::record("approval.{$decision}", $subject, ['step' => $step->label, 'note' => $note], class_basename($subject).'#'.$subject->getKey(), $user->id);

        $complete = $decision === 'approved' && ! $this->pending($subject);

        return ['approval' => $step->fresh(), 'complete' => $complete];
    }
}
