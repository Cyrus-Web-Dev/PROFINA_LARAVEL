<?php

namespace App\Services;

use App\Models\CompanyHoliday;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kalender libur: (1) libur nasional + cuti bersama dari dataset/API publik, (2) libur tambahan perusahaan
 * dan "hari kerja pengganti" dari tabel company_holidays (bisa diatur Admin HR).
 *
 * Ketahanan: hasil API di-cache (config hr.holidays.cache_hours). Bila API gagal dipakai salinan cache terakhir
 * yang berhasil ("stale"), dan bila itu pun tidak ada dipakai file cadangan database/data/holidays-id.json.
 * Jadi API luar mati TIDAK PERNAH membuat fitur cuti error.
 */
class HolidayService
{
    private const CACHE_KEY = 'holidays.national';
    private const STALE_KEY = 'holidays.national.stale';

    /** @return array<string,string> tanggal (Y-m-d) => nama, semua tahun yang tersedia */
    public function national(): array
    {
        if (! config('hr.holidays.enabled')) {
            return $this->fallback();
        }

        return Cache::remember(self::CACHE_KEY, now()->addHours((int) config('hr.holidays.cache_hours')), function () {
            try {
                $response = Http::timeout(8)->connectTimeout(4)->acceptJson()->get(config('hr.holidays.source_url'));
                if ($response->successful()) {
                    $parsed = $this->parse($response->json());
                    if ($parsed) {
                        Cache::forever(self::STALE_KEY, $parsed);

                        return $parsed;
                    }
                }
                Log::warning('[Holiday] Respons API tidak valid/kosong, memakai cadangan.');
            } catch (Throwable $e) {
                Log::warning('[Holiday] Gagal mengambil API hari libur: '.$e->getMessage());
            }

            return Cache::get(self::STALE_KEY) ?: $this->fallback();
        });
    }

    /** Sumber data yang sedang aktif, untuk ditampilkan di halaman admin. */
    public function sourceStatus(): string
    {
        if (! config('hr.holidays.enabled')) {
            return 'Dinonaktifkan (memakai file cadangan bawaan)';
        }

        return Cache::has(self::STALE_KEY) ? 'API publik (dengan cache)' : 'File cadangan bawaan (API belum berhasil diambil)';
    }

    public function refresh(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->national();
    }

    /** @return array<string,string> hari libur efektif (nasional + perusahaan - hari kerja pengganti) antara dua tanggal. */
    public function between(CarbonInterface $start, CarbonInterface $end): array
    {
        $result = $this->national();
        $result = array_filter($result, fn ($name, $date) => $date >= $start->toDateString() && $date <= $end->toDateString(), ARRAY_FILTER_USE_BOTH);

        $company = CompanyHoliday::whereBetween('date', [$start->toDateString(), $end->toDateString()])->get();
        foreach ($company as $row) {
            $key = $row->date->toDateString();
            if ($row->type === 'working_day') {
                unset($result[$key]);
            } else {
                $result[$key] = $row->name;
            }
        }

        ksort($result);

        return $result;
    }

    public function isWeekend(CarbonInterface $date): bool
    {
        // Carbon: 0 = Minggu ... 6 = Sabtu
        $weekend = (int) config('hr.work_days_per_week') >= 6 ? [0] : [0, 6];

        return in_array($date->dayOfWeek, $weekend, true);
    }

    /** Jumlah hari KERJA (bukan akhir pekan & bukan libur) dari $start s/d $end (inklusif). */
    public function workingDays(CarbonInterface $start, CarbonInterface $end): int
    {
        $holidays = $this->between($start, $end);
        $count = 0;

        for ($d = Carbon::parse($start->toDateString()); $d->lte($end); $d->addDay()) {
            if (! $this->isWeekend($d) && ! isset($holidays[$d->toDateString()])) {
                $count++;
            }
        }

        return $count;
    }

    /** @return array<int, array{date:string,name:string}> libur mendatang (mulai hari ini). */
    public function upcoming(int $limit = 5): array
    {
        $list = $this->between(today(), today()->addMonths(6));

        return collect($list)->map(fn ($name, $date) => ['date' => $date, 'name' => $name])->values()->take($limit)->all();
    }

    /** @return array<string,string> libur nasional untuk satu tahun (untuk halaman admin). */
    public function forYear(int $year): array
    {
        return array_filter($this->national(), fn ($name, $date) => str_starts_with($date, (string) $year), ARRAY_FILTER_USE_BOTH);
    }

    private function parse(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        $out = [];
        foreach ($json as $date => $info) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date) || ! is_array($info) || empty($info['holiday'])) {
                continue; // lewati kunci "info" & tanggal perayaan non-libur (mis. 1 Ramadan)
            }
            $out[$date] = trim(implode(', ', (array) ($info['summary'] ?? ['Hari libur']))) ?: 'Hari libur';
        }

        return $out;
    }

    private function fallback(): array
    {
        $path = database_path('data/holidays-id.json');
        if (! File::exists($path)) {
            return [];
        }

        $data = json_decode(File::get($path), true);

        return is_array($data['holidays'] ?? null) ? $data['holidays'] : [];
    }
}
