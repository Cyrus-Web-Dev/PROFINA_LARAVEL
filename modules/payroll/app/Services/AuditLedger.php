<?php

namespace App\Services;

use App\Models\AuditEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * Jejak audit rantai-hash (tamper-evident). Tiap baris menyimpan hash baris sebelumnya, jadi mengubah/menghapus satu
 * baris di tengah akan memutus rantai. Tabelnya juga append-only di level database (trigger), dan hash terbaru
 * dikirim berkala ke luar sistem (pay:audit-anchor) supaya penulisan ulang seluruh rantai tetap ketahuan.
 */
class AuditLedger
{
    private const LOCK_KEY = 7410001;

    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    public static function record(string $event, ?Model $subject = null, array $payload = [], ?string $label = null, ?int $actorId = null): AuditEntry
    {
        return DB::transaction(function () use ($event, $subject, $payload, $label, $actorId) {
            if (DB::getDriverName() === 'pgsql') {
                DB::select('select pg_advisory_xact_lock(?)', [self::LOCK_KEY]);
            }

            $last = AuditEntry::query()->orderByDesc('seq')->first();
            $seq = ($last->seq ?? 0) + 1;
            $prev = $last->hash ?? self::GENESIS;
            $user = $actorId ? \App\Models\User::find($actorId) : Auth::user();
            $occurred = now('UTC')->format('Y-m-d\TH:i:s.u\Z');
            $json = json_encode(self::canon($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $subjectType = $subject ? class_basename($subject) : null;
            $subjectId = $subject?->getKey();

            $hash = self::hashOf($prev, $seq, $event, $user?->id, $subjectType, $subjectId, $json, $occurred);

            return AuditEntry::create([
                'seq' => $seq, 'event' => $event, 'actor_id' => $user?->id, 'actor_name' => $user?->name,
                'ip' => Request::ip(), 'subject_type' => $subjectType, 'subject_id' => $subjectId,
                'subject_label' => $label, 'payload' => $json, 'prev_hash' => $prev, 'hash' => $hash, 'occurred_at' => $occurred,
            ]);
        });
    }

    public static function hashOf(string $prev, int $seq, string $event, ?int $actorId, ?string $subjectType, ?int $subjectId, string $payloadJson, string $occurredAt): string
    {
        return hash('sha256', implode('|', [$prev, $seq, $event, $actorId ?? '', $subjectType ?? '', $subjectId ?? '', $payloadJson, $occurredAt]));
    }

    /** Periksa seluruh rantai. @return array{ok:bool,checked:int,broken_at:?int,reason:?string,head:?string} */
    public static function verify(): array
    {
        $prev = self::GENESIS;
        $expectSeq = 1;
        $checked = 0;
        $head = null;

        foreach (AuditEntry::query()->orderBy('seq')->cursor() as $e) {
            if ((int) $e->seq !== $expectSeq) {
                return ['ok' => false, 'checked' => $checked, 'broken_at' => $expectSeq, 'reason' => "Nomor urut hilang/loncat (diharapkan {$expectSeq}, ditemukan {$e->seq}).", 'head' => $head];
            }
            if ($e->prev_hash !== $prev) {
                return ['ok' => false, 'checked' => $checked, 'broken_at' => (int) $e->seq, 'reason' => 'Hash sebelumnya tidak cocok dengan baris sebelumnya.', 'head' => $head];
            }
            $calc = self::hashOf($prev, (int) $e->seq, $e->event, $e->actor_id ? (int) $e->actor_id : null, $e->subject_type, $e->subject_id ? (int) $e->subject_id : null, $e->payload, $e->occurred_at);
            if (! hash_equals($calc, $e->hash)) {
                return ['ok' => false, 'checked' => $checked, 'broken_at' => (int) $e->seq, 'reason' => 'Isi baris tidak cocok dengan hash-nya (data diubah).', 'head' => $head];
            }
            $prev = $e->hash;
            $head = $e->hash;
            $expectSeq++;
            $checked++;
        }

        return ['ok' => true, 'checked' => $checked, 'broken_at' => null, 'reason' => null, 'head' => $head];
    }

    /** Urutkan kunci array asosiatif secara rekursif agar JSON kanonik. */
    private static function canon(mixed $v): mixed
    {
        if (! is_array($v)) {
            return $v;
        }
        $isList = array_is_list($v);
        $v = array_map([self::class, 'canon'], $v);
        if (! $isList) {
            ksort($v);
        }

        return $v;
    }
}
