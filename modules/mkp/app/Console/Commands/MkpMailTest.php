<?php

namespace App\Console\Commands;

use App\Exceptions\GmailSendException;
use App\Services\GmailApiService;
use Illuminate\Console\Command;

class MkpMailTest extends Command
{
    protected $signature = 'mkp:mail-test {to : Alamat email tujuan}';

    protected $description = 'Cek konfigurasi Gmail API dan kirim email percobaan (menampilkan penyebab error yang sebenarnya)';

    public function handle(GmailApiService $gmail): int
    {
        $diag = $gmail->diagnose();

        $this->line('credentials.json : '.($diag['files']['credentials'] ? 'ada' : 'TIDAK ADA'));
        $this->line('token.json       : '.($diag['files']['token'] ? 'ada' : 'TIDAK ADA'));
        $this->line('Sumber dipakai   : '.($diag['source'] ?? '(tidak ada)'));
        foreach ($diag['notes'] as $note) {
            $this->warn('Catatan: '.$note);
        }

        if (! $diag['ready']) {
            $this->error('Kredensial Gmail belum lengkap. Lihat README, bagian "Menyiapkan Gmail API".');

            return self::FAILURE;
        }

        $to = (string) $this->argument('to');
        $this->line("Mengirim email percobaan ke {$to} ...");

        try {
            $gmail->send(
                $to,
                null,
                'Email percobaan - MKP',
                '<p>Jika kamu menerima email ini, pengiriman lewat <strong>Gmail API</strong> sudah berfungsi.</p>',
                'Jika kamu menerima email ini, pengiriman lewat Gmail API sudah berfungsi.'
            );
        } catch (GmailSendException $e) {
            $this->error('GAGAL: '.$e->userMessage());

            return self::FAILURE;
        }

        $this->info('Berhasil terkirim. Cek inbox / folder spam.');

        return self::SUCCESS;
    }
}
