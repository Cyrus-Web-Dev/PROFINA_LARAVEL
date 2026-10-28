<?php

namespace App\Services;

use App\Models\PayrollFinding;
use App\Models\PayrollInput;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use Illuminate\Support\Collection;

/**
 * Mesin deteksi anomali / indikasi kecurangan. Dijalankan setiap putaran dihitung. Temuan "blocking" harus
 * dijelaskan (resolved/waived dengan catatan) oleh orang berwenang sebelum putaran boleh diverifikasi/disetujui.
 */
class FindingsEngine
{
    /** @var array<string,PayrollFinding> */
    private array $found = [];

    /**
     * @param  array{no_contract?:array,no_attendance?:array,tampered?:array,sdm_configured?:bool}  $extra
     */
    public function scan(PayrollRun $run, array $extra = [], ?array $budgetCheck = null): void
    {
        $this->found = [];
        $items = $run->items()->with(['employee', 'contract.grade'])->get();
        $window = (int) config('pay.controls.bank_change_window_days', 30);

        foreach ($extra['no_contract'] ?? [] as $emp) {
            $this->add($run, 'NO_CONTRACT', 'critical', true, "{$emp->full_name} tidak punya kontrak kompensasi aktif", 'Karyawan aktif di SDM tetapi tidak ada kontrak yang disetujui & berlaku pada periode ini, sehingga TIDAK dibayar. Usulkan/setujui kontrak, lalu hitung ulang.', $emp->id, ['nip' => $emp->nip]);
        }
        foreach ($extra['tampered'] ?? [] as $emp) {
            $this->add($run, 'CONTRACT_TAMPER', 'critical', true, "Isi kontrak {$emp->full_name} tidak cocok dengan hash", 'Angka/komponen kontrak berubah di luar alur persetujuan. Karyawan dikeluarkan dari putaran. Laporkan ke Auditor.', $emp->id, ['nip' => $emp->nip]);
        }
        foreach ($extra['no_attendance'] ?? [] as $emp) {
            $sev = ($extra['sdm_configured'] ?? false) ? 'critical' : 'warning';
            $this->add($run, 'NO_ATTENDANCE_DATA', $sev, $sev === 'critical', "Data absensi {$emp->full_name} belum ditarik dari SDM", 'Tanpa absensi, potongan alpha/cuti tidak berbayar tidak bisa dihitung (risiko kelebihan bayar). Tarik data SDM lalu hitung ulang.', $emp->id, ['nip' => $emp->nip]);
        }

        $byBank = $items->filter(fn ($i) => $i->bank_account_hash)->groupBy('bank_account_hash');

        foreach ($items as $i) {
            $e = $i->employee;
            $nm = $e->full_name;
            $snap = $i->employee_snapshot;

            if (! $i->bank_account_hash) {
                $this->add($run, 'NO_BANK', 'critical', true, "$nm belum punya rekening bank", 'Tanpa rekening, gaji tidak bisa ditransfer. Lengkapi di SDM atau tahan item ini.', $e->id);
            }
            if ($i->bank_account_hash && $byBank[$i->bank_account_hash]->count() > 1) {
                $names = $byBank[$i->bank_account_hash]->map(fn ($x) => $x->employee->full_name)->implode(', ');
                $this->add($run, 'DUP_BANK', 'critical', true, "Rekening $nm dipakai lebih dari satu karyawan", "Rekening yang sama muncul pada: {$names}. Pola klasik karyawan fiktif/ganda.", $e->id, ['group' => $names]);
            }
            if ($e->bank_changed_at && $e->bank_changed_at->gte($run->pay_date->copy()->subDays($window))) {
                $this->add($run, 'BANK_RECENT', 'critical', true, "Rekening $nm baru diubah (".$e->bank_changed_at->format('d/m/Y').')', "Rekening berubah dalam {$window} hari terakhir. Verifikasi langsung ke karyawan (bukan lewat orang yang mengubahnya), lalu catat hasilnya.", $e->id);
            }

            $att = \App\Models\AttendanceSnapshot::where('employee_id', $e->id)->where('year', $run->period_year)->where('month', $run->period_month)->first();
            if ($run->type === 'regular' && $att && $att->scheduled_days > 0 && ($att->present_days + $att->paid_leave_days + $att->sick_days + $att->wfh_days + $att->permit_days) <= 0 && $i->basic_salary > 0) {
                $this->add($run, 'GHOST_NO_ACTIVITY', 'critical', true, "$nm digaji tanpa satu pun kehadiran/cuti sebulan penuh", 'Absensi SDM kosong untuk seluruh hari kerja, namun gaji pokok tetap dibayar. Indikasi karyawan fiktif atau data absensi belum lengkap.', $e->id);
            }

            if ($i->net_pay <= 0 && $i->gross_earnings > 0) {
                $this->add($run, 'NET_NOT_POSITIVE', 'critical', true, "Gaji bersih $nm nol/negatif", 'Potongan melebihi penghasilan. Periksa kasbon, potongan, dan PPh 21.', $e->id, ['net' => $i->net_pay]);
            }

            if ($i->contract && $e->sdm_base_salary > 0 && abs($e->sdm_base_salary - (float) $i->contract->base_salary) > 1) {
                $this->add($run, 'SDM_DRIFT', 'warning', false, "Gaji di SDM berbeda dari kontrak aktif $nm", sprintf('SDM: Rp %s, kontrak aktif: Rp %s. Yang dibayar adalah kontrak. Pastikan perubahan di SDM sudah diusulkan sebagai kontrak baru.', number_format($e->sdm_base_salary, 0, ',', '.'), number_format($i->contract->base_salary, 0, ',', '.')), $e->id);
            }

            if ($run->type === 'regular') {
                $prev = PayrollItem::where('employee_id', $e->id)->whereHas('run', fn ($q) => $q->where('type', 'regular')->whereNotNull('locked_at')->whereNotIn('status', ['cancelled', 'rejected'])->where('id', '!=', $run->id))
                    ->orderByDesc('id')->first();
                if ($prev && $prev->gross_earnings > 0) {
                    $pct = ($i->gross_earnings - $prev->gross_earnings) / $prev->gross_earnings * 100;
                    if (abs($pct) >= (float) config('pay.controls.salary_jump_pct', 25)) {
                        $sameContract = $prev->contract_id === $i->contract_id;
                        $this->add($run, 'SALARY_JUMP', $sameContract ? 'warning' : 'info', false, sprintf('Penghasilan kotor %s berubah %+.1f%% dari bulan lalu', $nm, $pct),
                            $sameContract ? 'Kontrak tidak berubah, tetapi nominal berubah banyak. Periksa input variabel/absensi.' : 'Ada perubahan kontrak sehingga selisih wajar; pastikan sesuai kontrak baru.', $e->id, ['pct' => round($pct, 1)]);
                    }
                }
                if ($i->proration_factor < 1) {
                    $this->add($run, 'PRORATED', 'info', false, "$nm dibayar prorata (".round($i->proration_factor * 100).'%)', 'Masuk/keluar/kontrak mulai di tengah bulan.', $e->id);
                }
            }

            if (! $e->has_npwp && $i->pph21 > 0) {
                $this->add($run, 'NO_NPWP', 'info', false, "$nm tidak ber-NPWP: tarif PPh 21 +20%", 'Sesuai UU PPh Pasal 21 ayat (5).', $e->id);
            }
            if ($i->contract?->grade && $i->contract->grade->requires_dual_approval) {
                $this->add($run, 'EXEC_INCLUDED', 'info', false, "Golongan eksekutif/direksi disertakan ($nm)", 'Putaran ini wajib dua penyetuju.', $e->id);
            }
            if ($i->pph21_method === 'final') {
                $this->add($run, 'FINAL_PERIOD', 'info', false, "$nm: masa pajak terakhir (tarif Pasal 17)", sprintf('PPh 21 setahun dihitung ulang; selisih Rp %s.', number_format($i->pph21, 0, ',', '.')), $e->id);
            }
        }

        // Input variabel yang dipakai putaran ini
        $inputs = PayrollInput::where('applied_run_id', $run->id)->with('employee')->get();
        $large = (float) config('pay.controls.large_input_amount');
        $unit = (float) config('pay.controls.round_input_unit');
        foreach ($inputs as $in) {
            $nm = $in->employee->full_name;
            if ($in->type !== 'overtime' && $in->amount >= $large) {
                $round = $unit > 0 && fmod($in->amount, $unit) == 0.0;
                $this->add($run, $round ? 'INPUT_ROUND' : 'INPUT_LARGE', 'warning', false, ($round ? 'Nominal bulat besar: ' : 'Nominal besar: ').PayrollInput::TYPE_LABELS[$in->type]." {$nm} Rp ".number_format($in->amount, 0, ',', '.'), 'Pastikan ada dasar/bukti. Nominal bulat besar adalah pola umum pengisian fiktif.', $in->employee_id, ['input_id' => $in->id]);
            }
            if ($in->requested_by && $in->requested_by === $in->approved_by) {
                $this->add($run, 'INPUT_SELF_APPROVED', 'critical', true, "Input {$nm} disetujui oleh pengajunya sendiri", 'Pelanggaran pemisahan tugas.', $in->employee_id, ['input_id' => $in->id]);
            }
            if ($in->type === 'overtime') {
                $chk = $in->attendance_check ?? [];
                if (($chk['ok'] ?? true) === false) {
                    $this->add($run, 'OT_NOT_ATTENDED', 'critical', true, "Lembur {$nm} ".$in->work_date?->format('d/m').' tidak didukung absensi', $chk['message'] ?? 'Jam lembur melebihi jam pulang tercatat di SDM.', $in->employee_id, ['input_id' => $in->id]);
                }
            }
        }
        foreach ($inputs->where('type', 'overtime')->groupBy('employee_id') as $empId => $list) {
            $nm = $list->first()->employee->full_name;
            $perDay = $list->groupBy(fn ($x) => $x->work_date?->toDateString())->map->sum('hours');
            if ($perDay->max() > (float) config('pay.controls.overtime_max_hours_day', 4)) {
                $this->add($run, 'OT_DAY_LIMIT', 'warning', false, "Lembur {$nm} melebihi batas harian", 'Batas PP 35/2021: 4 jam/hari.', $empId);
            }
            $perWeek = $list->groupBy(fn ($x) => $x->work_date?->format('o-W'))->map->sum('hours');
            if ($perWeek->max() > (float) config('pay.controls.overtime_max_hours_week', 18)) {
                $this->add($run, 'OT_WEEK_LIMIT', 'warning', false, "Lembur {$nm} melebihi batas mingguan", 'Batas PP 35/2021: 18 jam/minggu.', $empId);
            }
        }

        if ($budgetCheck) {
            if (! $budgetCheck['has_plan']) {
                $this->add($run, 'NO_BUDGET', 'warning', false, 'Belum ada anggaran SDM aktif untuk tahun '.$run->period_year, 'Pengendalian anggaran tidak berjalan. Buat & aktifkan anggaran di menu Anggaran.', null);
            }
            foreach ($budgetCheck['rows'] ?? [] as $row) {
                if ($row['over'] > 0.5) {
                    $this->add($run, 'BUDGET_EXCEEDED', 'critical', true, "Anggaran {$row['name']} terlampaui Rp ".number_format($row['over'], 0, ',', '.'),
                        sprintf('Anggaran bulan ini Rp %s, realisasi termasuk putaran ini Rp %s.', number_format($row['budget'], 0, ',', '.'), number_format($row['actual'], 0, ',', '.')), null, ['department_id' => $row['department_id']]);
                }
            }
        }

        $earlier = PayrollRun::where('period_year', $run->period_year)->where('period_month', $run->period_month)->where('id', '!=', $run->id)
            ->whereNull('locked_at')->whereNotIn('status', ['cancelled', 'rejected'])->count();
        if ($earlier) {
            $this->add($run, 'PEER_RUN_OPEN', 'warning', false, 'Ada putaran lain bulan ini yang belum disetujui', 'Pajak bulan berjalan dihitung dari putaran terkunci saja. Setujui putaran sebelumnya dulu agar TER akurat.', null);
        }

        $this->persist($run);
    }

