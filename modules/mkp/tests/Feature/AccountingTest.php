<?php

namespace Tests\Feature;

use App\Services\FinancialReportService;
use App\Services\JournalService;
use App\Services\PeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<int, array{0: \App\Models\ChartOfAccount, 1: float, 2: float}> $lines */
    private function journal(array $lines, string $date = '2026-01-10', string $type = 'umum')
    {
        $rows = array_map(fn ($l) => ['account_id' => $l[0]->id, 'debit' => $l[1], 'credit' => $l[2]], $lines);

        return app(JournalService::class)->create([
            'journal_date' => $date,
            'type' => $type,
            'description' => 'Tes',
        ], $rows);
    }

    public function test_unbalanced_journal_is_rejected(): void
    {
        $kas = $this->makeAccount('1-1000', 'Kas', 'aset', 'aset_lancar');
        $pendapatan = $this->makeAccount('4-1000', 'Pendapatan', 'pendapatan');

        $this->expectException(ValidationException::class);
        $this->journal([[$kas, 100, 0], [$pendapatan, 0, 90]]);
    }

    public function test_a_line_cannot_have_both_debit_and_credit(): void
    {
        $kas = $this->makeAccount('1-1000', 'Kas', 'aset', 'aset_lancar');
        $pendapatan = $this->makeAccount('4-1000', 'Pendapatan', 'pendapatan');

        $this->expectException(ValidationException::class);
        $this->journal([[$kas, 100, 50], [$pendapatan, 0, 50]]);
    }

    public function test_posted_journal_appears_in_a_balanced_trial_balance(): void
    {
        $user = $this->makeUser('admin');
        $kas = $this->makeAccount('1-1000', 'Kas', 'aset', 'aset_lancar');
        $pendapatan = $this->makeAccount('4-1000', 'Pendapatan', 'pendapatan');

        $journal = $this->journal([[$kas, 100000, 0], [$pendapatan, 0, 100000]]);
        app(JournalService::class)->post($journal, $user->id);

        $tb = app(FinancialReportService::class)->trialBalance('2026-12-31');

        $this->assertTrue($tb['is_balanced']);
        $this->assertEquals(100000, $tb['total_debit']);
        $this->assertEquals(100000, $tb['total_credit']);
    }

    public function test_balance_sheet_balances_when_a_contra_account_is_used(): void
    {
        // Regresi: akun kontra (Akumulasi Penyusutan) dulu dijumlahkan sebagai positif -> Neraca tidak seimbang.
        $user = $this->makeUser('admin');
        $svc = app(JournalService::class);

        $kas = $this->makeAccount('1-1000', 'Kas', 'aset', 'aset_lancar');
        $alat = $this->makeAccount('1-2000', 'Peralatan', 'aset', 'aset_tetap');
        $akum = $this->makeAccount('1-2100', 'Akumulasi Penyusutan', 'aset', 'aset_tetap', 'kredit');
        $modal = $this->makeAccount('3-1000', 'Modal', 'ekuitas');
        $beban = $this->makeAccount('6-1000', 'Beban Penyusutan', 'beban');

        $svc->post($this->journal([[$kas, 1000, 0], [$modal, 0, 1000]]), $user->id);
        $svc->post($this->journal([[$alat, 600, 0], [$kas, 0, 600]]), $user->id);
        $svc->post($this->journal([[$beban, 100, 0], [$akum, 0, 100]]), $user->id);

        $bs = app(FinancialReportService::class)->balanceSheet('2026-12-31');

        $this->assertEquals(900, $bs['total_assets']);       // 400 + 600 - 100
        $this->assertEquals(900, $bs['total_equity']);       // 1000 - 100 laba berjalan (rugi)
        $this->assertTrue($bs['is_balanced']);
    }

    public function test_reversal_journals_cannot_be_voided_but_manual_journals_can(): void
    {
        $user = $this->makeUser('admin');
        $svc = app(JournalService::class);
        $kas = $this->makeAccount('1-1000', 'Kas', 'aset', 'aset_lancar');
        $pend = $this->makeAccount('4-1000', 'Pendapatan', 'pendapatan');

        $manual = $svc->post($this->journal([[$kas, 10, 0], [$pend, 0, 10]]), $user->id);
        $this->assertSame('void', $svc->void($manual)->status);

        $reversal = $svc->post($this->journal([[$kas, 10, 0], [$pend, 0, 10]], '2026-01-11', 'pembalik'), $user->id);
        $this->expectException(ValidationException::class);
        $svc->void($reversal);
    }

    /**
     * Regresi: kolom tanggal (cast Eloquent 'date') secara fisik tersimpan dengan jam
     * "00:00:00" di database (SQLite menyimpannya apa adanya, tidak seperti MySQL/PostgreSQL
     * yang otomatis memotongnya). Menutup TEPAT di tanggal 1 dan tanggal terakhir bulan pernah
     * gagal terkunci/gagal ditemukan lagi karena perbandingan where() persis tidak cocok dengan
     * nilai berjam itu. PeriodService sekarang memakai whereDate() di semua tempat; tes ini
     * mengunci perilaku itu supaya tidak diganti balik ke where() biasa secara tidak sengaja.
     */
    public function test_period_boundary_dates_are_handled_correctly(): void
    {
        $user = $this->makeUser('admin');
        $svc = app(JournalService::class);
        $periods = app(PeriodService::class);
        $kas = $this->makeAccount('1-1000', 'Kas', 'aset', 'aset_lancar');
        $pend = $this->makeAccount('4-1000', 'Pendapatan', 'pendapatan');

        // Jurnal tepat di tanggal 1 dan tanggal terakhir bulan (kasus batas).
        $svc->post($this->journal([[$kas, 1, 0], [$pend, 0, 1]], '2026-04-01'), $user->id);
        $svc->post($this->journal([[$kas, 1, 0], [$pend, 0, 1]], '2026-04-30'), $user->id);

        $periods->close(2026, 4);

        $this->assertTrue($periods->isClosed('2026-04-01'), 'Tanggal 1 seharusnya ikut terkunci.');
        $this->assertTrue($periods->isClosed('2026-04-30'), 'Tanggal terakhir bulan seharusnya ikut terkunci.');
        $this->assertFalse($periods->isClosed('2026-05-01'), 'Bulan berikutnya tidak boleh ikut terkunci.');

        // reopen() harus menemukan periode yang baru ditutup (dulu gagal: "Periode ini memang belum ditutup").
        $periods->reopen(2026, 4, 'Uji regresi');
        $this->assertFalse($periods->isClosed('2026-04-15'));

        // Menutup bulan KEDUA di tahun yang sama tidak boleh membuat FiscalYear duplikat
        // (dulu terjadi karena FiscalYear::firstOrCreate mencocokkan start_date secara persis).
        $svc->post($this->journal([[$kas, 1, 0], [$pend, 0, 1]], '2026-05-10'), $user->id);
        $periods->close(2026, 4); // tutup lagi bulan 4
        $periods->close(2026, 5);
        $this->assertSame(1, \App\Models\FiscalYear::where('name', 'Tahun Buku 2026')->count());
    }

    public function test_closed_period_locks_journals_and_reopening_unlocks_them(): void
    {
        $user = $this->makeUser('admin');
        $svc = app(JournalService::class);
        $periods = app(PeriodService::class);
        $kas = $this->makeAccount('1-1000', 'Kas', 'aset', 'aset_lancar');
        $pend = $this->makeAccount('4-1000', 'Pendapatan', 'pendapatan');

        // Ada draft di Januari -> tidak boleh ditutup
        $draft = $this->journal([[$kas, 10, 0], [$pend, 0, 10]], '2026-01-15');
        try {
            $periods->close(2026, 1);
            $this->fail('Periode dengan jurnal draft seharusnya tidak bisa ditutup.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('period', $e->errors());
        }

        $svc->post($draft, $user->id);
        $periods->close(2026, 1);
        $this->assertTrue($periods->isClosed('2026-01-31'));
        $this->assertFalse($periods->isClosed('2026-02-01'));

        // Januari terkunci, Februari masih bebas
        try {
            $this->journal([[$kas, 5, 0], [$pend, 0, 5]], '2026-01-20');
            $this->fail('Jurnal di periode tertutup seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('period', $e->errors());
        }
        $this->assertNotNull($this->journal([[$kas, 5, 0], [$pend, 0, 5]], '2026-02-02'));

        $periods->reopen(2026, 1, 'Koreksi');
        $this->assertFalse($periods->isClosed('2026-01-20'));
        $this->assertNotNull($this->journal([[$kas, 5, 0], [$pend, 0, 5]], '2026-01-20'));
    }
}
