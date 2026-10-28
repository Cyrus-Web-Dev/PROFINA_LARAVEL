@extends('pdf.layout')
@section('content')
<h1>Laporan Laba Rugi</h1>
<p class="subtitle">Periode {{ $startDate }} s/d {{ $endDate }}</p>

<div class="section-title">Pendapatan</div>
<table>
    <tbody>
        @foreach ($report['revenues'] as $r)
        <tr><td>{{ $r['name'] }}</td><td class="text-right">{{ number_format($r['amount'], 0, ',', '.') }}</td></tr>
        @endforeach
        <tr class="total-row"><td>Total Pendapatan</td><td class="text-right">{{ number_format($report['total_revenue'], 0, ',', '.') }}</td></tr>
    </tbody>
</table>

<div class="section-title">Beban</div>
<table>
    <tbody>
        @foreach ($report['expenses'] as $r)
        <tr><td>{{ $r['name'] }}</td><td class="text-right">{{ number_format($r['amount'], 0, ',', '.') }}</td></tr>
        @endforeach
        <tr class="total-row"><td>Total Beban</td><td class="text-right">{{ number_format($report['total_expense'], 0, ',', '.') }}</td></tr>
    </tbody>
</table>

<table style="margin-top:16px;">
    <tr class="total-row" style="font-size:13px;">
        <td>{{ $report['net_income'] >= 0 ? 'Laba Bersih' : 'Rugi Bersih' }}</td>
        <td class="text-right">{{ number_format(abs($report['net_income']), 0, ',', '.') }}</td>
    </tr>
</table>
@endsection
