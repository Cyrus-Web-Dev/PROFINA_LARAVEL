<?php

namespace App\Http\Controllers;

use App\Models\IntegrationClient;
use App\Models\IntegrationDelivery;
use App\Models\IntegrationInbox;
use App\Models\PayslipMirror;
use App\Services\ActivityLogger;
use App\Services\PayrollBridge;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/** Halaman "Integrasi Payroll": klien API untuk PAYBRIDGE, webhook keluar/masuk, dan slip gaji dari Payroll. */
class PayrollIntegrationController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:integrations.manage')->except(['mine']);
    }

    public function index()
    {
        return Inertia::render('Integrations/Payroll', [
            'clients' => IntegrationClient::withCount(['deliveries as failed_count' => fn ($q) => $q->whereIn('status', ['pending', 'failed'])])->orderBy('id')->get()->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'prefix' => $c->token_prefix, 'scopes' => $c->scopes, 'webhook_url' => $c->webhook_url, 'events' => $c->webhook_events ?? [],
                'active' => $c->is_active, 'last_used' => $c->last_used_at?->format('d/m/Y H:i'), 'last_ip' => $c->last_used_ip, 'failed' => $c->failed_count]),
            'deliveries' => IntegrationDelivery::with('client:id,name')->orderByDesc('id')->limit(40)->get()->map(fn ($d) => ['id' => $d->id, 'client' => $d->client->name, 'event' => $d->event,
                'status' => $d->status, 'code' => $d->response_code, 'attempts' => $d->attempts, 'error' => $d->error, 'at' => $d->created_at->format('d/m H:i')]),
            'inbox' => IntegrationInbox::orderByDesc('id')->limit(30)->get()->map(fn ($e) => ['id' => $e->id, 'event' => $e->event, 'status' => $e->status, 'error' => $e->error, 'at' => $e->created_at->format('d/m H:i')]),
            'runs' => PayslipMirror::selectRaw('run_number, period_year, period_month, count(*) as payslips, max(published_at) as published_at')->groupBy('run_number', 'period_year', 'period_month')
                ->orderByDesc('period_year')->orderByDesc('period_month')->limit(12)->get(),
            'scopes' => IntegrationClient::SCOPES, 'events' => IntegrationClient::EVENTS,
            'status' => [
                'external' => (bool) config('hr.payroll.external'), 'secret_set' => filled(config('hr.payroll.webhook_secret')), 'portal_url' => config('hr.payroll.portal_url'),
                'api_base' => url('/api/payroll/v1'), 'webhook_endpoint' => url('/api/payroll/webhook'), 'unrecorded_as_alpha' => (bool) config('hr.payroll.count_unrecorded_as_alpha'),
            ],
            'credentials' => session('new_credentials'),
        ]);
    }

    public function store(Request $request)
    {
        $d = $request->validate(['name' => 'required|string|max:80', 'scopes' => 'required|array|min:1', 'scopes.*' => 'in:'.implode(',', array_keys(IntegrationClient::SCOPES)),
            'webhook_url' => 'nullable|url|max:500', 'events' => 'array', 'events.*' => 'in:'.implode(',', array_keys(IntegrationClient::EVENTS))]);
        [$plain, $prefix, $hash] = IntegrationClient::makeToken();
        $secret = 'whsec_'.Str::random(40);
        $c = IntegrationClient::create(['name' => $d['name'], 'system' => 'payroll', 'token_prefix' => $prefix, 'token_hash' => $hash, 'scopes' => $d['scopes'], 'webhook_url' => $d['webhook_url'] ?? null,
            'webhook_secret' => $secret, 'webhook_events' => $d['events'] ?? [], 'created_by' => $request->user()->id]);
        ActivityLogger::log('integration.client_created', "Membuat klien integrasi: {$c->name}", $c, $request->user()->id);

        return back()->with('success', 'Klien dibuat. SALIN token & rahasia sekarang - tidak ditampilkan lagi.')->with('new_credentials', ['name' => $c->name, 'token' => $plain, 'secret' => $secret]);
    }

    public function update(Request $request, IntegrationClient $client)
    {
        $d = $request->validate(['scopes' => 'required|array|min:1', 'scopes.*' => 'in:'.implode(',', array_keys(IntegrationClient::SCOPES)), 'webhook_url' => 'nullable|url|max:500', 'events' => 'array', 'is_active' => 'boolean']);
        $client->update(['scopes' => $d['scopes'], 'webhook_url' => $d['webhook_url'] ?? null, 'webhook_events' => $d['events'] ?? [], 'is_active' => $d['is_active'] ?? true]);
        ActivityLogger::log('integration.client_updated', "Memperbarui klien integrasi: {$client->name}", $client, $request->user()->id);

        return back()->with('success', 'Klien diperbarui.');
    }

    public function rotate(Request $request, IntegrationClient $client)
    {
        [$plain, $prefix, $hash] = IntegrationClient::makeToken();
        $secret = 'whsec_'.Str::random(40);
        $client->update(['token_prefix' => $prefix, 'token_hash' => $hash, 'webhook_secret' => $secret]);
        ActivityLogger::log('integration.client_rotated', "Token klien integrasi dibuat ulang: {$client->name}", $client, $request->user()->id);

        return back()->with('success', 'Token & rahasia dibuat ulang. Perbarui .env PAYBRIDGE.')->with('new_credentials', ['name' => $client->name, 'token' => $plain, 'secret' => $secret]);
    }

    public function destroy(Request $request, IntegrationClient $client)
    {
        ActivityLogger::log('integration.client_deleted', "Menghapus klien integrasi: {$client->name}", null, $request->user()->id);
        $client->delete();

        return back()->with('success', 'Klien dihapus.');
    }

    public function ping(IntegrationClient $client, PayrollBridge $bridge)
    {
        $r = $bridge->ping($client);

        return back()->with($r['ok'] ? 'success' : 'error', $r['message']);
    }

    public function resend(IntegrationDelivery $delivery, PayrollBridge $bridge)
    {
        $bridge->send($delivery->fresh());

        return back()->with('success', 'Dikirim ulang; lihat statusnya di daftar.');
    }

    /** Slip gaji saya yang diterbitkan PAYBRIDGE (ringkasan; PDF diunduh dari portal Payroll). */
    public function mine(Request $request)
    {
        $emp = $request->user()->employee;

        return Inertia::render('Payroll/External', [
            'linked' => (bool) $emp,
            'portalUrl' => config('hr.payroll.portal_url'),
            'payslips' => $emp ? PayslipMirror::where('employee_id', $emp->id)->orderByDesc('period_year')->orderByDesc('period_month')->get()
                ->map(fn ($p) => ['id' => $p->id, 'run' => $p->run_number, 'type' => $p->run_type, 'period' => sprintf('%02d/%d', $p->period_month, $p->period_year), 'gross' => $p->gross, 'net' => $p->net]) : [],
        ]);
    }
}
