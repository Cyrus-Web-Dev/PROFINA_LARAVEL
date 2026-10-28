<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Profina\Sso\Token;
use Throwable;

/** Memantau semua modul lewat endpoint /profina/health (bertanda tangan). Hasil di-cache singkat. */
class ModuleHealth
{
    /** @return array<string,array<string,mixed>> */
    public function all(bool $fresh = false): array
    {
        $key = 'profina:health';
        if (! $fresh && ($cached = Cache::get($key))) {
            return $cached;
        }

        $modules = ModuleCatalog::all();
        $started = [];
        $responses = Http::pool(function (Pool $pool) use ($modules, &$started) {
            $calls = [];
            foreach ($modules as $code => $m) {
                $ts = (string) time();
                $started[$code] = microtime(true);
                $calls[] = $pool->as($code)->timeout(4)->connectTimeout(2)->acceptJson()->withHeaders([
                    'X-Profina-Timestamp' => $ts,
                    'X-Profina-Signature' => Token::requestSignature(ModuleCatalog::secretFor($code), $code, $ts),
                ])->get(($m['internal_url'] ?? $m['url']).'/profina/health');
            }

            return $calls;
        });

        $result = [];
        foreach ($modules as $code => $m) {
            $r = $responses[$code] ?? null;
            $row = ['code' => $code, 'name' => $m['name'], 'color' => $m['color'] ?? '#334155', 'url' => $m['url'], 'checked_at' => now()->format('H:i:s')];
            try {
                if ($r instanceof \Illuminate\Http\Client\Response && $r->successful()) {
                    $data = $r->json();
                    $row += [
                        'up' => (bool) ($data['db'] ?? false),
                        'db' => (bool) ($data['db'] ?? false),
                        'latency_ms' => (int) round((($r->transferStats?->getTransferTime()) ?? (microtime(true) - $started[$code])) * 1000),
                        'laravel' => $data['laravel'] ?? null,
                        'queue_pending' => $data['queue_pending'] ?? null,
                        'queue_failed' => $data['queue_failed'] ?? null,
                        'metrics' => $data['metrics'] ?? [],
                    ];
                    $row['error'] = $row['up'] ? null : 'Database modul tidak terjangkau.';
                } else {
                    $row['up'] = false;
                    $row['error'] = $r instanceof \Illuminate\Http\Client\Response
                        ? 'HTTP '.$r->status().($r->status() === 401 ? ' — kunci SSO modul tidak cocok (jalankan profina:provision)' : '')
                        : 'Tidak terjangkau (modul belum dijalankan?)';
                }
            } catch (Throwable $e) {
                $row['up'] = false;
                $row['error'] = $e->getMessage();
            }
            $result[$code] = $row;
        }

        Cache::put($key, $result, 15);

        return $result;
    }
}
