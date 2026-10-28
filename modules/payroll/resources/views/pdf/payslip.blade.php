<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 28px 34px; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #12302f; }
  h1 { font-size: 15px; margin: 0; }
  .muted { color: #5b6f6d; }
  .head { border-bottom: 2px solid #12302f; padding-bottom: 8px; margin-bottom: 10px; }
  table { width: 100%; border-collapse: collapse; }
  td, th { padding: 4px 6px; vertical-align: top; }
  .grid td { padding: 2px 0; }
  .lines th { background: #12302f; color: #fff; text-align: left; font-size: 9px; }
  .lines td { border-bottom: 1px solid #dfe6e4; }
  .r { text-align: right; }
  .tot td { font-weight: bold; border-top: 1px solid #12302f; }
  .net { background: #eaf6f1; border: 1px solid #17806a; padding: 8px 10px; margin-top: 10px; }
  .net .v { font-size: 16px; font-weight: bold; color: #17806a; }
  .foot { margin-top: 14px; font-size: 8px; color: #5b6f6d; border-top: 1px dashed #b9c6c3; padding-top: 6px; }
</style>
</head>
<body>
@php
  $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
  $s = $item->employee_snapshot;
  $earn = collect($item->lines)->where('type', 'earning');
  $ded = collect($item->lines)->where('type', 'deduction');
  $bpjsEe = $item->bpjs_kes_employee + $item->bpjs_jht_employee + $item->bpjs_jp_employee;
@endphp
<div class="head">
  <table><tr>
    <td><h1>{{ $company['name'] }}</h1><div class="muted">{{ $company['address'] }}</div></td>
    <td class="r"><h1>SLIP GAJI</h1><div class="muted">{{ $run->title }} · {{ $run->periodLabel() }}</div></td>
  </tr></table>
</div>

<table class="grid">
  <tr><td width="18%" class="muted">NIP</td><td width="32%">{{ $s['nip'] ?? '' }}</td><td width="18%" class="muted">Departemen</td><td>{{ $s['department'] ?? '-' }}</td></tr>
  <tr><td class="muted">Nama</td><td>{{ $s['full_name'] ?? '' }}</td><td class="muted">Jabatan</td><td>{{ $s['position'] ?? '-' }}</td></tr>
  <tr><td class="muted">Status PTKP</td><td>{{ $s['ptkp'] ?? '' }} · {{ ($s['has_npwp'] ?? false) ? 'ber-NPWP' : 'tanpa NPWP' }}</td><td class="muted">Rekening</td><td>{{ $s['bank_name'] ?? '-' }} {{ $s['bank_masked'] ?? '' }}</td></tr>
  <tr><td class="muted">Tanggal bayar</td><td>{{ $run->pay_date->format('d/m/Y') }}</td><td class="muted">No. putaran</td><td>{{ $run->run_number }}</td></tr>
</table>

<br>
<table class="lines">
  <tr><th width="50%">PENDAPATAN</th><th class="r">Jumlah</th><th width="2%"></th><th width="50%">POTONGAN</th><th class="r">Jumlah</th></tr>
  @php $n = max($earn->count(), $ded->count() + 4); $dl = collect([['BPJS Kesehatan (1%)', $item->bpjs_kes_employee], ['JHT karyawan (2%)', $item->bpjs_jht_employee], ['JP karyawan (1%)', $item->bpjs_jp_employee], ['PPh Pasal 21', $item->pph21]])->concat($ded->map(fn ($d) => [$d['name'], $d['amount']]))->filter(fn ($x) => abs($x[1]) > 0.004)->values(); $e = $earn->values(); $n = max($e->count(), $dl->count()); @endphp
  @for ($i = 0; $i < $n; $i++)
  <tr>
    <td>{{ $e[$i]['name'] ?? '' }}</td><td class="r">{{ isset($e[$i]) ? $rp($e[$i]['amount']) : '' }}</td><td></td>
    <td>{{ $dl[$i][0] ?? '' }}</td><td class="r">{{ isset($dl[$i]) ? $rp($dl[$i][1]) : '' }}</td>
  </tr>
  @endfor
  <tr class="tot"><td>Total pendapatan</td><td class="r">{{ $rp($item->gross_earnings) }}</td><td></td><td>Total potongan</td><td class="r">{{ $rp($item->total_employee_deductions) }}</td></tr>
</table>

<div class="net"><table><tr><td>GAJI BERSIH (take home pay)</td><td class="r v">{{ $rp($item->net_pay) }}</td></tr></table></div>

<br>
<table class="grid">
  <tr><td class="muted" width="50%">Informasi pajak</td><td class="muted">Iuran ditanggung perusahaan (info)</td></tr>
  <tr>
    <td>
      @if ($item->pph21_method === 'ter') Metode TER kategori {{ $item->ter_category }} · tarif {{ rtrim(rtrim(number_format($item->ter_rate, 2), '0'), '.') }}% atas bruto {{ $rp($item->tax_gross) }}
      @elseif ($item->pph21_method === 'final') Masa pajak terakhir: PPh 21 setahun dihitung ulang (tarif Pasal 17)
      @else Tidak ada pemotongan PPh 21 @endif
    </td>
    <td>
      BPJS Kes {{ $rp($item->bpjs_kes_company) }} · JHT {{ $rp($item->bpjs_jht_company) }} · JKK {{ $rp($item->bpjs_jkk_company) }} · JKM {{ $rp($item->bpjs_jkm_company) }} · JP {{ $rp($item->bpjs_jp_company) }}
    </td>
  </tr>
</table>

<div class="foot">
  Dokumen ini dihasilkan otomatis oleh PAYBRIDGE dari kontrak kompensasi yang telah disetujui dan sah tanpa tanda tangan.
  Kode verifikasi: {{ strtoupper(substr($item->item_hash, 0, 16)) }} · Putaran {{ $run->run_number }}@if($run->snapshot_hash) · Snapshot {{ strtoupper(substr($run->snapshot_hash, 0, 12)) }}@endif
</div>
</body>
</html>
