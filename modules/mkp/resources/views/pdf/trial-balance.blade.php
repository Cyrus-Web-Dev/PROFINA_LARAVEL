@extends('pdf.layout')
@section('content')
<h1>Neraca Saldo (Trial Balance)</h1>
<p class="subtitle">Per tanggal {{ $asOfDate }}</p>

<table>
    <thead><tr><th>Kode</th><th>Nama Akun</th><th class="text-right">Debit</th><th class="text-right">Kredit</th></tr></thead>
    <tbody>
        @foreach ($report['rows'] as $r)
        <tr>
            <td>{{ $r['code'] }}</td>
            <td>{{ $r['name'] }}</td>
            <td class="text-right">{{ $r['debit'] > 0 ? number_format($r['debit'], 0, ',', '.') : '-' }}</td>
            <td class="text-right">{{ $r['credit'] > 0 ? number_format($r['credit'], 0, ',', '.') : '-' }}</td>
        </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="2" class="text-right">Total</td>
            <td class="text-right">{{ number_format($report['total_debit'], 0, ',', '.') }}</td>
            <td class="text-right">{{ number_format($report['total_credit'], 0, ',', '.') }}</td>
        </tr>
    </tbody>
</table>

<p style="margin-top:12px;">
    Status: <span class="badge {{ $report['is_balanced'] ? 'badge-ok' : 'badge-warn' }}">{{ $report['is_balanced'] ? 'SEIMBANG' : 'TIDAK SEIMBANG' }}</span>
</p>
@endsection
