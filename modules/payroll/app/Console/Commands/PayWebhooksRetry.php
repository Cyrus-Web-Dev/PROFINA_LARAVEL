<?php

namespace App\Console\Commands;

use App\Services\WebhookService;
use Illuminate\Console\Command;

class PayWebhooksRetry extends Command
{
    protected $signature = 'pay:webhooks-retry';

    protected $description = 'Kirim ulang webhook yang tertunda';

    public function handle(WebhookService $svc): int
    {
        $this->info('Dicoba ulang: '.$svc->retryDue());

        return self::SUCCESS;
    }
}
