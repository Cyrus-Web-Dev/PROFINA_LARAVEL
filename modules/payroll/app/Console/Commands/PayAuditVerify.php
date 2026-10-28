<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditLedger;
use App\Services\NotificationMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PayAuditVerify extends Command
{
    protected $signature = 'pay:audit-verify';

    protected $description = 'Verifikasi seluruh rantai hash jejak audit; peringatkan Auditor bila rusak';

    public function handle(NotificationMailer $mail): int
    {
        $r = AuditLedger::verify();
        $r['at'] = now()->format('d/m/Y H:i');
        Cache::forever('pay.audit.last', $r);

        if ($r['ok']) {
            $this->info("Rantai audit utuh ({$r['checked']} entri).");

            return self::SUCCESS;
        }
        $this->error("RANTAI AUDIT RUSAK pada entri #{$r['broken_at']}: {$r['reason']}");
        foreach (User::permission('audit.view')->where('status', 'active')->get() as $u) {
            $mail->custom($u->email, $u->name, 'PERINGATAN: rantai audit PAYBRIDGE rusak', "Verifikasi otomatis menemukan rantai audit rusak pada entri #{$r['broken_at']}.\n{$r['reason']}\n\nIni indikasi data diubah di luar aplikasi. Segera selidiki dan amankan backup.");
        }

        return self::FAILURE;
    }
}
