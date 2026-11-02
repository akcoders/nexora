@use('App\Services\ComplaintOptions')
@php
    $label = fn (string $list, ?string $value) => ComplaintOptions::label($list, $value) ?? '—';
    $date = fn ($value, string $format = 'd M Y') => $value ? $value->format($format) : '—';
    $val = fn ($value) => filled($value) ? $value : '—';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>FSR {{ $complaint->complaint_no }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px; background: #eef1f5; color: #1e293b; font: 12px/1.45 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
        .sheet { max-width: 900px; margin: 0 auto; padding: 28px 30px; background: #fff; border: 1px solid #d8dee8; }
        .head { display: flex; justify-content: space-between; gap: 20px; padding-bottom: 14px; border-bottom: 3px solid #16233f; }
        .head h1 { margin: 0; font-size: 20px; color: #16233f; letter-spacing: .02em; }
        .head .doc { text-align: right; }
        .head .doc strong { display: block; font-size: 15px; color: #1f5fbf; }
        h2 { margin: 18px 0 8px; padding: 5px 8px; color: #fff; background: #16233f; font-size: 11.5px; letter-spacing: .08em; text-transform: uppercase; }
        h3 { margin: 12px 0 6px; font-size: 12.5px; color: #16233f; }
        table { width: 100%; border-collapse: collapse; }
        .grid td { width: 25%; padding: 5px 7px; border: 1px solid #e1e6ee; vertical-align: top; }
        .grid td span { display: block; color: #64748b; font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; }
        .list th, .list td { padding: 5px 6px; border: 1px solid #e1e6ee; text-align: left; vertical-align: top; }
        .list th { background: #f3f5f8; font-size: 10px; text-transform: uppercase; letter-spacing: .03em; color: #475569; }
        .visit { margin-bottom: 14px; padding: 10px; border: 1px solid #d8dee8; border-left: 4px solid #1f5fbf; break-inside: avoid; }
        .visit-title { display: flex; justify-content: space-between; font-weight: 700; color: #16233f; }
        .text { margin: 4px 0 0; padding: 6px 8px; background: #f8fafc; border: 1px solid #edf0f5; white-space: pre-line; }
        .muted { color: #64748b; }
        .sign { display: flex; justify-content: space-between; gap: 30px; margin-top: 34px; }
        .sign div { flex: 1; padding-top: 6px; border-top: 1px solid #94a3b8; text-align: center; color: #475569; }
        .toolbar { max-width: 900px; margin: 0 auto 12px; display: flex; justify-content: flex-end; gap: 8px; }
        .toolbar button, .toolbar a { padding: 8px 14px; color: #fff; background: #1f5fbf; border: 0; border-radius: 6px; font: 600 13px system-ui, sans-serif; text-decoration: none; cursor: pointer; }
        .toolbar a { color: #1e293b; background: #fff; border: 1px solid #cfd7e3; }
        @media print { body { padding: 0; background: #fff; } .sheet { border: 0; padding: 0; } .toolbar { display: none; } @page { size: A4; margin: 12mm; } }
    </style>
</head>
<body>
    <div class="toolbar"><a href="{{ route('complaints.show', $complaint) }}">Back</a><button type="button" onclick="window.print()">Print / Save as PDF</button></div>
    <div class="sheet">
        <div class="head">
            <div><h1 class="notranslate">{{ $companyName }}</h1><div class="muted">{{ collect([$companyPhone, $companyEmail])->filter()->join(' · ') }}</div><div class="muted">Field Service Report</div></div>
            <div class="doc"><strong>{{ $complaint->complaint_no }}</strong><div>Registered {{ $complaint->registered_at->format('d M Y, h:i A') }}</div><div>Status: {{ $complaint->statusMeta()['label'] }}</div></div>
        </div>

        <h2>Complaint</h2>
        <table class="grid">
            <tr><td><span>Customer</span>{{ $complaint->customer?->name }} ({{ $complaint->customer?->code }})</td><td><span>Branch / site</span>{{ $val(collect([$complaint->branch?->name, $complaint->site_location])->filter()->join(' · ')) }}</td><td><span>Contact person</span>{{ $val($complaint->contact_person) }}</td><td><span>Contact no</span>{{ $val($complaint->contact_no) }}</td></tr>
            <tr><td colspan="2"><span>Address</span>{{ $val($complaint->address) }}</td><td><span>Complaint given by</span>{{ $val($complaint->complaint_given_by) }}</td><td><span>Call taken by</span>{{ $val($complaint->callTaker?->name) }}</td></tr>
            <tr><td><span>Mode of call</span>{{ $label('call_modes', $complaint->call_mode) }}</td><td><span>Source</span>{{ $label('sources', $complaint->complaint_source) }}</td><td><span>Type of call</span>{{ $complaint->callTypeLabel() }}</td><td><span>Priority / SLA</span>{{ $complaint->priorityLabel() }} · {{ $complaint->sla_response_hours }}h / {{ $complaint->sla_resolution_hours }}h</td></tr>
            <tr><td colspan="2"><span>Nature of complaint</span>{{ $complaint->nature_of_complaint }}</td><td colspan="2"><span>Unit location</span>{{ $val($complaint->unit_location) }}</td></tr>
            @if($complaint->description)<tr><td colspan="4"><span>Description</span>{{ $complaint->description }}</td></tr>@endif
        </table>

        <h2>Equipment</h2>
        <table class="grid">
            <tr><td><span>Equipment ID</span>{{ $val($complaint->equipment_tag) }}</td><td><span>Type</span>{{ $label('equipment_types', $complaint->equipment_type) }}</td><td><span>Make / model</span>{{ $val(collect([$complaint->make, $complaint->model])->filter()->join(' · ')) }}</td><td><span>Capacity</span>{{ $val($complaint->capacity) }}</td></tr>
            <tr><td><span>Serial no</span>{{ $val($complaint->serial_no) }}</td><td><span>Refrigerant</span>{{ $label('refrigerants', $complaint->refrigerant) }}</td><td><span>Compressor / technology</span>{{ $label('compressor_types', $complaint->compressor_type) }} · {{ $label('technologies', $complaint->technology) }}</td><td><span>Power supply</span>{{ $label('power_supplies', $complaint->power_supply) }}</td></tr>
            <tr><td><span>Installation</span>{{ $date($complaint->installation_date) }}</td><td><span>Last service / PM</span>{{ $date($complaint->last_service_date) }} / {{ $date($complaint->last_pm_date) }}</td><td><span>Category</span>{{ $label('service_categories', $complaint->service_category) }}</td><td><span>AMC no / expiry</span>{{ $val($complaint->contract_no) }} · {{ $date($complaint->contract_expiry) }}</td></tr>
        </table>

        <h2>Visits</h2>
        @forelse($complaint->visits as $visit)
            <div class="visit">
                <div class="visit-title"><span>{{ $visit->label() }} · {{ $visit->reason_for_visit ?: 'Visit' }}</span><span>{{ $visit->statusMeta()['label'] }}@if($visit->outcomeMeta()) · {{ $visit->outcomeMeta()['label'] }}@endif</span></div>
                <div class="muted">Team: {{ $visit->teamNames() }} · Assigned {{ $visit->assigned_at->format('d M Y, h:i A') }} by {{ $val($visit->assigner?->name) }} · Appointment {{ $date($visit->appointment_at, 'd M Y, h:i A') }}</div>
                <table class="grid" style="margin-top:6px">
                    <tr><td><span>Accepted</span>{{ $date($visit->accepted_at, 'd M, h:i A') }}</td><td><span>Arrival</span>{{ $date($visit->arrived_at, 'd M, h:i A') }}</td><td><span>Site start / end</span>{{ $date($visit->started_at, 'h:i A') }} – {{ $date($visit->ended_at, 'h:i A') }}</td><td><span>Travel / labour</span>{{ $visit->travel_minutes !== null ? $visit->travel_minutes.' min' : '—' }} / {{ $visit->labour_hours !== null ? $visit->labour_hours.' h' : '—' }}</td></tr>
                </table>
                @if($visit->diagnosis)
                    <h3>Diagnosis readings</h3>
                    <div>{{ collect($visit->diagnosis)->map(function ($value, $key) { $field = ComplaintOptions::DIAGNOSIS_FIELDS[$key] ?? ['label' => $key, 'type' => 'text']; $text = match ($field['type']) { 'condition' => ComplaintOptions::label('conditions', $value), 'yes_no' => ComplaintOptions::label('yes_no', $value), default => $value }; return $field['label'].': '.$text.(isset($field['unit']) && is_numeric($value) ? ' '.$field['unit'] : ''); })->join(' · ') }}</div>
                @endif
                @foreach(['abnormality_found' => 'Abnormality found', 'observation' => 'Observation', 'problem_diagnosis' => 'Problem diagnosis', 'work_performed' => 'Work performed'] as $field => $title)
                    @if($visit->{$field})<h3>{{ $title }}</h3><div class="text">{{ $visit->{$field} }}</div>@endif
                @endforeach
                <div class="muted" style="margin-top:6px">Fault: {{ $val($visit->fault_category) }} · Code: {{ $val($visit->fault_code) }} · Root cause: {{ $val($visit->root_cause) }} · Final result: {{ $label('final_results', $visit->final_result) }}</div>
                @if($visit->customer_signed_name || $visit->customer_signature_path)
                    <div style="margin-top:8px;display:flex;align-items:center;gap:14px">@if($visit->customer_signature_path)<img src="{{ asset('storage/'.$visit->customer_signature_path) }}" alt="Signature" style="height:48px">@endif<div>Customer: <strong>{{ $visit->customer_signed_name }}</strong>@if($visit->customer_rating) · Rating {{ $visit->customer_rating }}/5 @endif @if($visit->customer_remarks)<div class="muted">“{{ $visit->customer_remarks }}”</div>@endif</div></div>
                @endif
            </div>
        @empty
            <p class="muted">No visits yet.</p>
        @endforelse

        @if($complaint->parts->isNotEmpty())
            <h2>Parts</h2>
            <table class="list">
                <thead><tr><th>Visit</th><th>Type</th><th>Part</th><th>Code</th><th>Qty</th><th>Part type</th><th>Status</th><th>Billable</th></tr></thead>
                <tbody>@foreach($complaint->parts as $part)<tr><td>{{ $part->visit?->label() }}</td><td>{{ $part->kind === 'used' ? 'Used' : 'Recommended' }}</td><td>{{ $part->part_name }}</td><td>{{ $part->part_code }}</td><td>{{ rtrim(rtrim(number_format((float) $part->quantity, 2), '0'), '.') }} {{ $label('uoms', $part->uom) }}</td><td>{{ $label('part_types', $part->part_type) }}</td><td>{{ $part->kind === 'used' ? ($part->old_part_returned ? 'Old part returned' : '') : $label('approval_statuses', $part->approval_status).' · '.$label('purchase_statuses', $part->purchase_status) }}</td><td>₹{{ number_format($part->lineTotal(), 2) }}</td></tr>@endforeach</tbody>
            </table>
        @endif

        <h2>Commercial</h2>
        <table class="grid">
            <tr><td><span>Visit</span>₹{{ number_format((float) $complaint->visit_charges, 2) }}</td><td><span>Labour</span>₹{{ number_format((float) $complaint->labour_charges, 2) }}</td><td><span>Parts</span>₹{{ number_format((float) $complaint->parts_charges, 2) }}</td><td><span>Travel / other</span>₹{{ number_format((float) $complaint->travel_charges + (float) $complaint->other_charges, 2) }}</td></tr>
            <tr><td><span>Discount</span>₹{{ number_format((float) $complaint->discount, 2) }}</td><td><span>GST ({{ (float) $complaint->gst_percent }}%)</span>₹{{ number_format((float) $complaint->gst_amount, 2) }}</td><td><span>Total invoice amount</span><strong>₹{{ number_format((float) $complaint->total_amount, 2) }}</strong></td><td><span>PO / quotation / invoice</span>{{ $val($complaint->customer_po_no) }} / {{ $val($complaint->quotation_no) }} / {{ $val($complaint->invoice_no) }}</td></tr>
        </table>

        @if($complaint->status === 'closed')
            <h2>Closure</h2>
            <table class="grid">
                <tr><td><span>Closed on</span>{{ $date($complaint->closed_at, 'd M Y, h:i A') }}</td><td><span>Closed by</span>{{ $val($complaint->closedBy?->name) }}</td><td><span>Root cause</span>{{ $val($complaint->root_cause) }}</td><td><span>Category</span>{{ $val($complaint->complaint_category) }}</td></tr>
                <tr><td><span>Happy call</span>{{ $date($complaint->happy_call_at, 'd M Y, h:i A') }}</td><td><span>Customer / team rating</span>{{ $complaint->customer_rating ? $complaint->customer_rating.'/5' : '—' }} / {{ $complaint->team_rating ? $complaint->team_rating.'/5' : '—' }}</td><td colspan="2"><span>Customer feedback</span>{{ $val($complaint->customer_feedback) }}</td></tr>
            </table>
        @endif

        <div class="sign"><div>Technician signature</div><div>Customer signature</div><div>Service manager</div></div>
        <p class="muted" style="margin-top:18px;text-align:center">Printed {{ now()->format('d M Y, h:i A') }}</p>
    </div>
</body>
</html>
