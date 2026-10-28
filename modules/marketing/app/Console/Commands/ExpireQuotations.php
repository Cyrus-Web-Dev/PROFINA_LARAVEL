<?php

namespace App\Console\Commands;

use App\Models\Quotation;
use Illuminate\Console\Command;

class ExpireQuotations extends Command
{
    protected $signature = 'marketing:expire-quotations';

    protected $description = 'Tandai penawaran draft/terkirim yang melewati masa berlaku sebagai expired';

    public function handle(): int
    {
        $n = Quotation::whereIn('status', ['draft', 'sent'])->whereDate('valid_until', '<', now()->toDateString())->update(['status' => 'expired']);
        $this->info("{$n} penawaran kedaluwarsa.");

        return self::SUCCESS;
    }
}
