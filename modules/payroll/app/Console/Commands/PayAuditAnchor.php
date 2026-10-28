<?php

namespace App\Console\Commands;

use App\Models\AuditEntry;
use App\Services\NotificationMailer;
use Illuminate\Console\Command;

class PayAuditAnchor extends Command
{
    protected $signature = 'pay:audit-anchor';

    protected $description = 'Kirim hash terbaru rantai audit ke email auditor (jangkar di luar sistem)';

    public function handle(NotificationMailer $mail): int
    {
        $emails = config('pay.audit.anchor_emails', []);
        $head = AuditEntry::orderByDesc('seq')->first();
        if (! $emails || ! $head) {
            $this->warn('Tidak ada penerima (PAY_AUDIT_ANCHOR_EMAILS) atau belum ada entri.');

            return self::SUCCESS;
        }
        foreach ($emails as $to) {
            $mail->custom($to, '', 'Jangkar audit PAYBRIDGE '.now()->format('d/m/Y'), "Entri terakhir: #{$head->seq}\nHash: {$head->hash}\nWaktu (UTC): {$head->occurred_at}\n\nSimpan email ini. Jika suatu hari rantai di database ditulis ulang, hash di sini tidak akan cocok.");
        }
        $this->info('Jangkar terkirim ke '.count($emails).' alamat.');

        return self::SUCCESS;
    }
}
