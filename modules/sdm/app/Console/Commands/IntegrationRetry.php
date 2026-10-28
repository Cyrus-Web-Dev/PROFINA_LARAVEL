<?php

namespace App\Console\Commands;

use App\Services\PayrollBridge;
use Illuminate\Console\Command;

class IntegrationRetry extends Command
{
    protected $signature = 'sdm:integration-retry';

    protected $description = 'Kirim ulang webhook SDM -> PAYBRIDGE yang tertunda (backoff eksponensial)';

    public function handle(PayrollBridge $bridge): int
    {
        $this->info('Dicoba ulang: '.$bridge->retryDue());

        return self::SUCCESS;
    }
}
