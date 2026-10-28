<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>{{ $d->number }}</title>
<style>
 body{font-family:DejaVu Sans,sans-serif;font-size:11px;color:#111}
 h1{font-size:20px;margin:0} .muted{color:#555} table{width:100%;border-collapse:collapse}
 .items th{background:#eee;text-align:left;padding:6px;border:1px solid #bbb} .items td{padding:6px;border:1px solid #ccc}
 .r{text-align:right} .box{margin-top:14px} .sig td{height:80px;vertical-align:bottom;text-align:center;width:33%}
</style></head><body>
<table><tr>
 <td><h1>{{ $co['name'] }}</h1><div class="muted">{{ $co['address'] }}<br>{{ $co['phone'] }}</div></td>
 <td class="r"><h1>SURAT JALAN</h1><strong>{{ $d->number }}</strong><br>Tanggal: {{ $d->delivery_date->format('d/m/Y') }}<br>Ref. SO: {{ $d->order->number }}</td>
</tr></table>
<div class="box"><strong>Dikirim kepada:</strong> {{ $d->customer->name }}<br>{{ $d->ship_to }}</div>
<div class="box">Sopir: {{ $d->driver ?: '-' }} &nbsp;|&nbsp; No. kendaraan: {{ $d->vehicle_no ?: '-' }}</div>
<table class="items box"><thead><tr><th>#</th><th>Barang</th><th class="r">Qty</th><th>Satuan</th></tr></thead><tbody>
@foreach($d->items as $i)
<tr><td>{{ $loop->iteration }}</td><td>{{ $i->orderItem->description }}</td><td class="r">{{ rtrim(rtrim(number_format((float)$i->qty,3,',','.'),'0'),',') }}</td><td>{{ $i->orderItem->unit }}</td></tr>
@endforeach
</tbody></table>
@if($d->notes)<div class="box"><strong>Catatan:</strong> {{ $d->notes }}</div>@endif
<table class="sig box"><tr><td>Dibuat oleh<br><br><br>(................)</td><td>Sopir<br><br><br>(................)</td><td>Penerima<br><br><br>(................)</td></tr></table>
</body></html>
