<?php

namespace App\Services;

use App\Models\User;
use App\Support\RegulationSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Akses parameter regulasi dari database (insert-only, berlaku per tanggal). */
class Regulations
{
    public function snapshot(?string $asOf = null): RegulationSnapshot
    {
        $asOf ??= now()->toDateString();

        $rows = Cache::remember('pay.reg.rows', 600, fn () => DB::table('regulation_parameters')
            ->get(['code', 'value', 'effective_from'])
            ->map(fn ($r) => [$r->code, json_decode($r->value, true), substr((string) $r->effective_from, 0, 10)])->all());

        $terRows = Cache::remember('pay.reg.ter', 600, fn () => DB::table('tax_ter_rates')
            ->orderBy('category')->orderBy('lower_bound')->get()->map(fn ($r) => (array) $r)->all());

        // pilih himpunan TER dengan effective_from terbaru yang <= asOf
        $latest = collect($terRows)->filter(fn ($r) => substr($r['effective_from'], 0, 10) <= $asOf)->max('effective_from');
        $ter = [];
        foreach ($terRows as $r) {
            if ($r['effective_from'] !== $latest) {
                continue;
            }
            $ter[$r['category']][] = [$r['upper_bound'] === null ? null : (float) $r['upper_bound'], (float) $r['rate']];
        }

        return RegulationSnapshot::build($rows, $ter, $asOf, (bool) config('pay.payroll.round_to_rupiah', true));
    }

    /** Tambah versi parameter baru (tidak pernah menimpa). */
    public function publish(string $code, mixed $value, string $effectiveFrom, ?string $source, ?User $by): void
    {
        DB::table('regulation_parameters')->updateOrInsert(
            ['code' => $code, 'effective_from' => $effectiveFrom],
            ['value' => json_encode($value), 'source' => $source, 'created_by' => $by?->id, 'created_at' => now(), 'updated_at' => now()]
        );
        $this->flush();
        AuditLedger::record('regulation.published', null, ['code' => $code, 'value' => $value, 'effective_from' => $effectiveFrom, 'source' => $source], $code);
    }

    public function flush(): void
    {
        Cache::forget('pay.reg.rows');
        Cache::forget('pay.reg.ter');
    }
}
