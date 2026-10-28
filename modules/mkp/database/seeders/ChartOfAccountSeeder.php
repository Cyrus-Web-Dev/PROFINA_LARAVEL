<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // ASET
            ['1-1000', 'ASET LANCAR', 'aset', 'aset_lancar', 'debit', true],
            ['1-1100', 'Kas', 'aset', 'aset_lancar', 'debit', false],
            ['1-1200', 'Bank', 'aset', 'aset_lancar', 'debit', false],
            ['1-1300', 'Piutang Usaha', 'aset', 'aset_lancar', 'debit', false],
            ['1-1400', 'Persediaan Barang Dagang', 'aset', 'aset_lancar', 'debit', false],
            ['1-1500', 'Perlengkapan', 'aset', 'aset_lancar', 'debit', false],
            ['1-1600', 'Beban Dibayar Dimuka', 'aset', 'aset_lancar', 'debit', false],
            ['1-1700', 'Uang Muka Pengadaan', 'aset', 'aset_lancar', 'debit', false],
            ['1-2000', 'ASET TETAP', 'aset', 'aset_tetap', 'debit', true],
            ['1-2100', 'Tanah', 'aset', 'aset_tetap', 'debit', false],
            ['1-2200', 'Bangunan', 'aset', 'aset_tetap', 'debit', false],
            ['1-2300', 'Kendaraan', 'aset', 'aset_tetap', 'debit', false],
            ['1-2400', 'Peralatan Kantor', 'aset', 'aset_tetap', 'debit', false],
            ['1-2900', 'Akumulasi Depresiasi Aset Tetap', 'aset', 'aset_tetap', 'kredit', false],

            // KEWAJIBAN
            ['2-1000', 'KEWAJIBAN JANGKA PENDEK', 'kewajiban', 'kewajiban_lancar', 'kredit', true],
            ['2-1100', 'Utang Usaha', 'kewajiban', 'kewajiban_lancar', 'kredit', false],
            ['2-1200', 'Utang Gaji', 'kewajiban', 'kewajiban_lancar', 'kredit', false],
            ['2-1300', 'Utang Pajak', 'kewajiban', 'kewajiban_lancar', 'kredit', false],
            ['2-1400', 'Pendapatan Diterima Dimuka', 'kewajiban', 'kewajiban_lancar', 'kredit', false],
            ['2-2000', 'KEWAJIBAN JANGKA PANJANG', 'kewajiban', 'kewajiban_panjang', 'kredit', true],
            ['2-2100', 'Utang Bank Jangka Panjang', 'kewajiban', 'kewajiban_panjang', 'kredit', false],

            // EKUITAS
            ['3-1000', 'EKUITAS', 'ekuitas', null, 'kredit', true],
            ['3-1100', 'Modal Saham', 'ekuitas', null, 'kredit', false],
            ['3-1200', 'Laba Ditahan', 'ekuitas', null, 'kredit', false],
            ['3-1300', 'Prive / Dividen', 'ekuitas', null, 'debit', false],

            // PENDAPATAN
            ['4-1000', 'PENDAPATAN', 'pendapatan', null, 'kredit', true],
            ['4-1100', 'Pendapatan Penjualan', 'pendapatan', null, 'kredit', false],
            ['4-1200', 'Pendapatan Jasa', 'pendapatan', null, 'kredit', false],
            ['4-1900', 'Pendapatan Lain-lain', 'pendapatan', null, 'kredit', false],

            // BEBAN
            ['5-1000', 'BEBAN', 'beban', null, 'debit', true],
            ['5-1100', 'Beban Gaji', 'beban', null, 'debit', false],
            ['5-1200', 'Beban Sewa', 'beban', null, 'debit', false],
            ['5-1300', 'Beban Listrik, Air & Telepon', 'beban', null, 'debit', false],
            ['5-1400', 'Beban Perlengkapan', 'beban', null, 'debit', false],
            ['5-1500', 'Beban Depresiasi', 'beban', null, 'debit', false],
            ['5-1600', 'Beban Pemasaran', 'beban', null, 'debit', false],
            ['5-1900', 'Beban Lain-lain', 'beban', null, 'debit', false],
        ];

        foreach ($accounts as [$code, $name, $type, $category, $normal, $isHeader]) {
            ChartOfAccount::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name, 'type' => $type, 'category' => $category, 'normal_balance' => $normal,
                    'is_header' => $isHeader, 'is_active' => true,
                    'is_cash_bank' => in_array($code, ['1-1100', '1-1200']), // Kas & Bank
                ]
            );
        }
    }
}
