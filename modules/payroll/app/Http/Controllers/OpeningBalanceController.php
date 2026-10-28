<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\OpeningBalance;
use App\Services\AuditLedger;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Saldo awal tahun berjalan (go-live di tengah tahun): agar PPh 21 masa pajak terakhir akurat. */
class OpeningBalanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:opening.manage');
    }

    public function index(Request $request)
    {
        $year = (int) ($request->year ?: now()->year);

        return Inertia::render('Opening/Index', [
            'year' => $year,
            'rows' => OpeningBalance::with('employee:id,nip,full_name')->where('year', $year)->orderBy('id')->get()->map(fn ($o) => ['nip' => $o->employee->nip, 'name' => $o->employee->full_name, 'months' => $o->months_counted, 'gross' => $o->gross_ytd, 'pension' => $o->pension_ytd, 'tax' => $o->pph21_ytd]),
        ]);
    }

    /** CSV tempel: nip,bulan_terhitung,bruto_ytd,iuran_pensiun_ytd,pph21_ytd */
    public function store(Request $request)
    {
        $d = $request->validate(['year' => 'required|integer|between:2024,2100', 'csv' => 'required|string|max:200000']);
        $ok = 0;
        $errors = [];
        foreach (preg_split('/\r\n|\n|\r/', trim($d['csv'])) as $n => $line) {
            if (trim($line) === '' || stripos($line, 'nip') === 0) {
                continue;
            }
            $c = array_map('trim', str_getcsv($line));
            $emp = Employee::where('nip', $c[0] ?? '')->first();
            if (! $emp || count($c) < 5) {
                $errors[] = 'Baris '.($n + 1).': NIP tidak ditemukan atau kolom kurang.';
                continue;
            }
            OpeningBalance::updateOrCreate(['employee_id' => $emp->id, 'year' => $d['year']], ['months_counted' => (int) $c[1], 'gross_ytd' => (float) $c[2], 'pension_ytd' => (float) $c[3], 'pph21_ytd' => (float) $c[4],
                'source_note' => 'Impor manual', 'created_by' => $request->user()->id]);
            $ok++;
        }
        AuditLedger::record('opening.imported', null, ['year' => $d['year'], 'rows' => $ok, 'errors' => count($errors)], 'Saldo awal '.$d['year']);

        return back()->with($errors ? 'error' : 'success', "{$ok} baris tersimpan.".($errors ? ' '.implode(' ', array_slice($errors, 0, 3)) : ''));
    }
}
