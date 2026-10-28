<?php

namespace App\Http\Controllers;

use App\Exceptions\ProcuraException;
use App\Models\Attachment;
use App\Models\ChartOfAccount;
use App\Models\ProcurementFunding;
use App\Models\ProcurementReport;
use App\Services\ProcuraClient;
use App\Services\ProcurementFundingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/** Halaman "Dana Pengadaan": pengajuan dana dari Pengadaan, keputusan, pencairan, LPJ, dan laporan. */
class ProcurementFundingController extends Controller
{
    private const TABS = ['waiting', 'decided', 'cash', 'settle', 'all', 'reports'];

    public function __construct(private ProcurementFundingService $svc, private ProcuraClient $client)
    {
    }

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'waiting';
        $search = trim((string) $request->query('search', ''));

        $q = ProcurementFunding::query()
            ->when($tab === 'waiting', fn ($w) => $w->where('status', 'submitted'))
            ->when($tab === 'decided', fn ($w) => $w->whereIn('status', ['approved', 'rejected', 'revision']))
            ->when($tab === 'cash', fn ($w) => $w->whereIn('status', ['approved', 'disbursed']))
            ->when($tab === 'settle', fn ($w) => $w->whereIn('status', ['settled', 'closed']))
            ->when($search !== '', fn ($w) => $w->where(fn ($x) => $x
                ->whereRaw('LOWER(procura_number) LIKE ?', ['%'.mb_strtolower($search).'%'])
                ->orWhereRaw('LOWER(request_title) LIKE ?', ['%'.mb_strtolower($search).'%'])
                ->orWhereRaw('LOWER(finance_ref) LIKE ?', ['%'.mb_strtolower($search).'%'])))
            ->orderByDesc('id');

        $reports = $tab === 'reports'
            ? ProcurementReport::with(['funding:id,procura_number', 'author:id,name'])->orderByDesc('id')->paginate(12)->withQueryString()
                ->through(fn (ProcurementReport $r) => [
                    'id' => $r->id, 'direction' => $r->direction, 'kind' => $r->kind, 'subject' => $r->subject, 'body' => $r->body, 'read' => (bool) $r->read_at,
                    'created_at' => $r->created_at->format('Y-m-d H:i'), 'funding_id' => $r->funding_id, 'funding_number' => $r->funding?->procura_number, 'author' => $r->author?->name,
                ])
            : null;

