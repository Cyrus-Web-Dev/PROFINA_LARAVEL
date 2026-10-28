<?php

namespace App\Http\Controllers;

use App\Models\CompanyHoliday;
use App\Services\ActivityLogger;
use App\Services\HolidayService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class HolidayController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:holidays.view')->only(['index']);
        $this->middleware('permission:holidays.manage')->except(['index']);
    }

    public function index(Request $request, HolidayService $holidays)
    {
        $year = min(2100, max(2020, (int) $request->input('year', today()->year)));

        return Inertia::render('Holidays/Index', [
            'year' => $year,
            'national' => collect($holidays->forYear($year))->map(fn ($name, $date) => ['date' => $date, 'name' => $name])->values(),
            'company' => CompanyHoliday::whereYear('date', $year)->orderBy('date')->get()->map(fn ($h) => [
                'id' => $h->id, 'date' => $h->date->toDateString(), 'name' => $h->name, 'type' => $h->type,
            ]),
            'sourceStatus' => $holidays->sourceStatus(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date', Rule::unique('company_holidays', 'date')],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['holiday', 'working_day'])],
        ], ['date.unique' => 'Tanggal ini sudah ada di daftar libur perusahaan.']);

        $holiday = CompanyHoliday::create($data + ['created_by' => auth()->id()]);
        ActivityLogger::log('holiday.created', "Menambah {$holiday->type} perusahaan: {$holiday->name} ({$holiday->date->format('d/m/Y')})", $holiday, auth()->id());

        return back()->with('success', 'Tanggal berhasil ditambahkan.');
    }

    public function destroy(CompanyHoliday $holiday)
    {
        ActivityLogger::log('holiday.deleted', "Menghapus libur perusahaan: {$holiday->name}", null, auth()->id());
        $holiday->delete();

        return back()->with('success', 'Tanggal dihapus.');
    }

    /** Buang cache dan ambil ulang data libur nasional dari API. */
    public function refresh(HolidayService $holidays)
    {
        $holidays->refresh();

        return back()->with('success', 'Data libur nasional diperbarui. Sumber aktif: '.$holidays->sourceStatus().'.');
    }
}
