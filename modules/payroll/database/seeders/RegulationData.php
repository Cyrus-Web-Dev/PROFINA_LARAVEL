<?php

namespace Database\Seeders;

/**
 * Data regulasi awal (statis, tanpa database) - dipakai RegulationSeeder dan unit test.
 * Sumber & verifikasi: lihat kolom "source". Semua nilai berlaku per tanggal (effective_from) dan
 * dapat ditambah versi barunya lewat menu Regulasi tanpa menimpa yang lama.
 */
class RegulationData
{
    public const TER_EFFECTIVE = '2024-01-01';

    /** @return array<int, array{0:string,1:mixed,2:string,3:string}> [code, value, effective_from, source] */
    public static function params(): array
    {
        $kes = 'UU 40/2004 jo. Perpres 64/2020 (iuran 5%: 4% perusahaan + 1% karyawan, batas upah Rp12 juta)';
        $tk = 'PP 44/2015, PP 45/2015, PP 46/2015 (BPJS Ketenagakerjaan)';
        $pph = 'UU 7/2021 (HPP) Pasal 17; PP 58/2023; PMK 168/2023';

        return [
            ['bpjs.kes.rate_employee', 1, '2020-01-01', $kes],
            ['bpjs.kes.rate_company', 4, '2020-01-01', $kes],
            ['bpjs.kes.wage_cap', 12000000, '2020-01-01', $kes],
            ['bpjs.jht.rate_employee', 2, '2015-07-01', $tk],
            ['bpjs.jht.rate_company', 3.7, '2015-07-01', $tk],
            ['bpjs.jkk.rate', [1 => 0.24, 2 => 0.54, 3 => 0.89, 4 => 1.27, 5 => 1.74], '2015-07-01', $tk.' - kelas risiko 1..5'],
            ['bpjs.jkm.rate', 0.3, '2015-07-01', $tk],
            ['bpjs.jp.rate_employee', 1, '2015-07-01', $tk],
            ['bpjs.jp.rate_company', 2, '2015-07-01', $tk],
            ['bpjs.jp.wage_cap', 10547400, '2025-03-01', 'SE BPJS Ketenagakerjaan batas upah JP 2025'],
            ['bpjs.jp.wage_cap', 11086300, '2026-03-01', 'SE BPJS Ketenagakerjaan No. B/1226/022026 (25 Feb 2026)'],

            ['tax.ptkp', ['TK/0' => 54000000, 'TK/1' => 58500000, 'TK/2' => 63000000, 'TK/3' => 67500000, 'K/0' => 58500000, 'K/1' => 63000000, 'K/2' => 67500000, 'K/3' => 72000000], '2016-01-01', 'PMK 101/PMK.010/2016'],
            ['tax.ter_category', ['TK/0' => 'A', 'TK/1' => 'A', 'K/0' => 'A', 'TK/2' => 'B', 'TK/3' => 'B', 'K/1' => 'B', 'K/2' => 'B', 'K/3' => 'C'], self::TER_EFFECTIVE, 'PMK 168/2023'],
            ['tax.biaya_jabatan', ['pct' => 5, 'cap_month' => 500000], '2009-01-01', 'PMK 250/PMK.03/2008 (5%, maks Rp500.000/bulan atau Rp6.000.000/tahun)'],
            ['tax.pasal17', [[60000000, 5], [250000000, 15], [500000000, 25], [5000000000, 30], [null, 35]], '2022-01-01', $pph],
            ['tax.npwp_surcharge_pct', 20, '2009-01-01', 'UU PPh Pasal 21 ayat (5): tarif lebih tinggi 20% bagi yang tidak ber-NPWP'],

            ['labor.overtime', ['divisor' => 173, 'weekday_first' => 1.5, 'weekday_next' => 2, 'rest_first8' => 2, 'rest_9th' => 3, 'rest_10th_plus' => 4], '2021-02-02', 'PP 35/2021; Kepmenakertrans 102/2004 (5 hari kerja)'],
            ['labor.thr', ['full_months' => 12, 'min_months' => 1, 'days_before_holiday' => 7], '2016-03-08', 'Permenaker 6/2016 (THR minimal H-7 hari raya)'],

            ['remit.due_days', ['pph21_pay' => 10, 'pph21_report' => 20, 'bpjs_kes' => 10, 'bpjs_tk' => 15], '2024-01-01', 'Batas setor PPh 21 tgl 10 / lapor tgl 20; BPJS Kes tgl 10; BPJS TK tgl 15 bulan berikutnya'],
        ];
    }

    /**
     * Tabel TER PP 58/2023 (lampiran PMK 168/2023). Tiap baris [batas_atas_inklusif|null, tarif_%].
     * Diverifikasi terhadap dua sumber independen (A 44 lapis, B 40 lapis, C 41 lapis).
     *
     * @return array<string, array<int, array{0:?int,1:float}>>
     */
    public static function terTables(): array
    {
        return [
            'A' => [[5400000, 0], [5650000, .25], [5950000, .5], [6300000, .75], [6750000, 1], [7500000, 1.25], [8550000, 1.5], [9650000, 1.75], [10050000, 2], [10350000, 2.25], [10700000, 2.5], [11050000, 3], [11600000, 3.5], [12500000, 4], [13750000, 5], [15100000, 6], [16950000, 7], [19750000, 8], [24150000, 9], [26450000, 10], [28000000, 11], [30050000, 12], [32400000, 13], [35400000, 14], [39100000, 15], [43850000, 16], [47800000, 17], [51400000, 18], [56300000, 19], [62200000, 20], [68600000, 21], [77500000, 22], [89000000, 23], [103000000, 24], [125000000, 25], [157000000, 26], [206000000, 27], [337000000, 28], [454000000, 29], [550000000, 30], [695000000, 31], [910000000, 32], [1400000000, 33], [null, 34]],
            'B' => [[6200000, 0], [6500000, .25], [6850000, .5], [7300000, .75], [9200000, 1], [10750000, 1.5], [11250000, 2], [11600000, 2.5], [12600000, 3], [13600000, 4], [14950000, 5], [16400000, 6], [18450000, 7], [21850000, 8], [26000000, 9], [27700000, 10], [29350000, 11], [31450000, 12], [33950000, 13], [37100000, 14], [41100000, 15], [45800000, 16], [49500000, 17], [53800000, 18], [58500000, 19], [64000000, 20], [71000000, 21], [80000000, 22], [93000000, 23], [109000000, 24], [129000000, 25], [163000000, 26], [211000000, 27], [374000000, 28], [459000000, 29], [555000000, 30], [704000000, 31], [957000000, 32], [1405000000, 33], [null, 34]],
            'C' => [[6600000, 0], [6950000, .25], [7350000, .5], [7800000, .75], [8850000, 1], [9800000, 1.25], [10950000, 1.5], [11200000, 1.75], [12050000, 2], [12950000, 3], [14150000, 4], [15550000, 5], [17050000, 6], [19500000, 7], [22700000, 8], [26600000, 9], [28100000, 10], [30100000, 11], [32600000, 12], [35400000, 13], [38900000, 14], [43000000, 15], [47400000, 16], [51200000, 17], [55800000, 18], [60400000, 19], [66700000, 20], [74500000, 21], [83200000, 22], [95600000, 23], [110000000, 24], [134000000, 25], [169000000, 26], [221000000, 27], [390000000, 28], [463000000, 29], [561000000, 30], [709000000, 31], [965000000, 32], [1419000000, 33], [null, 34]],
        ];
    }
}
