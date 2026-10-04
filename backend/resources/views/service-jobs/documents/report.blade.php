<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $serviceJob->job_no }} Service Report</title>
    <style>
        @page { margin: 24px 28px; }
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 9px; line-height: 1.4; }
        h1, h2, h3, p { margin: 0; }
        .header { width: 100%; border-bottom: 3px solid #1e40af; padding-bottom: 12px; margin-bottom: 16px; }
        .brand { color: #1e40af; font-size: 21px; font-weight: bold; }
        .title { font-size: 16px; font-weight: bold; text-align: right; }
        .muted { color: #64748b; }
        .badge { display: inline-block; background: #dbeafe; color: #1e40af; padding: 4px 8px; border-radius: 10px; font-weight: bold; }
        .section { margin-top: 14px; page-break-inside: avoid; }
        .section-title { background: #eff6ff; color: #1e3a8a; padding: 6px 8px; font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; background: #f8fafc; color: #475569; font-size: 8px; text-transform: uppercase; }
        th, td { border-bottom: 1px solid #e2e8f0; padding: 6px 7px; vertical-align: top; }
        .details td { width: 25%; border: 1px solid #e2e8f0; }
        .label { color: #64748b; font-size: 8px; margin-bottom: 2px; }
        .value { font-weight: bold; }
        .right { text-align: right; }
        .photo { width: 30%; height: 115px; object-fit: cover; margin: 5px; border: 1px solid #dbe3ef; }
        .signature { max-width: 170px; max-height: 65px; }
        .signature-box { width: 48%; height: 90px; border-top: 1px solid #94a3b8; padding-top: 6px; }
        .footer { margin-top: 18px; border-top: 1px solid #cbd5e1; padding-top: 8px; color: #64748b; font-size: 8px; text-align: center; }
    </style>
</head>
<body>
    <table class="header"><tr><td style="border:0;padding:0"><div class="brand">{{ $company['company_name'] ?? config('app.name') }}</div><div class="muted">{{ $company['company_email'] ?? '' }}{{ !empty($company['company_phone']) ? ' · '.$company['company_phone'] : '' }}</div></td><td style="border:0;padding:0" class="right"><div class="title">SERVICE REPORT</div><div>{{ $serviceJob->job_no }}</div><div class="muted">Generated {{ now()->format('d M Y, h:i A') }}</div></td></tr></table>

    <table class="details"><tr><td><div class="label">CUSTOMER</div><div class="value">{{ $serviceJob->customer->name }}</div></td><td><div class="label">PHONE</div><div class="value">{{ $serviceJob->customer_phone ?: '—' }}</div></td><td><div class="label">SERVICE DATE</div><div class="value">{{ ($serviceJob->service_completed_at ?? $serviceJob->scheduled_at)?->format('d M Y') ?? '—' }}</div></td><td><div class="label">STATUS</div><div class="value">{{ str($serviceJob->status)->replace('_', ' ')->headline() }}</div></td></tr><tr><td colspan="2"><div class="label">SERVICE ADDRESS</div><div class="value">{{ $serviceJob->service_address }}</div></td><td><div class="label">TECHNICIAN</div><div class="value">{{ $serviceJob->technician?->name ?? '—' }}</div></td><td><div class="label">SERVICE TYPE</div><div class="value">{{ $serviceJob->serviceType?->name ?? 'General Service' }}</div></td></tr></table>

    <div class="section"><div class="section-title">Equipment & Complaint</div><table><tr><td style="width:50%"><div class="label">EQUIPMENT</div><div class="value">{{ collect([$serviceJob->equipment?->equipment_type, $serviceJob->equipment?->brand, $serviceJob->equipment?->model, $serviceJob->equipment?->capacity])->filter()->join(' · ') ?: 'Not linked' }}</div><div class="muted">{{ collect([$serviceJob->equipment?->serial_no, $serviceJob->equipment?->location])->filter()->join(' · ') }}</div></td><td><div class="label">CUSTOMER COMPLAINT</div><div>{{ $serviceJob->complaint }}</div></td></tr></table></div>

    @foreach($serviceJob->inspections as $inspection)
        <div class="section"><div class="section-title">{{ $inspection->phase === 'pre' ? 'Pre-Service Inspection' : 'Post-Service Checklist' }}</div><table><thead><tr><th>Check</th><th>Condition</th><th>Finding / Remark</th></tr></thead><tbody>@foreach($inspection->items as $item)<tr><td>{{ $item->label }}</td><td><strong>{{ $item->condition?->name ?? '—' }}</strong></td><td>{{ $item->remark ?: '—' }}</td></tr>@endforeach</tbody></table></div>
    @endforeach

    @if($serviceJob->performedServices->isNotEmpty() || $serviceJob->materials->isNotEmpty())
        <div class="section"><div class="section-title">Work Completed & Materials Used</div><table><thead><tr><th>Item</th><th>Type</th><th>Qty</th><th class="right">Amount</th></tr></thead><tbody>@foreach($serviceJob->performedServices as $service)<tr><td>{{ $service->name }}<div class="muted">{{ $service->remark }}</div></td><td>Service</td><td>{{ $service->quantity }}</td><td class="right">₹{{ number_format((float) $service->line_total, 2) }}</td></tr>@endforeach @foreach($serviceJob->materials as $material)<tr><td>{{ $material->name }}<div class="muted">{{ $material->remark }}</div></td><td>{{ $material->is_inventory ? 'Inventory material' : 'Other material' }}</td><td>{{ $material->quantity }} {{ $material->unit }}</td><td class="right">₹{{ number_format((float) $material->line_total, 2) }}</td></tr>@endforeach</tbody></table></div>
    @endif

    @php($estimate = $serviceJob->estimates->first())
    <div class="section"><div class="section-title">Amount & Payment</div><table><tr><td><div class="label">APPROVED ESTIMATE</div><div class="value">₹{{ number_format((float) ($estimate?->final_amount ?? 0), 2) }}</div></td><td><div class="label">FINAL SERVICE AMOUNT</div><div class="value">₹{{ number_format((float) $serviceJob->final_amount, 2) }}</div></td><td><div class="label">AMOUNT RECEIVED</div><div class="value">₹{{ number_format($paidAmount, 2) }}</div></td><td><div class="label">PAYMENT STATUS</div><div class="value">{{ str($serviceJob->payment_status)->headline() }}</div></td></tr></table>@if($serviceJob->payments->isNotEmpty())<table><thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="right">Amount</th></tr></thead><tbody>@foreach($serviceJob->payments as $payment)<tr><td>{{ $payment->paid_at?->format('d M Y, h:i A') }}</td><td>{{ str($payment->method)->headline() }}</td><td>{{ $payment->transaction_reference ?: '—' }}</td><td class="right">₹{{ number_format((float) $payment->amount, 2) }}</td></tr>@endforeach</tbody></table>@endif</div>

    @php($evidence = $serviceJob->photos->whereIn('category', ['before','after'])->take(6))
    @if($evidence->isNotEmpty())<div class="section"><div class="section-title">Before / After Evidence</div><div style="text-align:center">@foreach($evidence as $photo)@if($images[$photo->path] ?? null)<img class="photo" src="{{ $images[$photo->path] }}" alt="{{ $photo->category }} photo">@endif @endforeach</div></div>@endif

    <div class="section" style="margin-top:28px"><table><tr><td class="signature-box">@php($customerSignature = $serviceJob->signatures->where('type', 'completion')->first()) @if($customerSignature && ($images[$customerSignature->path] ?? null))<img class="signature" src="{{ $images[$customerSignature->path] }}" alt="Customer signature"><br>@endif<strong>{{ $customerSignature?->signer_name ?? 'Customer' }}</strong><br><span class="muted">Customer Signature · {{ $customerSignature?->signed_at?->format('d M Y, h:i A') }}</span></td><td style="width:4%;border:0"></td><td class="signature-box"><br><br><strong>{{ $serviceJob->technician?->name ?? 'Technician' }}</strong><br><span class="muted">Service Technician · {{ $serviceJob->completed_at?->format('d M Y, h:i A') }}</span></td></tr></table></div>

    <div class="footer">This report records service work performed for {{ $serviceJob->job_no }}. Keep it for your equipment service history.</div>
</body>
</html>
