<?php

namespace App\Http\Controllers;

use App\Models\AuditEntry;
use App\Services\AuditLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class AuditController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:audit.view');
    }

    public function index(Request $request)
    {
        $q = AuditEntry::query()->when($request->event, fn ($x, $e) => $x->where('event', 'like', $e.'%'))->when($request->actor, fn ($x, $a) => $x->where('actor_name', 'ilike', "%$a%"))
            ->when($request->subject, fn ($x, $s) => $x->where('subject_label', 'ilike', "%$s%"))->orderByDesc('seq');

        return Inertia::render('Audit/Index', [
            'entries' => $q->paginate(30)->withQueryString()->through(fn ($e) => ['seq' => $e->seq, 'event' => $e->event, 'actor' => $e->actor_name, 'ip' => $e->ip, 'subject' => $e->subject_label, 'type' => $e->subject_type,
                'payload' => json_decode($e->payload, true), 'hash' => $e->hash, 'prev' => $e->prev_hash, 'at' => $e->occurred_at]),
            'filters' => $request->only('event', 'actor', 'subject'),
            'last' => Cache::get('pay.audit.last'), 'total' => AuditEntry::count(), 'head' => AuditEntry::orderByDesc('seq')->value('hash'),
        ]);
    }

    public function verify()
    {
        $r = AuditLedger::verify();
        $r['at'] = now()->format('d/m/Y H:i');
        Cache::forever('pay.audit.last', $r);

        return back()->with($r['ok'] ? 'success' : 'error', $r['ok'] ? "Rantai audit UTUH: {$r['checked']} entri terverifikasi." : "RANTAI AUDIT RUSAK pada entri #{$r['broken_at']}: {$r['reason']}");
    }

    public function export()
    {
        $rows = AuditEntry::orderBy('seq')->cursor();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['seq', 'waktu_utc', 'event', 'aktor', 'ip', 'objek', 'payload', 'prev_hash', 'hash']);
            foreach ($rows as $e) {
                fputcsv($out, [$e->seq, $e->occurred_at, $e->event, $e->actor_name, $e->ip, $e->subject_label, $e->payload, $e->prev_hash, $e->hash]);
            }
            fclose($out);
        }, 'jejak-audit-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }
}
