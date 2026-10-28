<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PeriodService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Penutupan periode akuntansi (khusus Manager; dijaga middleware role:admin di routes/web.php). */
class PeriodController extends Controller
{
    public function index(Request $request, PeriodService $periods)
    {
        $year = max(2000, min(2100, (int) $request->query('year', now()->year)));

        return Inertia::render('Admin/Periods', [
            'year' => $year,
            'months' => $periods->overview($year),
        ]);
    }

    public function close(Request $request, PeriodService $periods)
    {
        $data = $request->validate(['year' => 'required|integer|between:2000,2100', 'month' => 'required|integer|between:1,12']);

        $periods->close((int) $data['year'], (int) $data['month']);

        return back()->with('success', 'Periode '.PeriodService::MONTHS[(int) $data['month']].' '.$data['year'].' ditutup. Jurnal di bulan itu kini terkunci.');
    }

    public function reopen(Request $request, PeriodService $periods)
    {
        $data = $request->validate([
            'year' => 'required|integer|between:2000,2100',
            'month' => 'required|integer|between:1,12',
            'reason' => 'required|string|max:300',
        ], ['reason.required' => 'Tulis alasan membuka kembali periode (tercatat di Activity Log).']);

        $periods->reopen((int) $data['year'], (int) $data['month'], $data['reason']);

        return back()->with('success', 'Periode '.PeriodService::MONTHS[(int) $data['month']].' '.$data['year'].' dibuka kembali.');
    }
}
