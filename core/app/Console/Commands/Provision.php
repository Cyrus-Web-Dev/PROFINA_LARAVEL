<?php

namespace App\Console\Commands;

use App\Services\ModuleCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class Provision extends Command
{
    protected $signature = 'profina:provision {--show : tampilkan kunci tiap modul}';

    protected $description = 'Buat kunci induk Portal & isi PROFINA_* di .env tiap modul (modules/<kode>/.env)';

    public function handle(): int
    {
        $coreEnv = base_path('.env');
        if (! is_file($coreEnv)) {
            $this->error('Salin .env.example menjadi .env di core dulu.');

            return self::FAILURE;
        }

        $master = (string) config('portal.master_key');
        if ($master === '') {
            $master = bin2hex(random_bytes(32));
            $this->setEnv($coreEnv, 'PROFINA_MASTER_KEY', $master);
            config(['portal.master_key' => $master]);
            $this->info('PROFINA_MASTER_KEY dibuat di core/.env (simpan, jangan dibagikan ke modul).');
        }

        $coreUrl = rtrim((string) config('app.url'), '/');
        $cookieHost = parse_url($coreUrl, PHP_URL_HOST) ?: 'localhost';

        foreach (ModuleCatalog::all() as $code => $m) {
            $dir = base_path("../modules/{$code}");
            if (! is_dir($dir)) {
                $this->warn("modules/{$code} tidak ditemukan - dilewati.");

                continue;
            }
            $env = $dir.'/.env';
            if (! is_file($env)) {
                if (! is_file($dir.'/.env.example')) {
                    $this->warn("modules/{$code}/.env.example tidak ada - dilewati.");

                    continue;
                }
                copy($dir.'/.env.example', $env);
            }

            $secret = ModuleCatalog::secretFor($code);
            $this->setEnv($env, 'PROFINA_MODULE', $code);
            $this->setEnv($env, 'PROFINA_CORE_URL', $coreUrl);
            $this->setEnv($env, 'PROFINA_CORE_INTERNAL_URL', (string) env('PROFINA_CORE_INTERNAL_URL', 'http://127.0.0.1:8000'));
            $this->setEnv($env, 'PROFINA_SSO_SECRET', $secret);
            $this->setEnv($env, 'APP_URL', $m['url']);
            $this->setEnv($env, 'SESSION_COOKIE', "profina_{$code}_session");
            $this->line("✔ modules/{$code}/.env diisi".($this->option('show') ? "  secret={$secret}" : ''));
        }

        $this->newLine();
        $this->info('Selesai. Jalankan "php artisan config:clear" di tiap aplikasi bila sebelumnya sudah di-cache.');

        return self::SUCCESS;
    }

    private function setEnv(string $file, string $key, string $value): void
    {
        $content = file_get_contents($file);
        $line = $key.'='.(preg_match('/\s|#|"/', $value) ? '"'.addslashes($value).'"' : $value);

        if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $content)) {
            $content = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', str_replace('\\', '\\\\', $line), $content);
        } else {
            $content = rtrim($content)."\n".$line."\n";
        }
        file_put_contents($file, $content);
    }
}
