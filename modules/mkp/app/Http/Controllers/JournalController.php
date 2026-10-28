<?php

namespace App\Http\Controllers;

use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Services\JournalService;
use Illuminate\Http\Request;
use App\Models\Attachment;
use Inertia\Inertia;

class JournalController extends Controller
{
    public function __construct(private JournalService $journalService)
    {
    }

    public function index(Request $request)
    {
        $journals = Journal::query()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('journal_date', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->whereDate('journal_date', '<=', $d))
            ->withCount('details')
            ->orderByDesc('journal_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Journals/Index', [
            'journals' => $journals,
            'filters' => $request->only(['status', 'date_from', 'date_to']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Journals/Create', [
            'accounts' => ChartOfAccount::postable()->orderBy('code')->get(['id', 'code', 'name', 'normal_balance']),
            'nextNumber' => Journal::generateJournalNumber(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'journal_date' => 'required|date',
            'type' => 'required|in:umum,penyesuaian,penutup,pembalik',
            'reference' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:chart_of_accounts,id',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
            'lines.*.description' => 'nullable|string',
        ]);

        $journal = $this->journalService->create(
            [
                'journal_date' => $validated['journal_date'],
                'type' => $validated['type'],
                'reference' => $validated['reference'] ?? null,
                'description' => $validated['description'] ?? null,
                'created_by' => $request->user()?->id,
            ],
            $validated['lines']
        );

        return redirect()->route('journals.show', $journal)->with('success', 'Jurnal berhasil disimpan sebagai draft.');
    }

    public function show(Request $request, Journal $journal)
    {
        $journal->load('details.account', 'creator', 'approver');

        return Inertia::render('Journals/Show', [
            'journal' => $journal,
            'attachments' => Attachment::panelFor($journal),
            'canApprove' => $journal->approval_status === 'pending' && (bool) $request->user()?->can('journal.approve'),
        ]);
    }

    public function edit(Journal $journal)
    {
        abort_unless($journal->status === 'draft', 403, 'Hanya jurnal draft yang dapat diedit.');
        $journal->load('details.account');

        return Inertia::render('Journals/Edit', [
            'journal' => $journal,
            'accounts' => ChartOfAccount::postable()->orderBy('code')->get(['id', 'code', 'name', 'normal_balance']),
        ]);
    }

    public function update(Request $request, Journal $journal)
    {
        $validated = $request->validate([
            'journal_date' => 'required|date',
            'type' => 'required|in:umum,penyesuaian,penutup,pembalik',
            'reference' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:chart_of_accounts,id',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
            'lines.*.description' => 'nullable|string',
        ]);

        $this->journalService->updateDraft(
            $journal,
            [
                'journal_date' => $validated['journal_date'],
                'type' => $validated['type'],
                'reference' => $validated['reference'] ?? null,
                'description' => $validated['description'] ?? null,
            ],
            $validated['lines']
        );

        return redirect()->route('journals.show', $journal)->with('success', 'Jurnal draft berhasil diperbarui.');
    }

    public function destroy(Journal $journal)
    {
        $this->journalService->deleteDraft($journal);

        return redirect()->route('journals.index')->with('success', 'Jurnal draft berhasil dihapus.');
    }

    public function post(Request $request, Journal $journal)
    {
        $this->journalService->post($journal, $request->user()->id);

        return back()->with('success', 'Jurnal berhasil diposting ke buku besar.');
    }

    public function void(Journal $journal)
    {
        $this->journalService->void($journal);

        return back()->with('success', 'Jurnal telah dibatalkan (void).');
    }

    public function approve(Request $request, Journal $journal)
    {
        $this->journalService->approve($journal, $request->user()->id);

        return back()->with('success', 'Jurnal disetujui. Sekarang dapat diposting.');
    }

    public function reject(Request $request, Journal $journal)
    {
        $validated = $request->validate(['rejection_reason' => 'nullable|string']);
        $this->journalService->reject($journal, $request->user()->id, $validated['rejection_reason'] ?? null);

        return back()->with('success', 'Jurnal ditolak.');
    }

    public function exportExcel(Request $request)
    {
        $start = $request->start_date ?? now()->startOfYear()->toDateString();
        $end = $request->end_date ?? now()->toDateString();

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\JournalExport($start, $end), "jurnal-{$start}-{$end}.xlsx");
    }
}
