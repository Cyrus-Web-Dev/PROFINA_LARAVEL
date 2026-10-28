<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>{{ $q->number }}</title>
<style>
 body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#111}
 h1{font-size:20px;margin:0} .muted{color:#555} table{width:100%;border-collapse:collapse}
 .items th{background:#eee;text-align:left;padding:6px;border:1px solid #bbb} .items td{padding:6px;border:1px solid #ccc}
 .r{text-align:right} .tot td{padding:4px 6px} .box{margin-top:14px}
</style></head><body>
<table><tr>
 <td><h1>{{ $co['name'] }}</h1><div class="muted">{{ $co['address'] }}<br>{{ $co['phone'] }} {{ $co['email'] }}</div></td>
 <td class="r"><h1>PENAWARAN</h1><strong>{{ $q->number }}</strong><br>Tanggal: {{ $q->quote_date->format('d/m/Y') }}<br>Berlaku s/d: {{ $q->valid_until->format('d/m/Y') }}</td>
</tr></table>
<div class="box"><strong>Kepada:</strong><br>{{ $q->customer->name }}@if($q->customer->company) — {{ $q->customer->company }}@endif<br>{{ $q->customer->address }}</div>
<table class="items box"><thead><tr><th>#</th><th>Deskripsi</th><th class="r">Qty</th><th>Sat</th><th class="r">Harga</th><th class="r">Disk %</th><th class="r">Jumlah</th></tr></thead><tbody>
@foreach($q->items as $i)
<tr><td>{{ $loop->iteration }}</td><td>{{ $i->description }}</td><td class="r">{{ rtrim(rtrim(number_format((float)$i->qty,3,',','.'),'0'),',') }}</td><td>{{ $i->unit }}</td><td class="r">{{ number_format((float)$i->unit_price,2,',','.') }}</td><td class="r">{{ (float)$i->discount_pct ?: '' }}</td><td class="r">{{ number_format((float)$i->line_total,2,',','.') }}</td></tr>
@endforeach
</tbody></table>
<table class="tot" style="width:45%;margin-left:55%;margin-top:8px">
<tr><td>Subtotal</td><td class="r">{{ number_format((float)$q->subtotal,2,',','.') }}</td></tr>
<tr><td>PPN {{ (float)$q->tax_pct }}%</td><td class="r">{{ number_format((float)$q->tax_amount,2,',','.') }}</td></tr>
<tr><td><strong>Total</strong></td><td class="r"><strong>Rp {{ number_format((float)$q->total,2,',','.') }}</strong></td></tr>
</table>
@if($q->terms)<div class="box"><strong>Syarat & ketentuan</strong><br>{!! nl2br(e($q->terms)) !!}</div>@endif
@if($q->notes)<div class="box"><strong>Catatan</strong><br>{!! nl2br(e($q->notes)) !!}</div>@endif
@if($co['bank'])<div class="box muted">Pembayaran: {{ $co['bank'] }}</div>@endif
<div class="box" style="margin-top:40px">Hormat kami,<br><br><br>{{ $q->sales?->name }}</div>
</body></html>
