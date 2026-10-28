<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Backup database ke storage/app/private/backups/.
 *
 *  1) Mode "full"  : memakai mysqldump / pg_dump bila ditemukan (struktur + data, paling lengkap).
 *  2) Mode "data"  : cadangan DATA saja lewat PHP (tanpa program luar) untuk MySQL maupun PostgreSQL.
 *                    Pulihkan ke database kosong yang sudah dijalankan `php artisan migrate`.
 */
class DatabaseBackupService
{
    public const DIR = 'backups';

    /** Tabel sementara / dikelola framework: tidak perlu di-backup. */
    private const SKIP = ['migrations', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    public const FILENAME_PATTERN = '/^sdm_backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}_(full|data)\.sql$/';

    /** @return array{file: string, mode: string, note: ?string} */
    public function create(): array
    {
        Storage::disk('local')->makeDirectory(self::DIR);
        $dir = Storage::disk('local')->path(self::DIR);
        $stamp = now()->format('Y-m-d_H-i-s');
        $driver = DB::connection()->getDriverName();

        $note = null;
        $full = $dir.DIRECTORY_SEPARATOR."sdm_backup_{$stamp}_full.sql";

        try {
            if ($this->nativeDump($driver, $full)) {
                $this->prune();

                return ['file' => basename($full), 'mode' => 'full', 'note' => null];
            }
            $note = 'Program mysqldump/pg_dump tidak ditemukan, jadi yang dibuat adalah cadangan DATA saja (mode PHP).';
        } catch (Throwable $e) {
            Log::warning('[Backup] Dump bawaan gagal: '.$e->getMessage());
            @unlink($full);
            $note = 'Dump bawaan gagal ('.$e->getMessage().'), jadi yang dibuat adalah cadangan DATA saja (mode PHP).';
        }

        $data = $dir.DIRECTORY_SEPARATOR."sdm_backup_{$stamp}_data.sql";
        try {
            $this->phpDump($data);
        } catch (Throwable $e) {
            @unlink($data);
            throw $e;
        }
        $this->prune();

        return ['file' => basename($data), 'mode' => 'data', 'note' => $note];
    }

    /** @return array<int, array{name: string, mode: string, size: int, modified: string}> */
    public function list(): array
    {
        $disk = Storage::disk('local');

        return collect($disk->files(self::DIR))
            ->filter(fn ($f) => preg_match(self::FILENAME_PATTERN, basename($f)))
            ->map(fn ($f) => [
                'name' => basename($f),
                'mode' => str_ends_with($f, '_full.sql') ? 'full' : 'data',
                'size' => $disk->size($f),
                'modified' => date('d/m/Y H:i', $disk->lastModified($f)),
                'ts' => $disk->lastModified($f),
            ])
            ->sortByDesc('ts')
            ->map(fn ($f) => collect($f)->except('ts')->all())
            ->values()
            ->all();
    }

    /** Lokasi mysqldump/pg_dump yang akan dipakai (null bila tidak ada -> mode PHP). */
    public function nativeToolPath(): ?string
    {
        return match (DB::connection()->getDriverName()) {
            'mysql' => $this->findBinary('mysqldump', config('hr.backup.mysqldump_path'), $this->extraDirs('mysql')),
            'pgsql' => $this->findBinary('pg_dump', config('hr.backup.pg_dump_path'), $this->extraDirs('pgsql')),
            default => null,
        };
    }

    public function prune(): void
    {
        $keep = max(1, (int) config('hr.backup.keep', 10));
        foreach (array_slice($this->list(), $keep) as $old) {
            Storage::disk('local')->delete(self::DIR.'/'.$old['name']);
        }
    }

    // ------------------------------------------------------------------ mode full

    private function nativeDump(string $driver, string $target): bool
    {
        $c = config('database.connections.'.config('database.default'));
        $env = [];

        if ($driver === 'mysql') {
            $bin = $this->nativeToolPath();
            if (! $bin) {
                return false;
            }
            $cmd = [$bin, '--host='.$c['host'], '--port='.$c['port'], '--user='.$c['username'],
                '--single-transaction', '--default-character-set=utf8mb4', '--result-file='.$target, $c['database']];
            if (($c['password'] ?? '') !== '') {
                $env['MYSQL_PWD'] = (string) $c['password']; // lewat environment, bukan argumen (tidak terlihat di daftar proses)
            }
        } elseif ($driver === 'pgsql') {
            $bin = $this->nativeToolPath();
            if (! $bin) {
                return false;
            }
            $cmd = [$bin, '--host='.$c['host'], '--port='.$c['port'], '--username='.$c['username'],
                '--no-owner', '--no-privileges', '--file='.$target, $c['database']];
            if (($c['password'] ?? '') !== '') {
                $env['PGPASSWORD'] = (string) $c['password'];
            }
        } else {
            return false;
        }

        $process = new Process($cmd, null, $env ?: null, null, 300);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($target) || filesize($target) < 100) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'kode keluar '.$process->getExitCode());
        }

        return true;
    }

    private function findBinary(string $name, ?string $configured, array $extraDirs): ?string
    {
        if ($configured && is_file($configured)) {
            return $configured;
        }

        return (new ExecutableFinder())->find($name, null, $extraDirs);
    }

    private function extraDirs(string $driver): array
    {
        $dirs = $driver === 'mysql'
            ? ['C:\\xampp\\mysql\\bin', 'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin', '/opt/lampp/bin', '/usr/local/mysql/bin', '/usr/local/bin']
            : ['/usr/local/bin', '/opt/homebrew/bin'];

        $globs = $driver === 'mysql'
            ? ['C:\\laragon\\bin\\mysql\\*\\bin', 'C:\\wamp64\\bin\\mysql\\*\\bin', 'C:\\Program Files\\MariaDB *\\bin']
            : ['C:\\Program Files\\PostgreSQL\\*\\bin', '/usr/lib/postgresql/*/bin'];

        foreach ($globs as $g) {
            $dirs = array_merge($dirs, glob($g, GLOB_ONLYDIR) ?: []);
        }

        return $dirs;
    }

    // ------------------------------------------------------------------ mode data (PHP)

    private function phpDump(string $target): void
    {
        $conn = DB::connection();
        $driver = $conn->getDriverName();
        $pdo = $conn->getPdo();
        $grammar = $conn->getQueryGrammar();

        $h = fopen($target, 'wb');
        if (! $h) {
            throw new RuntimeException('Tidak bisa menulis file backup di '.dirname($target));
        }

        fwrite($h, "-- SDM HRIS backup DATA ({$driver}) - ".now()->toDateTimeString()."\n");
        fwrite($h, "-- Hanya berisi DATA. Pulihkan ke database KOSONG yang sudah dijalankan: php artisan migrate\n\n");
        if ($driver === 'mysql') {
            fwrite($h, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        }

        foreach ($this->orderedTables($driver) as $table) {
            $columns = Schema::getColumnListing($table);
            if (! $columns) {
                continue;
            }
            $prefix = 'INSERT INTO '.$grammar->wrapTable($table).' ('.implode(', ', array_map(fn ($c) => $grammar->wrap($c), $columns)).") VALUES\n";

            $batch = [];
            $total = 0;
            $flush = function () use (&$batch, $h, $prefix) {
                if ($batch) {
                    fwrite($h, $prefix.implode(",\n", $batch).";\n");
                    $batch = [];
                }
            };

            foreach (DB::table($table)->cursor() as $row) {
                $values = [];
                foreach ($columns as $column) {
                    $values[] = $this->quote($pdo, $row->{$column} ?? null, $driver);
                }
                $batch[] = '('.implode(', ', $values).')';
                $total++;
                if (count($batch) >= 200) {
                    $flush();
                }
            }
            $flush();

            // PostgreSQL: setelah memasukkan id manual, sequence harus disetel ulang agar insert berikutnya tidak bentrok.
            if ($driver === 'pgsql' && $total > 0 && in_array('id', $columns, true)) {
                $wrapped = $grammar->wrapTable($table);
                fwrite($h, "SELECT setval(pg_get_serial_sequence('".str_replace("'", "''", $wrapped)."', 'id'), (SELECT MAX(id) FROM {$wrapped}));\n");
            }
            fwrite($h, "\n");
        }

        if ($driver === 'mysql') {
            fwrite($h, "SET FOREIGN_KEY_CHECKS=1;\n");
        }
        fclose($h);
    }

    private function quote(\PDO $pdo, mixed $value, string $driver): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $driver === 'pgsql' ? ($value ? 'TRUE' : 'FALSE') : ($value ? '1' : '0');
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $pdo->quote((string) $value);
    }

    /** Urutkan tabel: tabel induk (yang dirujuk foreign key) lebih dulu. */
    private function orderedTables(string $driver): array
    {
        $tables = collect(Schema::getTables())
            ->when($driver === 'pgsql', fn ($c) => $c->where('schema', 'public'))
            ->pluck('name')
            ->reject(fn ($t) => in_array($t, self::SKIP, true))
            ->values()
            ->all();

        try {
            $deps = [];
            foreach ($tables as $t) {
                $deps[$t] = collect(Schema::getForeignKeys($t))->pluck('foreign_table')->unique()
                    ->filter(fn ($d) => $d !== $t && in_array($d, $tables, true))->values()->all();
            }

            $sorted = [];
            $visiting = [];
            $visit = function (string $t) use (&$visit, &$sorted, &$visiting, $deps) {
                if (isset($sorted[$t]) || isset($visiting[$t])) {
                    return;
                }
                $visiting[$t] = true;
                foreach ($deps[$t] as $d) {
                    $visit($d);
                }
                $sorted[$t] = true;
            };
            foreach ($tables as $t) {
                $visit($t);
            }

            return array_keys($sorted);
        } catch (Throwable) {
            return $tables; // MySQL tetap aman karena FOREIGN_KEY_CHECKS dimatikan
        }
    }
}
