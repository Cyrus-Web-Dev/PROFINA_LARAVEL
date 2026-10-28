<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #12302f; }
  h1 { font-size: 15px; margin: 0 0 4px; } table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  th { background: #12302f; color: #fff; text-align: left; padding: 5px 6px; } td { padding: 4px 6px; border-bottom: 1px solid #dfe6e4; }
  .r { text-align: right; } .tot td { font-weight: bold; border-top: 2px solid #12302f; } .note { margin-top: 14px; font-size: 8px; color: #5b6f6d; }
</style>
</head>
<body>
@php $rp = fn ($v) => number_format((float) $v, 0, ',', '.'); @endphp
<h1>Ringkasan PPh Pasal 21 Tahun {{ $year }}</h1>
<div>{{ $company['name'] }} @if($company['npwp']) · NPWP {{ $company['npwp'] }} @endif</div>
<table style="margin-top:8px">
  <tr><td width="20%">Nama</td><td>{{ $e->full_name }}</td><td width="20%">NIP</td><td>{{ $e->nip }}</td></tr>
  <tr><td>Status PTKP</td><td>{{ $e->ptkp_status }}</td><td>NPWP</td><td>{{ $e->has_npwp ? 'Ada' : 'Tidak ada' }}</td></tr>
</table>
<table>
  <tr><th>Masa</th><th class="r">Penghasilan bruto kena pajak</th><th class="r">Iuran pensiun (JHT+JP)</th><th class="r">PPh 21 dipotong</th></tr>
  @foreach ($months as $m => $v)
    <tr><td>{{ sprintf('%02d', $m) }}/{{ $year }}</td><td class="r">{{ $rp($v['gross']) }}</td><td class="r">{{ $rp($v['pension']) }}</td><td class="r">{{ $rp($v['pph']) }}</td></tr>
  @endforeach
  <tr class="tot"><td>Total</td><td class="r">{{ $rp($total['gross']) }}</td><td class="r">{{ $rp($total['pension']) }}</td><td class="r">{{ $rp($total['pph']) }}</td></tr>
</table>
<div class="note">Dokumen kerja internal untuk rekonsiliasi. Bukan pengganti bukti potong resmi (BPA1) yang diterbitkan melalui Coretax DJP. Angka berasal dari putaran payroll yang telah disetujui & dikunci.</div>
</body>
</html>
