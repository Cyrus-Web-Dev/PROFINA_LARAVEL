<?php

namespace App\Console\Commands;

use App\Models\Remittance;
use App\Models\User;
use App\Services\NotificationMailer;
use Illuminate\Console\Command;

class PayRemittanceReminders extends Command
{
    protected $signature = 'pay:remittance-reminders';

    protected $description = 'Ingatkan setoran PPh 21/BPJS yang jatuh tempo <= 3 hari atau terlambat';

    public function handle(NotificationMailer $mail): int
    {
        $due = Remittance::where('status', 'pending')->whereDate('due_date', '<=', now()->addDays(3))->with('run')->get();
        if ($due->isEmpty()) {
            return self::SUCCESS;
        }
        $lines = $due->map(fn ($r) => sprintf('- %s %02d/%d: Rp %s, jatuh tempo %s%s', Remittance::TYPE_LABELS[$r->type], $r->period_month, $r->period_year, number_format($r->amount, 0, ',', '.'), $r->due_date->format('d/m/Y'), $r->due_date->isPast() ? ' (TERLAMBAT)' : ''))->implode("\n");
        foreach (User::permission('remittances.manage')->where('status', 'active')->get() as $u) {
            $mail->custom($u->email, $u->name, 'Pengingat setoran pajak & BPJS', "Setoran berikut perlu segera dibayar Keuangan:\n\n{$lines}\n\nKeterlambatan setor PPh 21 dikenai sanksi bunga; BPJS dikenai denda.");
        }
        $this->info($due->count().' pengingat dikirim.');

        return self::SUCCESS;
    }
}