    private function add(PayrollRun $run, string $code, string $sev, bool $blocking, string $title, ?string $detail, ?int $employeeId, array $ctx = []): void
    {
        $fp = sha1($code.'|'.($employeeId ?? '').'|'.($ctx['input_id'] ?? '').'|'.($ctx['department_id'] ?? ''));
        $this->found[$fp] = new PayrollFinding([
            'payroll_run_id' => $run->id, 'employee_id' => $employeeId, 'code' => $code, 'severity' => $sev, 'is_blocking' => $blocking,
            'title' => mb_substr($title, 0, 200), 'detail' => $detail, 'context' => $ctx ?: null, 'fingerprint' => $fp,
        ]);
    }

    private function persist(PayrollRun $run): void
    {
        $existing = PayrollFinding::where('payroll_run_id', $run->id)->get()->keyBy('fingerprint');

        foreach ($existing as $fp => $f) {
            if (! isset($this->found[$fp]) && $f->status === 'open') {
                $f->delete();
            }
        }
        foreach ($this->found as $fp => $new) {
            if ($old = $existing->get($fp)) {
                $old->update(['severity' => $new->severity, 'is_blocking' => $new->is_blocking, 'title' => $new->title, 'detail' => $new->detail, 'context' => $new->context]);
            } else {
                $new->save();
            }
        }
    }

    public static function blockingOpen(PayrollRun $run): Collection
    {
        return PayrollFinding::where('payroll_run_id', $run->id)->where('is_blocking', true)->where('status', 'open')->get();
    }
}
