<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #141F2B; }
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #141F2B; padding-bottom: 12px; margin-bottom: 16px; }
        .title { font-size: 18px; font-weight: bold; }
        .subtitle { color: #4c6d8a; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        td, th { padding: 5px 4px; text-align: left; }
        .section-title { font-weight: bold; margin-top: 16px; margin-bottom: 4px; border-bottom: 1px solid #c1cedb; padding-bottom: 3px; }
        .amount { text-align: right; font-family: 'Courier New', monospace; }
        .total-row td { border-top: 1.5px solid #141F2B; font-weight: bold; padding-top: 6px; }
        .footer { margin-top: 30px; font-size: 10px; color: #6b8aa5; text-align: center; }
        .net { background: #fbf6e9; padding: 10px; margin-top: 16px; border: 1px solid #dbb968; }
        .net .amount { font-size: 16px; color: #4c3714; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="title">SLIP GAJI</div>
            <div class="subtitle">Periode {{ $item->period->label }}</div>
        </div>
        <div style="text-align:right">
            <div style="font-weight:bold">SDM HRIS</div>
            <div class="subtitle">Dicetak {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>

    <table>
        <tr>
            <td style="width:50%"><strong>Nama</strong>: {{ $item->employee->full_name }}</td>
            <td><strong>NIP</strong>: {{ $item->employee->nip }}</td>
        </tr>
        <tr>
            <td><strong>Departemen</strong>: {{ $item->employee->department?->name ?? '-' }}</td>
            <td><strong>Jabatan</strong>: {{ $item->employee->position?->title ?? '-' }}</td>
        </tr>
    </table>

    <div class="section-title">Pendapatan</div>
    <table>
        <tr><td>Gaji Pokok</td><td class="amount">Rp {{ number_format($item->basic_salary, 0, ',', '.') }}</td></tr>
        @foreach($item->allowances ?? [] as $a)
        <tr><td>{{ $a['label'] }}</td><td class="amount">Rp {{ number_format($a['amount'], 0, ',', '.') }}</td></tr>
        @endforeach
        @if($item->overtime_amount > 0)
        <tr><td>Lembur</td><td class="amount">Rp {{ number_format($item->overtime_amount, 0, ',', '.') }}</td></tr>
        @endif
        <tr class="total-row"><td>Total Pendapatan</td><td class="amount">Rp {{ number_format($item->gross_salary, 0, ',', '.') }}</td></tr>
    </table>

    <div class="section-title">Potongan</div>
    <table>
        <tr><td>BPJS Kesehatan (Karyawan)</td><td class="amount">Rp {{ number_format($item->bpjs_kesehatan_employee, 0, ',', '.') }}</td></tr>
        <tr><td>BPJS Ketenagakerjaan - JHT (Karyawan)</td><td class="amount">Rp {{ number_format($item->bpjs_jht_employee, 0, ',', '.') }}</td></tr>
        <tr><td>BPJS Ketenagakerjaan - JP (Karyawan)</td><td class="amount">Rp {{ number_format($item->bpjs_jp_employee, 0, ',', '.') }}</td></tr>
        <tr><td>PPh 21</td><td class="amount">Rp {{ number_format($item->pph21, 0, ',', '.') }}</td></tr>
        @foreach($item->other_deductions ?? [] as $d)
        <tr><td>{{ $d['label'] }}</td><td class="amount">Rp {{ number_format($d['amount'], 0, ',', '.') }}</td></tr>
        @endforeach
        <tr class="total-row"><td>Total Potongan</td><td class="amount">Rp {{ number_format($item->total_deductions, 0, ',', '.') }}</td></tr>
    </table>

    <div class="net">
        <table><tr><td><strong>GAJI BERSIH DITERIMA</strong></td><td class="amount">Rp {{ number_format($item->net_salary, 0, ',', '.') }}</td></tr></table>
    </div>

    <div class="section-title" style="margin-top:20px">Kontribusi Perusahaan (Informasi)</div>
    <table>
        <tr><td>BPJS Kesehatan (Perusahaan)</td><td class="amount">Rp {{ number_format($item->bpjs_kesehatan_company, 0, ',', '.') }}</td></tr>
        <tr><td>BPJS Ketenagakerjaan - JHT (Perusahaan)</td><td class="amount">Rp {{ number_format($item->bpjs_jht_company, 0, ',', '.') }}</td></tr>
        <tr><td>BPJS Ketenagakerjaan - JKK (Perusahaan)</td><td class="amount">Rp {{ number_format($item->bpjs_jkk_company, 0, ',', '.') }}</td></tr>
        <tr><td>BPJS Ketenagakerjaan - JKM (Perusahaan)</td><td class="amount">Rp {{ number_format($item->bpjs_jkm_company, 0, ',', '.') }}</td></tr>
        <tr><td>BPJS Ketenagakerjaan - JP (Perusahaan)</td><td class="amount">Rp {{ number_format($item->bpjs_jp_company, 0, ',', '.') }}</td></tr>
    </table>

    <div class="footer">
        Dokumen ini dihasilkan otomatis oleh sistem SDM HRIS dan sah tanpa tanda tangan basah.<br>
        PPh 21 dihitung dengan metode estimasi tarif progresif tahunan — bukan pengganti perhitungan resmi dari konsultan pajak.
    </div>
</body>
</html>
