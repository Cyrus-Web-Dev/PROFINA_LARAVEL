<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }
    .header { display: table; width: 100%; margin-bottom: 24px; }
    .header .company { display: table-cell; width: 60%; vertical-align: top; }
    .header .invoice-info { display: table-cell; width: 40%; vertical-align: top; text-align: right; }
    .company-name { font-size: 18px; font-weight: bold; color: #0f172a; }
    .muted { color: #64748b; }
    h1 { font-size: 22px; color: #059669; margin: 0 0 4px; }
    .badge { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 10px; font-weight: bold; margin-top: 4px; }
    .badge-unpaid { background: #fef3c7; color: #b45309; }
    .badge-partial { background: #dbeafe; color: #1d4ed8; }
    .badge-paid { background: #d1fae5; color: #047857; }
    .bill-to { margin: 20px 0; }
    .bill-to .label { font-size: 9px; text-transform: uppercase; color: #94a3b8; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 16px; }
    table.items th { text-align: left; font-size: 9px; text-transform: uppercase; color: #94a3b8; border-bottom: 2px solid #e2e8f0; padding: 8px 6px; }
    table.items td { padding: 8px 6px; border-bottom: 1px solid #f1f5f9; }
    .text-right { text-align: right; }
    .totals { width: 280px; margin-left: auto; margin-top: 12px; }
    .totals table { width: 100%; }
    .totals td { padding: 4px 6px; }
    .totals .grand-total { font-size: 14px; font-weight: bold; border-top: 2px solid #0f172a; }
    .footer { margin-top: 40px; padding-top: 16px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>
    <div class="header">
        <div class="company">
            @if($company->logo_data_uri)<img src="{{ $company->logo_data_uri }}" style="max-height:52px;max-width:180px;margin-bottom:6px;"><br>@endif
            <div class="company-name">{{ $company->name }}</div>
            @if($company->legal_name)<div class="muted">{{ $company->legal_name }}</div>@endif
            @if($company->address)<div class="muted">{{ $company->address }}</div>@endif
            <div class="muted">
                @if($company->phone) {{ $company->phone }} @endif
                @if($company->email) &middot; {{ $company->email }} @endif
            </div>
            @if($company->npwp)<div class="muted">NPWP: {{ $company->npwp }}</div>@endif
        </div>
        <div class="invoice-info">
            <h1>INVOICE</h1>
            <div class="muted">{{ $invoice->invoice_number }}</div>
            <div class="badge badge-{{ $invoice->status === 'paid' ? 'paid' : ($invoice->status === 'partial' ? 'partial' : 'unpaid') }}">
                {{ strtoupper($invoice->status) }}
            </div>
        </div>
    </div>

    <div class="bill-to">
        <div class="label">Ditagihkan kepada</div>
        <div style="font-weight:bold;font-size:13px;">{{ $invoice->customer->name }}</div>
        @if($invoice->customer->address)<div class="muted">{{ $invoice->customer->address }}</div>@endif
        <div class="muted">
            @if($invoice->customer->phone) {{ $invoice->customer->phone }} @endif
            @if($invoice->customer->email) &middot; {{ $invoice->customer->email }} @endif
        </div>
    </div>

    <table style="width:100%;">
        <tr>
            <td><span class="muted">Tanggal Invoice:</span> {{ $invoice->invoice_date->format('d M Y') }}</td>
            <td style="text-align:right;"><span class="muted">Jatuh Tempo:</span> {{ $invoice->due_date->format('d M Y') }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr><th>Deskripsi</th><th class="text-right">Qty</th><th class="text-right">Harga Satuan</th><th class="text-right">Jumlah</th></tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td class="text-right">{{ rtrim(rtrim(number_format($item->qty, 2, ',', '.'), '0'), ',') }}</td>
                <td class="text-right">{{ number_format($item->price, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($item->amount, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr><td class="muted">Subtotal</td><td class="text-right">{{ number_format($invoice->subtotal, 0, ',', '.') }}</td></tr>
            @if($invoice->tax_amount > 0)
            <tr><td class="muted">Pajak</td><td class="text-right">{{ number_format($invoice->tax_amount, 0, ',', '.') }}</td></tr>
            @endif
            <tr class="grand-total"><td>Total</td><td class="text-right">Rp {{ number_format($invoice->total, 0, ',', '.') }}</td></tr>
            @if($invoice->paid_amount > 0)
            <tr><td class="muted">Sudah Dibayar</td><td class="text-right">{{ number_format($invoice->paid_amount, 0, ',', '.') }}</td></tr>
            <tr style="font-weight:bold;"><td>Sisa Tagihan</td><td class="text-right">{{ number_format($invoice->total - $invoice->paid_amount, 0, ',', '.') }}</td></tr>
            @endif
        </table>
    </div>

    @if($invoice->description)
    <div style="margin-top:24px;">
        <div class="label muted" style="font-size:9px;text-transform:uppercase;">Catatan</div>
        <div>{{ $invoice->description }}</div>
    </div>
    @endif

    <div class="footer">
        Dokumen ini dibuat otomatis oleh sistem {{ $company->name }} dan sah tanpa tanda tangan basah.
    </div>
</body>
</html>
