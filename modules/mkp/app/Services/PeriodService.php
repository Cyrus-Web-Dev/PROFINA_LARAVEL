<?php

namespace App\Services;

use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\Journal;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penutupan periode akuntansi (per bulan). Periode yang sudah ditutup mengunci jurnal:
 * tidak bisa dibuat, diubah, diposting, atau di-void dengan tanggal di bulan tersebut.
 * Ini yang menjaga laporan yang sudah diserahkan tidak berubah diam-diam.
 *
 * PENTING: start_date/end_date/journal_date disimpan lewat cast Eloquent 'date', yang secara
 * fisik menulis "YYYY-MM-DD 00:00:00" (dengan jam) ke database, bukan "YYYY-MM-DD" polos.
 * MySQL/PostgreSQL biasanya memotong jam itu sendiri karena tipe kolomnya DATE, tapi SQLite
 * (dipakai oleh test) menyimpan apa adanya. Karena itu SEMUA perbandingan tanggal di file ini
 * memakai whereDate() (menerapkan fungsi SQL DATE()), bukan where()/whereBetween() biasa -
 * whereDate() aman di ketiga database itu. Jangan ganti balik ke where() biasa.
 */
class PeriodService
{
    public const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function isClosed(string|DateTimeInterface|null $date): bool
    {
        $d = Carbon::parse($date ?? now())->toDateString();

        return FiscalPeriod::where('status', 'closed')
            ->whereDate('start_date', '<=', $d)
            ->whereDate('end_date', '>=', $d)
            ->exists();
    }

    public function assertOpen(string|DateTimeInterface|null $date): void
    {
        if ($this->isClosed($date)) {
            $c = Carbon::parse($date ?? now());

            throw ValidationException::withMessages([
                'period' => 'Periode '.self::MONTHS[$c->month].' '.$c->year.' sudah ditutup, sehingga transaksi bertanggal '
                    .$c->format('d/m/Y').' tidak bisa dicatat atau diubah. Gunakan tanggal di periode yang masih terbuka, '
                    .'atau minta Manager membuka kembali periodenya.',
            ]);
        }
    }

    /** Ringkasan 12 bulan pada satu tahun: status + jumlah jurnal posted / draft. */
    public function overview(int $year): array
    {
        $periods = FiscalPeriod::whereDate('start_date', '>=', "{$year}-01-01")
            ->whereDate('start_date', '<=', "{$year}-12-31")
            ->get()
            ->keyBy(fn ($p) => (int) Carbon::parse($p->start_date)->month);

        $counts = [];
        $rows = DB::table('journals')
            ->whereDate('journal_date', '>=', "{$year}-01-01")
            ->whereDate('journal_date', '<=', "{$year}-12-31")
            ->whereIn('status', ['draft', 'posted'])
            ->selectRaw('journal_date, status, count(*) as total')
            ->groupBy('journal_date', 'status')
            ->get();
        foreach ($rows as $r) {
            // journal_date yang terbaca lewat query mentah ini bisa berupa "YYYY-MM-DD" atau
            // "YYYY-MM-DD HH:MM:SS" tergantung driver - keduanya sama-sama diawali "YYYY-MM".
            $m = (int) substr((string) $r->journal_date, 5, 2);
            $counts[$m][$r->status] = ($counts[$m][$r->status] ?? 0) + (int) $r->total;
        }

        $out = [];
        foreach (self::MONTHS as $m => $name) {
            $out[] = [
                'month' => $m,
                'name' => $name,
                'status' => ($periods[$m]->status ?? 'open'),
                'posted' => $counts[$m]['posted'] ?? 0,
                'drafts' => $counts[$m]['draft'] ?? 0,
            ];
        }

        return $out;
    }

    public function close(int $year, int $month): FiscalPeriod
    {
        [$start, $end] = $this->bounds($year, $month);

        $drafts = Journal::where('status', 'draft')
            ->whereDate('journal_date', '>=', $start)
            ->whereDate('journal_date', '<=', $end)
            ->count();
        if ($drafts > 0) {
            throw ValidationException::withMessages([
                'period' => "Masih ada {$drafts} jurnal draft / menunggu approval pada ".self::MONTHS[$month]." {$year}. Posting atau hapus dulu sebelum menutup periode.",
            ]);
        }

        return DB::transaction(function () use ($year, $month, $start, $end) {
            $fiscalYear = $this->findOrCreateFiscalYear($year);

            $period = FiscalPeriod::firstOrNew(['fiscal_year_id' => $fiscalYear->id, 'month' => $month]);
            $period->fill(['start_date' => $start, 'end_date' => $end, 'status' => 'closed'])->save();

            ActivityLogger::log('period.closed', 'Menutup periode '.self::MONTHS[$month]." {$year}");

            return $period;
        });
    }

    public function reopen(int $year, int $month, string $reason): void
    {
        [$start] = $this->bounds($year, $month);

        $period = FiscalPeriod::where('status', 'closed')->whereDate('start_date', $start)->first();
        if (! $period) {
            throw ValidationException::withMessages(['period' => 'Periode ini memang belum ditutup.']);
        }

        $period->update(['status' => 'open']);

        ActivityLogger::log('period.reopened', 'Membuka kembali periode '.self::MONTHS[$month]." {$year}. Alasan: {$reason}");
    }

    /**
     * Cari FiscalYear tahun tsb, atau buat baru. TIDAK memakai FiscalYear::firstOrCreate() dengan
     * array tanggal, karena pencocokannya berupa where() persis (bukan whereDate()) dan akan
     * membuat baris duplikat setiap kali dipanggil - lihat catatan di docblock kelas ini.
     */
    private function findOrCreateFiscalYear(int $year): FiscalYear
    {
        $fiscalYear = FiscalYear::whereDate('start_date', "{$year}-01-01")->first();

        return $fiscalYear ?? FiscalYear::create([
            'name' => "Tahun Buku {$year}",
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
            'status' => 'open',
        ]);
    }

    /** @return array{0: string, 1: string} */
    private function bounds(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1);

        return [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()];
    }
}