        return Inertia::render('Procurement/Index', [
            'tab' => $tab,
            'filters' => ['search' => $search],
            'funds' => $tab === 'reports' ? null : $q->paginate(12)->withQueryString()->through(fn (ProcurementFunding $f) => $this->row($f)),
            'reports' => $reports,
            'counts' => [
                'waiting' => ProcurementFunding::where('status', 'submitted')->count(),
                'cash' => ProcurementFunding::where('status', 'approved')->count(),
                'settle' => ProcurementFunding::where('status', 'settled')->count(),
                'unread' => ProcurementReport::unread(),
            ],
            'totals' => [
                'waiting' => (float) ProcurementFunding::where('status', 'submitted')->sum('amount'),
                'approved' => (float) ProcurementFunding::where('status', 'approved')->sum('approved_amount'),
                'outstanding' => (float) ProcurementFunding::where('status', 'disbursed')->sum('disbursed_amount'),
            ],
            'fundingOptions' => $tab === 'reports' ? ProcurementFunding::orderByDesc('id')->limit(80)->get(['id', 'procura_number', 'request_title']) : [],
            'connected' => $this->client->configured(),
            'lastSync' => ProcurementFunding::max('synced_at'),
        ]);
    }

    public function show(ProcurementFunding $funding)
    {
        $funding->load(['cashAccount:id,code,name', 'settlementAccount:id,code,name', 'disbursementJournal:id,journal_number', 'settlementJournal:id,journal_number', 'reports.author:id,name']);

        $cashAccounts = ChartOfAccount::cashBank()->orderBy('code')->get(['id', 'code', 'name'])
            ->map(fn ($a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'balance' => $a->getBalance()]);

        return Inertia::render('Procurement/Show', [
            'funding' => [
                ...$funding->only(['id', 'procura_number', 'request_number', 'request_title', 'category', 'amount', 'purpose', 'status', 'finance_ref', 'approved_amount', 'decided_by_name', 'decision_note',
                    'disbursed_amount', 'disbursement_method', 'disbursement_ref', 'actual_spent', 'returned_amount']),
                'submitted_at' => $funding->submitted_at?->format('Y-m-d'), 'decided_at' => $funding->decided_at?->format('Y-m-d'),
                'disbursed_at' => $funding->disbursed_at?->format('Y-m-d'), 'settled_at' => $funding->settled_at?->format('Y-m-d'),
                'cash_account' => $funding->cashAccount, 'settlement_account' => $funding->settlementAccount,
                'disbursement_journal' => $funding->disbursementJournal, 'settlement_journal' => $funding->settlementJournal,
                'synced_at' => $funding->synced_at?->format('Y-m-d H:i'), 'last_error' => $funding->last_error,
                'attachments' => Attachment::panelFor($funding),
            ],
            'reports' => $funding->reports->map(fn ($r) => [
                'id' => $r->id, 'direction' => $r->direction, 'kind' => $r->kind, 'subject' => $r->subject, 'body' => $r->body,
                'created_at' => $r->created_at->format('Y-m-d H:i'), 'author' => $r->author?->name,
            ]),
            'cashAccounts' => $cashAccounts,
            'expenseAccounts' => ChartOfAccount::postable()->whereIn('type', ['aset', 'beban'])->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'suggestedAccountId' => $this->svc->suggestedAccountId($funding->category),
            'capitalCategory' => in_array($funding->category, config('procura.capital_categories'), true),
            'threshold' => (float) config('procura.approval_threshold'),
        ]);
    }

    public function sync(Request $request)
    {
        try {
            $n = $this->svc->syncAll();
        } catch (ProcuraException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return back()->with('success', $n ? "{$n} pengajuan dimuat dari Pengadaan." : 'Tidak ada pengajuan di Pengadaan.');
    }

    public function refresh(ProcurementFunding $funding)
    {
        return $this->svc->syncOne($funding)
            ? back()->with('success', 'Data terbaru dari Pengadaan sudah dimuat.')
            : back()->withErrors(['general' => $funding->fresh()->last_error ?? 'Gagal memuat data dari Pengadaan.']);
    }

    public function decide(Request $request, ProcurementFunding $funding)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject,revision'],
            'approved_amount' => ['nullable', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], ['decision.required' => 'Pilih keputusan.', 'approved_amount.gt' => 'Nominal harus lebih dari nol.']);

        return $this->run(fn () => $this->svc->decide($funding, $data['decision'], isset($data['approved_amount']) ? (float) $data['approved_amount'] : null, $data['note'] ?? null, $request->user()),
            'Keputusan dicatat dan Pengadaan sudah diberi tahu.');
    }

    public function disburse(Request $request, ProcurementFunding $funding)
    {
        $data = $request->validate([
            'cash_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', 'in:transfer,tunai,cek'],
            'reference' => ['nullable', 'string', 'max:60'],
        ], ['cash_account_id.required' => 'Pilih akun Kas/Bank sumber dana.', 'date.before_or_equal' => 'Tanggal pencairan tidak boleh di masa depan.']);

        return $this->run(fn () => $this->svc->disburse($funding, $data, $request->user()), 'Dana dicairkan, jurnal dibuat, dan Pengadaan sudah diberi tahu.');
    }

    public function settle(Request $request, ProcurementFunding $funding)
    {
        $data = $request->validate([
            'account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'return_account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $data['account_id'] = $data['account_id'] ?? 0;

        return $this->run(fn () => $this->svc->bookSettlement($funding, $data, $request->user()), 'LPJ dijurnal dan Pengadaan sudah diberi tahu.');
    }

    public function note(Request $request)
    {
        $data = $request->validate([
            'funding_id' => ['nullable', 'integer', 'exists:procurement_fundings,id'],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:3000'],
        ], ['subject.required' => 'Isi perihal laporan.']);

        $f = ! empty($data['funding_id']) ? ProcurementFunding::find($data['funding_id']) : null;

        return $this->run(fn () => $this->svc->sendNote($f, $data['subject'], $data['body'] ?? null, $request->user()), 'Laporan terkirim ke Pengadaan.');
    }

    public function read(ProcurementReport $report)
    {
        $report->update(['read_at' => $report->read_at ?? now()]);

        return back();
    }

    public function readAll()
    {
        ProcurementReport::where('direction', 'masuk')->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Semua laporan ditandai sudah dibaca.');
    }

    /** Jalankan aksi; kegagalan komunikasi dengan PROCURA ditampilkan sebagai banner (kunci error "general"). */
    private function run(\Closure $action, string $success)
    {
        try {
            $action();
        } catch (ProcuraException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }

        return back()->with('success', $success);
    }

    private function row(ProcurementFunding $f): array
    {
        return [
            'id' => $f->id, 'procura_number' => $f->procura_number, 'request_title' => $f->request_title, 'request_number' => $f->request_number, 'category' => $f->category,
            'status' => $f->status, 'amount' => $f->amount, 'approved_amount' => $f->approved_amount, 'disbursed_amount' => $f->disbursed_amount, 'actual_spent' => $f->actual_spent,
            'returned_amount' => $f->returned_amount, 'decided_by_name' => $f->decided_by_name, 'decision_note' => $f->decision_note, 'finance_ref' => $f->finance_ref,
            'submitted_at' => $f->submitted_at?->format('Y-m-d'), 'decided_at' => $f->decided_at?->format('Y-m-d'),
        ];
    }
}
