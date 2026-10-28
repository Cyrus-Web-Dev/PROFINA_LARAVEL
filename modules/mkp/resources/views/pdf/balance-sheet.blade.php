@extends('pdf.layout')
@section('content')
<h1>Neraca (Laporan Posisi Keuangan)</h1>
<p class="subtitle">Per tanggal {{ $asOfDate }}</p>

<div class="section-title">Aset</div>
<table>
    <tbody>
        @foreach ($report['assets'] as $a)
        <tr><td>{{ $a['name'] }}</td><td class="text-right">{{ number_format($a['amount'], 0, ',', '.') }}</td></tr>
        @endforeach
        <tr class="total-row"><td>Total Aset</td><td class="text-right">{{ number_format($report['total_assets'], 0, ',', '.') }}</td></tr>
    </tbody>
</table>

<div class="section-title">Kewajiban</div>
<table>
    <tbody>
        @foreach ($report['liabilities'] as $l)
        <tr><td>{{ $l['name'] }}</td><td class="text-right">{{ number_format($l['amount'], 0, ',', '.') }}</td></tr>
        @endforeach
        <tr class="total-row"><td>Total Kewajiban</td><td class="text-right">{{ number_format($report['total_liabilities'], 0, ',', '.') }}</td></tr>
    </tbody>
</table>

<div class="section-title">Ekuitas</div>
<table>
    <tbody>
        @foreach ($report['equity'] as $e)
        <tr><td>{{ $e['name'] }}</td><td class="text-right">{{ number_format($e['amount'], 0, ',', '.') }}</td></tr>
        @endforeach
        <tr><td>Laba Tahun Berjalan</td><td class="text-right">{{ number_format($report['current_year_earnings'], 0, ',', '.') }}</td></tr>
        <tr class="total-row"><td>Total Kewajiban + Ekuitas</td><td class="text-right">{{ number_format($report['total_liabilities'] + $report['total_equity'], 0, ',', '.') }}</td></tr>
    </tbody>
</table>

<p style="margin-top:12px;">
    Status: <span class="badge {{ $report['is_balanced'] ? 'badge-ok' : 'badge-warn' }}">{{ $report['is_balanced'] ? 'BALANCE' : 'TIDAK BALANCE' }}</span>
</p>
@endsection
