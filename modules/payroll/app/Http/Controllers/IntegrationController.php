<?php

namespace App\Http\Controllers;

use App\Exceptions\IntegrationException;
use App\Http\Controllers\Api\SdmWebhookController;
use App\Models\ApiClient;
use App\Models\InboundEvent;
use App\Models\WebhookDelivery;
use App\Services\AuditLedger;
use App\Services\ContractService;
use App\Services\SdmClient;
use App\Services\SdmSyncService;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/** Pengaturan klien REST API (Keuangan, SDM, Auditor), log webhook, dan koneksi ke SDM. Pola sama dengan "Integrasi API" PROCURA. */
class IntegrationController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:integrations.manage');
    }

    public function index(SdmClient $sdm)
    {
        return Inertia::render('Integrations/Index', [
            'clients' => ApiClient::withCount(['deliveries as failed_count' => fn ($q) => $q->whereIn('status', ['pending', 'failed'])])->orderBy('id')->get()->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'system' => $c->system, 'prefix' => $c->token_prefix, 'scopes' => $c->scopes, 'webhook_url' => $c->webhook_url, 'events' => $c->webhook_events ?? [],
                'active' => $c->is_active, 'last_used' => $c->last_used_at?->format('d/m/Y H:i'), 'last_ip' => $c->last_used_ip, 'failed' => $c->failed_count]),
            'deliveries' => WebhookDelivery::with('client:id,name')->orderByDesc('id')->limit(40)->get()->map(fn ($d) => ['id' => $d->id, 'client' => $d->client->name, 'event' => $d->event, 'status' => $d->status,
                'code' => $d->response_code, 'attempts' => $d->attempts, 'error' => $d->error, 'at' => $d->created_at->format('d/m H:i')]),
            'inbound' => InboundEvent::orderByDesc('id')->limit(30)->get()->map(fn ($e) => ['id' => $e->id, 'source' => $e->source, 'event' => $e->event, 'status' => $e->status, 'error' => $e->error, 'at' => $e->created_at->format('d/m H:i')]),
            'scopes' => ApiClient::SCOPES, 'events' => ApiClient::EVENTS, 'presets' => ApiClient::PRESETS,
            'sdm' => ['configured' => $sdm->configured(), 'url' => config('pay.integrations.sdm.url'), 'secret_set' => filled(config('pay.integrations.sdm.webhook_secret')), 'webhook_endpoint' => url('/api/sdm/webhook')],
            'apiBase' => url('/api/v1'),
            'credentials' => session('new_credentials'),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate(['name' => 'required|string|max:80', 'system' => 'required|in:sdm,keuangan,audit,lainnya', 'scopes' => 'required|array|min:1', 'scopes.*' => 'in:'.implode(',', array_keys(ApiClient::SCOPES)),
            'webhook_url' => 'nullable|url|max:500', 'events' => 'array', 'events.*' => 'in:'.implode(',', array_keys(ApiClient::EVENTS))]);
        [$plain, $prefix, $hash] = ApiClient::makeToken();
        $secret = 'whsec_'.Str::random(40);
        $c = ApiClient::create(['name' => $d['name'], 'system' => $d['system'], 'token_prefix' => $prefix, 'token_hash' => $hash, 'scopes' => $d['scopes'], 'webhook_url' => $d['webhook_url'] ?? null,
            'webhook_secret' => $secret, 'webhook_events' => $d['events'] ?? [], 'created_by' => $request->user()->id]);
        AuditLedger::record('integration.client_created', $c, ['system' => $c->system, 'scopes' => $c->scopes], $c->name);

        return back()->with('success', 'Klien dibuat. SALIN token & rahasia sekarang - tidak akan ditampilkan lagi.')->with('new_credentials', ['name' => $c->name, 'token' => $plain, 'secret' => $secret]);
    }

    public function update(Request $request, ApiClient $client)
    {
        $d = $request->validate(['scopes' => 'required|array|min:1', 'scopes.*' => 'in:'.implode(',', array_keys(ApiClient::SCOPES)), 'webhook_url' => 'nullable|url|max:500', 'events' => 'array', 'is_active' => 'boolean']);
        $client->update(['scopes' => $d['scopes'], 'webhook_url' => $d['webhook_url'] ?? null, 'webhook_events' => $d['events'] ?? [], 'is_active' => $d['is_active'] ?? true]);
        AuditLedger::record('integration.client_updated', $client, ['scopes' => $client->scopes, 'active' => $client->is_active], $client->name);

        return back()->with('success', 'Klien diperbarui.');
    }

    public function rotate(Request $request, ApiClient $client)
    {
        [$plain, $prefix, $hash] = ApiClient::makeToken();
        $secret = 'whsec_'.Str::random(40);
        $client->update(['token_prefix' => $prefix, 'token_hash' => $hash, 'webhook_secret' => $secret]);
        AuditLedger::record('integration.client_rotated', $client, [], $client->name);

        return back()->with('success', 'Token & rahasia dibuat ulang. Perbarui .env sistem klien.')->with('new_credentials', ['name' => $client->name, 'token' => $plain, 'secret' => $secret]);
    }

    public function destroy(ApiClient $client)
    {
        AuditLedger::record('integration.client_deleted', $client, [], $client->name);
        $client->delete();

        return back()->with('success', 'Klien dihapus.');
    }

    public function ping(ApiClient $client, WebhookService $svc)
    {
        $r = $svc->ping($client);

        return back()->with($r['ok'] ? 'success' : 'error', $r['message']);
    }

    public function resend(WebhookDelivery $delivery, WebhookService $svc)
    {
        $svc->send($delivery->fresh());

        return back()->with('success', 'Dikirim ulang; lihat statusnya di daftar.');
    }

    public function sdmTest(SdmClient $sdm)
    {
        try {
            $r = $sdm->ping();
        } catch (IntegrationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Terhubung ke SDM'.(isset($r['scopes']) ? ' (scope: '.implode(', ', $r['scopes']).')' : '').'.');
    }

    public function replay(InboundEvent $event, SdmSyncService $sync, ContractService $contracts)
    {
        try {
            $status = app(SdmWebhookController::class)->handle($event->event, $event->payload['data'] ?? [], $sync, $contracts, 'replay-'.$event->id);
            $event->update(['status' => $status, 'error' => null, 'processed_at' => now()]);
        } catch (\Throwable $e) {
            $event->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500)]);

            return back()->with('error', 'Gagal diproses ulang: '.$e->getMessage());
        }

        return back()->with('success', 'Kejadian diproses ulang.');
    }
}
