<?php

namespace App\Services;

use App\Models\ApiClient;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mengabari sistem lain (SDM, Keuangan) saat terjadi sesuatu di PAYBRIDGE. Tiap pengiriman ditandatangani HMAC-SHA256:
 *   X-Paybridge-Signature: sha256=<hmac(body mentah, webhook_secret klien)>
 * Kegagalan webhook TIDAK PERNAH menggagalkan aksi pengguna; hasilnya dicatat dan dicoba ulang (pay:webhooks-retry).
 */
class WebhookService
{
    public const MAX_ATTEMPTS = 6;

    public function dispatch(string $event, array $data): void
    {
        try {
            ApiClient::where('is_active', true)->whereNotNull('webhook_url')->get()
                ->filter(fn (ApiClient $c) => in_array($event, $c->webhook_events ?? [], true))
                ->each(function (ApiClient $client) use ($event, $data) {
                    $delivery = WebhookDelivery::create([
                        'api_client_id' => $client->id, 'event' => $event,
                        'payload' => ['event' => $event, 'occurred_at' => now()->toIso8601String(), 'data' => $data],
                    ]);
                    $this->send($delivery);
                });
        } catch (Throwable $e) {
            Log::warning('[Webhook] Gagal menjadwalkan pengiriman: '.$e->getMessage());
        }
    }

    public function send(WebhookDelivery $delivery): void
    {
        $client = $delivery->client;
        $body = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = 'sha256='.hash_hmac('sha256', $body, (string) $client->webhook_secret);
        $attempts = $delivery->attempts + 1;

        try {
            $response = Http::timeout((int) config('pay.integrations.api.webhook_timeout', 8))->connectTimeout(5)
                ->withHeaders([
                    'Content-Type' => 'application/json', 'X-Paybridge-Event' => $delivery->event,
                    'X-Paybridge-Delivery' => (string) $delivery->id, 'X-Paybridge-Signature' => $signature,
                ])->withBody($body, 'application/json')->post($client->webhook_url);

            $ok = $response->successful();
            $delivery->update([
                'attempts' => $attempts, 'response_code' => $response->status(),
                'status' => $ok ? 'delivered' : ($attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending'),
                'error' => $ok ? null : 'HTTP '.$response->status(),
                'delivered_at' => $ok ? now() : null,
                'next_retry_at' => $ok || $attempts >= self::MAX_ATTEMPTS ? null : now()->addMinutes(2 ** $attempts),
            ]);
        } catch (Throwable $e) {
            $delivery->update([
                'attempts' => $attempts, 'status' => $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending',
                'error' => mb_substr($e->getMessage(), 0, 300),
                'next_retry_at' => $attempts >= self::MAX_ATTEMPTS ? null : now()->addMinutes(2 ** $attempts),
            ]);
        }
    }

    /** Dipanggil scheduler: kirim ulang yang tertunda (backoff eksponensial 2,4,8.. menit). */
    public function retryDue(): int
    {
        $n = 0;
        WebhookDelivery::where('status', 'pending')->where('next_retry_at', '<=', now())->limit(100)->get()
            ->each(function ($d) use (&$n) {
                $this->send($d);
                $n++;
            });

        return $n;
    }

    public function ping(ApiClient $client): array
    {
        if (! $client->webhook_url) {
            return ['ok' => false, 'message' => 'Alamat webhook belum diisi.'];
        }
        $body = json_encode(['event' => 'ping', 'occurred_at' => now()->toIso8601String(), 'data' => ['client' => $client->name]]);
        try {
            $r = Http::timeout(8)->connectTimeout(5)->withHeaders([
                'X-Paybridge-Event' => 'ping', 'X-Paybridge-Delivery' => 'ping-'.uniqid(),
                'X-Paybridge-Signature' => 'sha256='.hash_hmac('sha256', $body, (string) $client->webhook_secret),
            ])->withBody($body, 'application/json')->post($client->webhook_url);

            return ['ok' => $r->successful(), 'message' => $r->successful() ? 'Ping diterima klien.' : 'Klien membalas HTTP '.$r->status().'.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Tidak bisa menghubungi klien: '.mb_substr($e->getMessage(), 0, 160)];
        }
    }
}
