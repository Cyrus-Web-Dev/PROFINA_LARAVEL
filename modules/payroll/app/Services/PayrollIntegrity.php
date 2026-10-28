<?php

namespace App\Services;

use App\Models\PayrollRun;

/** Verifikasi independen hasil payroll: menghitung ulang identitas akuntansi dari data tersimpan & mencocokkan hash. */
class PayrollIntegrity
{
    /** @return array<int,string> daftar pelanggaran (kosong = utuh) */
    public static function verify(PayrollRun $run): array
    {
        $errors = [];
        $sum = ['gross' => 0.0, 'net' => 0.0, 'pph' => 0.0];

        foreach ($run->items()->with('contract.components')->get() as $i) {
            $nm = $i->employee_snapshot['nip'] ?? $i->employee_id;
            $lines = collect($i->lines);
            $gross = (float) $lines->where('type', 'earning')->sum('amount');
            $ded = (float) $lines->where('type', 'deduction')->sum('amount');
            $bpjsEe = $i->bpjs_kes_employee + $i->bpjs_jht_employee + $i->bpjs_jp_employee;
            $bpjsEr = $i->bpjs_kes_company + $i->bpjs_jht_company + $i->bpjs_jkk_company + $i->bpjs_jkm_company + $i->bpjs_jp_company;

            if (abs($gross - $i->gross_earnings) > 0.01) {
                $errors[] = "[$nm] Penghasilan kotor tidak sama dengan jumlah baris komponen.";
            }
            if (abs(($gross - $bpjsEe - $i->pph21 - $ded) - $i->net_pay) > 0.01) {
                $errors[] = "[$nm] Gaji bersih tidak sama dengan kotor - BPJS - PPh 21 - potongan.";
            }
            if (abs(($gross + $bpjsEr) - $i->employer_cost) > 0.01) {
                $errors[] = "[$nm] Biaya perusahaan tidak cocok.";
            }
            if (! hash_equals($i->item_hash, $i->computeHash())) {
                $errors[] = "[$nm] Hash item tidak cocok (isi item berubah setelah dihitung).";
            }
            if ($i->contract && ! $i->contract->intact()) {
                $errors[] = "[$nm] Isi kontrak {$i->contract->contract_number} tidak cocok dengan hash-nya.";
            }
            $sum['gross'] += $i->gross_earnings;
            $sum['net'] += $i->net_pay;
            $sum['pph'] += $i->pph21;
        }

        if (abs($sum['gross'] - $run->total_gross) > 0.01 || abs($sum['net'] - $run->total_net) > 0.01 || abs($sum['pph'] - $run->total_pph21) > 0.01) {
            $errors[] = 'Total putaran tidak sama dengan jumlah item.';
        }

        return $errors;
    }

    /** Hash gabungan seluruh item (disimpan saat dikunci). */
    public static function snapshotHash(PayrollRun $run): string
    {
        $hashes = $run->items()->orderBy('employee_id')->pluck('item_hash')->all();

        return hash('sha256', $run->run_number.'|'.implode(',', $hashes));
    }
}
