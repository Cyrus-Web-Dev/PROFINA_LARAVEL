<?php

namespace Tests\Feature;

use App\Exceptions\ProcuraException;
use App\Models\ChartOfAccount;
use App\Models\Journal;
use App\Models\ProcurementFunding;
use App\Services\JournalService;
use App\Services\ProcurementFundingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Sisi Keuangan dari alur pengadaan: keputusan, pencairan (jurnal), LPJ (jurnal), dan webhook. */
class ProcurementFundingTest extends TestCase
{
    use RefreshDatabase;

    private ProcurementFundingService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        config(['procura.url' => 'http://procura.test/api/v1', 'procura.token' => 'pcr_x', 'procura.webhook_secret' => 'whsec_uji']);
        $this->svc = app(ProcurementFundingService::class);
    }

    private function remote(array $o = []): array
    {
        return array_merge([
            'number' => 'FR-2610-0001', 'request_number' => 'PR-2610-0001', 'request_title' => 'Laptop staf', 'category' => 'elektronik', 'status' => 'submitted',
            'amount' => 20000000, 'purpose' => 'Beli laptop', 'submitted_at' => now()->toIso8601String(), 'approved_amount' => null, 'decided_by' => null,
            'decided_at' => null, 'decision_note' => null, 'finance_ref' => null, 'disbursed_amount' => null, 'disbursed_at' => null,
            'actual_spent' => null, 'returned_amount' => null, 'settled_at' => null,
        ], $o);
    }

    /** Kas dengan saldo awal 100 juta (Dr Kas / Kr Modal). */
    private function cashWithBalance(): ChartOfAccount
    {
        $kas = ChartOfAccount::create(['code' => '1-1100', 'name' => 'Kas', 'type' => 'aset', 'category' => 'aset_lancar', 'normal_balance' => 'debit', 'is_active' => true, 'is_header' => false, 'is_cash_bank' => true]);
        $modal = $this->makeAccount('3-1100', 'Modal', 'ekuitas');
        $j = app(JournalService::class)->create(['journal_date' => now()->toDateString(), 'type' => 'umum', 'description' => 'Saldo awal'], [
            ['account_id' => $kas->id, 'debit' => 100000000, 'credit' => 0], ['account_id' => $modal->id, 'debit' => 0, 'credit' => 100000000],
        ], true);
        app(JournalService::class)->post($j, 1);

        return $kas;
    }

    public function test_upsert_creates_funding_and_incoming_report(): void
    {
        $f = $this->svc->upsert($this->remote());

        $this->assertSame('submitted', $f->status);
        $this->assertDatabaseHas('procurement_reports', ['funding_id' => $f->id, 'direction' => 'masuk', 'kind' => 'pengajuan']);
        // pengajuan yang masih menunggu persetujuan internal Pengadaan tidak boleh muncul di Keuangan
        $this->assertNull($this->svc->upsert($this->remote(['number' => 'FR-2610-0002', 'status' => 'pending_internal'])));
    }

    public function test_full_cycle_posts_balanced_journals_and_clears_advance_account(): void
    {
        Http::fake(['procura.test/*' => Http::response(['data' => []], 200)]);
        $manager = $this->makeUser('admin');
        $kas = $this->cashWithBalance();
        $beban = $this->makeAccount('1-2400', 'Peralatan Kantor', 'aset', 'aset_tetap');
        $f = $this->svc->upsert($this->remote());

        $this->svc->decide($f, 'approve', 19980000, 'OK', $manager);
        $f->refresh();
        $this->assertSame('approved', $f->status);
        $this->assertMatchesRegularExpression('/^KDP-\d{4}-0001$/', $f->finance_ref);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/funding/FR-2610-0001/decision') && $r['decision'] === 'approve' && $r['finance_ref'] === $f->finance_ref);

        $this->svc->disburse($f, ['cash_account_id' => $kas->id, 'amount' => 19980000, 'date' => now()->toDateString(), 'method' => 'transfer', 'reference' => 'TRF-1'], $manager);
        $f->refresh();
        $advance = ChartOfAccount::where('code', '1-1700')->first();
        $this->assertSame('disbursed', $f->status);
        $this->assertEquals(19980000.0, $advance->getBalance());
        $this->assertEquals(80020000.0, $kas->getBalance());

        // LPJ masuk dari PROCURA lewat sinkron: terpakai 19.950.000, sisa 30.000
        $this->svc->upsert($this->remote(['status' => 'settled', 'approved_amount' => 19980000, 'disbursed_amount' => 19980000, 'actual_spent' => 19950000, 'returned_amount' => 30000, 'settled_at' => now()->toIso8601String()]));
        $f->refresh();
        $this->assertSame('settled', $f->status);

        $this->svc->bookSettlement($f, ['account_id' => $beban->id, 'date' => now()->toDateString(), 'return_account_id' => $kas->id], $manager);
        $f->refresh();
        $this->assertSame('closed', $f->status);
        $this->assertEquals(0.0, $advance->getBalance());           // uang muka lunas
        $this->assertEquals(19950000.0, $beban->getBalance());      // dikapitalisasi
        $this->assertEquals(80050000.0, $kas->getBalance());        // sisa 30.000 kembali
        $this->assertSame(0, Journal::where('status', 'posted')->get()->filter(fn ($j) => round($j->total_debit, 2) !== round($j->total_credit, 2))->count());

        // sinkron ulang setelah dijurnal tidak boleh membuka kembali statusnya
        $this->svc->upsert($this->remote(['status' => 'settled', 'disbursed_amount' => 19980000, 'actual_spent' => 19950000, 'returned_amount' => 30000, 'settled_at' => now()->toIso8601String()]));
        $this->assertSame('closed', $f->fresh()->status);
    }

    public function test_large_approval_requires_journal_approve_permission(): void
    {
        Http::fake(['procura.test/*' => Http::response(['data' => []], 200)]);
        config(['procura.approval_threshold' => 10000000]);
        $staff = $this->makeUser('staff', ['procurement.decide']);
        $f = $this->svc->upsert($this->remote());

        $this->expectException(ValidationException::class);
        $this->svc->decide($f, 'approve', null, null, $staff);
    }

    public function test_reject_requires_a_reason(): void
    {
        Http::fake();
        $manager = $this->makeUser('admin');
        $f = $this->svc->upsert($this->remote());

        $this->expectException(ValidationException::class);
        $this->svc->decide($f, 'reject', null, '', $manager);
    }

    public function test_disbursement_is_rolled_back_when_procura_rejects_it(): void
    {
        $manager = $this->makeUser('admin');
        $kas = $this->cashWithBalance();
        Http::fake(['procura.test/*' => Http::response(['data' => []], 200)]);
        $f = $this->svc->upsert($this->remote());
        $this->svc->decide($f, 'approve', null, null, $manager);
        $journalsBefore = Journal::count();

        Http::fake(['procura.test/*' => Http::response(['error' => ['code' => 'workflow_violation', 'message' => 'Tidak bisa dicairkan.']], 422)]);
        try {
            $this->svc->disburse($f->fresh(), ['cash_account_id' => $kas->id, 'amount' => 20000000, 'date' => now()->toDateString(), 'method' => 'transfer'], $manager);
            $this->fail('Seharusnya melempar ProcuraException');
        } catch (ProcuraException) {
            // diharapkan
        }

        $this->assertSame($journalsBefore, Journal::count());           // tidak ada jurnal setengah jadi
        $this->assertSame('approved', $f->fresh()->status);
    }

    public function test_disbursement_is_refused_when_cash_balance_is_insufficient(): void
    {
        Http::fake(['procura.test/*' => Http::response(['data' => []], 200)]);
        $manager = $this->makeUser('admin');
        $kas = ChartOfAccount::create(['code' => '1-1100', 'name' => 'Kas', 'type' => 'aset', 'category' => 'aset_lancar', 'normal_balance' => 'debit', 'is_active' => true, 'is_header' => false, 'is_cash_bank' => true]);
        $f = $this->svc->upsert($this->remote());
        $this->svc->decide($f, 'approve', null, null, $manager);

        $this->expectException(ValidationException::class);
        $this->svc->disburse($f->fresh(), ['cash_account_id' => $kas->id, 'amount' => 20000000, 'date' => now()->toDateString(), 'method' => 'transfer'], $manager);
    }

    public function test_webhook_requires_valid_signature_and_is_idempotent(): void
    {
        $body = json_encode(['event' => 'funding.submitted', 'data' => $this->remote()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sig = 'sha256='.hash_hmac('sha256', $body, 'whsec_uji');
        $headers = ['X-Procura-Event' => 'funding.submitted', 'X-Procura-Delivery' => '42', 'X-Procura-Signature' => $sig, 'Content-Type' => 'application/json'];

        $this->call('POST', '/api/procura/webhook', [], [], [], $this->transformHeadersToServerVars(array_merge($headers, ['X-Procura-Signature' => 'sha256=salah'])), $body)->assertStatus(401);
        $this->assertSame(0, ProcurementFunding::count());

        $this->call('POST', '/api/procura/webhook', [], [], [], $this->transformHeadersToServerVars($headers), $body)->assertOk();
        $this->assertSame(1, ProcurementFunding::count());

        $this->call('POST', '/api/procura/webhook', [], [], [], $this->transformHeadersToServerVars($headers), $body)->assertOk()->assertJson(['duplicate' => true]);
        $this->assertSame(1, ProcurementFunding::count());
    }
}
