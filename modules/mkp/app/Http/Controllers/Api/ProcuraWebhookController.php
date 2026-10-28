<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProcurementReport;
use App\Services\ProcurementFundingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Penerima webhook PROCURA (POST /api/procura/webhook).
 * Event yang dipakai Keuangan: funding.submitted (pengajuan masuk) dan funding.settled (LPJ diserahkan). Payload sudah memuat
 * data dana lengkap, jadi handler TIDAK memanggil balik PROCURA (server `artisan serve` satu-utas akan saling menunggu).
 * Keamanan: HMAC-SHA256 atas isi mentah (X-Procura-Signature) + idempotensi lewat X-Procura-Delivery.
 */
class ProcuraWebhookController extends Controller
{
    public function __invoke(Request $request, ProcurementFundingService $svc): JsonResponse
    {
        $secret = config('procura.webhook_secret');
        if (! $secret) {
            return response()->json(['error' => 'Webhook belum dikonfigurasi (PROCURA_WEBHOOK_SECRET kosong).'], 503);
        }

        $body = $request->getContent();
        if (! hash_equals('sha256='.hash_hmac('sha256', $body, $secret), (string) $request->header('X-Procura-Signature'))) {
            return response()->json(['error' => 'Tanda tangan tidak valid.'], 401);
        }

        $payload = json_decode($body, true);
        if (! is_array($payload)) {
            return response()->json(['error' => 'Isi bukan JSON.'], 400);
        }

        $event = (string) ($request->header('X-Procura-Event') ?: ($payload['event'] ?? ''));
        if ($event === 'ping') {
            return response()->json(['ok' => true, 'pong' => true]);
        }

        $delivery = (string) $request->header('X-Procura-Delivery');
        if ($delivery !== '' && ProcurementReport::where('delivery_id', $delivery)->exists()) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        if (in_array($event, ['funding.submitted', 'funding.settled'], true)) {
            try {
                $svc->upsert($payload['data'] ?? [], $delivery !== '' ? $delivery : null);
            } catch (Throwable $e) {
                // Tetap 200 agar PROCURA tidak mengulang terus; `procura:sync` akan mengoreksi.
                Log::error('[PROCURA webhook] '.$event.': '.$e->getMessage());
            }
        }

        return response()->json(['ok' => true]);
    }
}
