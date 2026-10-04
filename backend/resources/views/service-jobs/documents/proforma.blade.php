<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $serviceJob->job_no }} Proforma Invoice</title>
    <style>
        @page { margin: 30px; }
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 8px; vertical-align: top; }
        .brand { color: #1e40af; font-size: 22px; font-weight: bold; }
        .title { font-size: 18px; font-weight: bold; text-align: right; }
        .notice { margin: 16px 0; padding: 9px; border: 1px solid #fbbf24; background: #fffbeb; color: #92400e; text-align: center; font-weight: bold; }
        .box { border: 1px solid #dbe3ef; }
        .muted { color: #64748b; }
        .items th { background: #1e40af; color: #fff; font-size: 9px; text-align: left; }
        .items td { border-bottom: 1px solid #e2e8f0; }
        .right { text-align: right !important; }
        .totals { width: 45%; margin-left: 55%; margin-top: 14px; }
        .totals td { border-bottom: 1px solid #e2e8f0; }
        .grand td { font-size: 14px; font-weight: bold; color: #1e3a8a; }
        .footer { margin-top: 35px; border-top: 1px solid #cbd5e1; padding-top: 10px; color: #64748b; font-size: 8px; text-align: center; }
    </style>
</head>
<body>
    <table><tr><td style="padding:0"><div class="brand">{{ $company['company_name'] ?? config('app.name') }}</div><div class="muted">{{ $company['company_email'] ?? '' }}{{ !empty($company['company_phone']) ? ' · '.$company['company_phone'] : '' }}</div></td><td style="padding:0" class="title">PROFORMA INVOICE<br><span class="muted" style="font-size:10px">{{ $serviceJob->job_no }}</span></td></tr></table>
    <div class="notice">PROFORMA ONLY · This is not a tax/final invoice</div>
    <table class="box"><tr><td style="width:55%"><strong>Bill To</strong><br>{{ $serviceJob->customer->name }}<br>{{ $serviceJob->service_address }}<br>{{ $serviceJob->customer_phone }}</td><td><strong>Service Reference</strong><br>Job: {{ $serviceJob->job_no }}<br>Date: {{ ($serviceJob->service_completed_at ?? $serviceJob->scheduled_at)?->format('d M Y') ?? now()->format('d M Y') }}<br>Technician: {{ $serviceJob->technician?->name ?? '—' }}<br>Payment: {{ str($serviceJob->payment_status)->headline() }}</td></tr></table>

    @php($estimate = $serviceJob->estimates->first())
    <table class="items" style="margin-top:18px"><thead><tr><th>DESCRIPTION</th><th>TYPE</th><th class="right">QTY</th><th class="right">RATE</th><th class="right">TAX</th><th class="right">TOTAL</th></tr></thead><tbody>
        @if($estimate)
            @foreach($estimate->items as $item)<tr><td>{{ $item->description }}</td><td>{{ str($item->type)->headline() }}</td><td class="right">{{ $item->quantity }} {{ $item->unit }}</td><td class="right">₹{{ number_format((float) $item->unit_rate, 2) }}</td><td class="right">{{ number_format((float) $item->tax_percent, 2) }}%</td><td class="right">₹{{ number_format((float) $item->line_total, 2) }}</td></tr>@endforeach
        @else
            @foreach($serviceJob->performedServices as $item)<tr><td>{{ $item->name }}</td><td>Service</td><td class="right">{{ $item->quantity }}</td><td class="right">₹{{ number_format((float) $item->unit_rate, 2) }}</td><td class="right">{{ number_format((float) $item->tax_percent, 2) }}%</td><td class="right">₹{{ number_format((float) $item->line_total, 2) }}</td></tr>@endforeach
            @foreach($serviceJob->materials as $item)<tr><td>{{ $item->name }}</td><td>Material</td><td class="right">{{ $item->quantity }} {{ $item->unit }}</td><td class="right">₹{{ number_format((float) $item->unit_rate, 2) }}</td><td class="right">—</td><td class="right">₹{{ number_format((float) $item->line_total, 2) }}</td></tr>@endforeach
        @endif
    </tbody></table>

    <table class="totals"><tr><td>Subtotal</td><td class="right">₹{{ number_format((float) ($estimate?->subtotal ?? $serviceJob->final_amount), 2) }}</td></tr><tr><td>Discount</td><td class="right">− ₹{{ number_format((float) ($estimate?->discount_amount ?? 0), 2) }}</td></tr><tr><td>Tax</td><td class="right">₹{{ number_format((float) ($estimate?->tax_amount ?? 0), 2) }}</td></tr><tr class="grand"><td>Total</td><td class="right">₹{{ number_format((float) ($serviceJob->final_amount ?: $estimate?->final_amount), 2) }}</td></tr><tr><td>Received</td><td class="right">₹{{ number_format($paidAmount, 2) }}</td></tr><tr><td>Pending</td><td class="right">₹{{ number_format(max(0, (float) $serviceJob->final_amount - $paidAmount), 2) }}</td></tr></table>

    <div class="footer">Generated for service job {{ $serviceJob->job_no }} · Accounting may convert this service transaction into a final invoice separately.</div>
</body>
</html>
