<?php

namespace App\Services;

use App\Models\IntegrationClient;
use App\Models\IntegrationDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Webhook KELUAR SDM -> PAYBRIDGE. Ditandatangani HMAC-SHA256:
 *   X-Sdm-Signature: sha256=<hmac(body mentah, webhook_secret klien)>
 * Prinsip "event tipis": data sensitif (rekening, NPWP) TIDAK dibawa di webhook; PAYBRIDGE menariknya lewat REST API ber-token.
 * Kegagalan webhook tidak pernah menggagalkan penyimpanan data di SDM; dicatat & dicoba ulang (sdm:integration-retry).
 */
class PayrollBridge
{
    public const MAX_ATTEMPTS = 6;

    public function dispatch(string $event, array $data): void
    {
        try {
            IntegrationClient::where('is_active', true)->whereNotNull('webhook_url')->get()
                ->filter(fn (IntegrationClient $c) => in_array($event, $c->webhook_events ?? [], true))
                ->each(function (IntegrationClient $c) use ($event, $data) {
                    $d = IntegrationDelivery::create([
                        'integration_client_id' => $c->id, 'event' => $event,
                        'payload' => ['event' => $event, 'occurred_at' => now()->toIso8601String(), 'data' => $data],
                    ]);
                    $this->send($d);
                });
        } catch (Throwable $e) {
            Log::warning('[PayrollBridge] Gagal menjadwalkan webhook: '.$e->getMessage());
        }
    }

    public function send(IntegrationDelivery $d): void
    {
        $c = $d->client;
        $body = json_encode($d->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $attempts = $d->attempts + 1;
        try {
            $r = Http::timeout((int) config('hr.payroll.webhook_timeout', 8))->connectTimeout(5)->withHeaders([
                'X-Sdm-Event' => $d->event, 'X-Sdm-Delivery' => (string) $d->id,
                'X-Sdm-Signature' => 'sha256='.hash_hmac('sha256', $body, (string) $c->webhook_secret),
            ])->withBody($body, 'application/json')->post($c->webhook_url);
            $ok = $r->successful();
            $d->update([
                'attempts' => $attempts, 'response_code' => $r->status(), 'status' => $ok ? 'delivered' : ($attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending'),
                'error' => $ok ? null : 'HTTP '.$r->status(), 'delivered_at' => $ok ? now() : null,
                'next_retry_at' => $ok || $attempts >= self::MAX_ATTEMPTS ? null : now()->addMinutes(2 ** $attempts),
            ]);
        } catch (Throwable $e) {
            $d->update([
                'attempts' => $attempts, 'status' => $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending', 'error' => mb_substr($e->getMessage(), 0, 300),
                'next_retry_at' => $attempts >= self::MAX_ATTEMPTS ? null : now()->addMinutes(2 ** $attempts),
            ]);
        }
    }

    public function retryDue(): int
    {
        $n = 0;
        IntegrationDelivery::where('status', 'pending')->where('next_retry_at', '<=', now())->limit(100)->get()->each(function ($d) use (&$n) {
            $this->send($d);
            $n++;
        });

        return $n;
    }

    public function ping(IntegrationClient $c): array
    {
        if (! $c->webhook_url) {
            return ['ok' => false, 'message' => 'Alamat webhook belum diisi.'];
        }
        $body = json_encode(['event' => 'ping', 'occurred_at' => now()->toIso8601String(), 'data' => []]);
        try {
            $r = Http::timeout(8)->connectTimeout(5)->withHeaders([
                'X-Sdm-Event' => 'ping', 'X-Sdm-Delivery' => 'ping-'.uniqid(), 'X-Sdm-Signature' => 'sha256='.hash_hmac('sha256', $body, (string) $c->webhook_secret),
            ])->withBody($body, 'application/json')->post($c->webhook_url);

            return ['ok' => $r->successful(), 'message' => $r->successful() ? 'Ping diterima PAYBRIDGE.' : 'PAYBRIDGE membalas HTTP '.$r->status().'.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Tidak bisa menghubungi PAYBRIDGE: '.mb_substr($e->getMessage(), 0, 160)];
        }
    }
}
