<x-app-layout>
    <x-slot name="title">{{ $serviceJob->job_no }}</x-slot>
    <x-slot name="pageTitle">{{ $serviceJob->job_no }}</x-slot>
    <x-slot name="breadcrumb">Service Jobs / {{ $serviceJob->job_no }}</x-slot>

    @php
        $stages = [
            ['Visit', ['assigned','contacted','confirmed','rescheduled','no_answer','on_the_way','arrived']],
            ['Inspect', ['inspection_in_progress','inspection_completed']],
            ['Approve', ['estimate_created','estimate_pending_approval','customer_approved']],
            ['Service', ['service_in_progress','service_completed']],
            ['Payment', ['payment_pending','partially_paid','paid']],
            ['Done', ['completed']],
        ];
        $currentStage = collect($stages)->search(fn ($stage) => in_array($serviceJob->status, $stage[1], true));
        $statusTone = match($serviceJob->status) { 'completed','paid','customer_approved' => 'success', 'cancelled','customer_declined' => 'danger', 'on_the_way','arrived','inspection_in_progress','service_in_progress' => 'primary', default => 'warning' };
        $priorityTone = match($serviceJob->priority) { 'emergency' => 'danger', 'very_high' => 'orange', 'high' => 'warning', default => 'secondary' };
        $paidAmount = (float) $serviceJob->payments->where('status', 'received')->sum('amount');
    @endphp

    <section class="nx-card mb-4">
        <div class="nx-complaint-head">
            <div class="min-w-0">
                <a href="{{ route('service-jobs.index') }}" class="small d-inline-flex align-items-center gap-1 mb-2"><i data-lucide="arrow-left" style="width:15px"></i>Service jobs</a>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="nx-complaint-no notranslate">{{ $serviceJob->job_no }}</span>
                    <x-pill :tone="$statusTone" :label="$workflow->label($serviceJob->status)" />
                    <x-pill :tone="$priorityTone" :label="str($serviceJob->priority)->headline().' priority'" />
                </div>
                <h2 class="notranslate">{{ $serviceJob->customer->name }}</h2>
                <div class="nx-complaint-meta">
                    <span><i data-lucide="wrench"></i>{{ $serviceJob->serviceType?->name ?? 'General service' }}</span>
                    <span title="Assigned technician"><i data-lucide="hard-hat"></i>{{ $serviceJob->technician->name }}</span>
                    <span><i data-lucide="calendar"></i>Scheduled {{ $serviceJob->scheduled_at?->format('d M Y, h:i A') ?? '—' }}</span>
                </div>
            </div>
            <div class="nx-page-actions">
                <a class="btn btn-light border" href="{{ route('service-jobs.report', $serviceJob) }}"><i data-lucide="file-down"></i>Service report</a>
                <a class="btn btn-light border" href="{{ route('service-jobs.proforma', $serviceJob) }}"><i data-lucide="receipt-text"></i>Proforma</a>
            </div>
        </div>
        <div class="nx-flow" style="--steps: {{ count($stages) }}">
            @foreach($stages as $index => [$label, $statuses])
                @php($done = $currentStage !== false && ($index < $currentStage || $serviceJob->status === 'completed'))
                <div class="nx-flow-step {{ $done ? 'done' : ($currentStage === $index ? 'current' : '') }}"><span>@if($done)<i data-lucide="check"></i>@else{{ $index + 1 }}@endif</span>{{ $label }}</div>
            @endforeach
        </div>
    </section>

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="nx-card mb-4">
                <div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="clipboard-list"></i>Job details</h3><p class="nx-card-subtitle">Customer complaint and service location</p></div></div>
                <div class="nx-card-body">
                    <dl class="nx-dl">
                        <div style="grid-column:1/-1"><dt>Complaint</dt><dd>{{ $serviceJob->complaint }}</dd></div>
                        <div><dt>Equipment</dt><dd>{{ collect([$serviceJob->equipment?->equipment_type, $serviceJob->equipment?->brand, $serviceJob->equipment?->model, $serviceJob->equipment?->capacity])->filter()->join(' · ') ?: 'Not linked' }}@if($serviceJob->equipment?->location)<span class="nx-row-sub fw-normal">{{ $serviceJob->equipment->location }}</span>@endif</dd></div>
                        <div><dt>Phone</dt><dd class="notranslate"><a href="tel:{{ $serviceJob->customer_phone }}">{{ $serviceJob->customer_phone }}</a></dd></div>
                        <div><dt>Priority</dt><dd>{{ str($serviceJob->priority)->headline() }}</dd></div>
                        <div style="grid-column:1/-1"><dt>Address</dt><dd>{{ $serviceJob->service_address }}</dd></div>
                    </dl>
                </div>
            </section>

            <section class="nx-card mb-4">
                <div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="clipboard-check"></i>Work summary</h3><p class="nx-card-subtitle">Inspection, services, materials and evidence</p></div><span class="badge badge-soft-primary">{{ $serviceJob->photos->count() }} photos</span></div>
                <div class="nx-card-body">
                    <div class="row g-3"><div class="col-md-4"><div class="nx-review-box h-100"><div class="small text-secondary">Inspection</div><div class="h5 fw-bold mb-1">{{ $serviceJob->inspections->where('phase', 'pre')->first()?->items->whereNotNull('completed_at')->count() ?? 0 }}</div><div class="small">items checked</div></div></div><div class="col-md-4"><div class="nx-review-box h-100"><div class="small text-secondary">Services performed</div><div class="h5 fw-bold mb-1">{{ $serviceJob->performedServices->count() }}</div><div class="small">work items</div></div></div><div class="col-md-4"><div class="nx-review-box h-100"><div class="small text-secondary">Materials</div><div class="h5 fw-bold mb-1">{{ $serviceJob->materials->count() }}</div><div class="small">items consumed</div></div></div></div>
                    @if($serviceJob->performedServices->isNotEmpty() || $serviceJob->materials->isNotEmpty())<div class="table-responsive mt-4"><table class="table nx-table"><thead><tr><th>Item</th><th class="d-none d-sm-table-cell">Type</th><th class="text-end">Qty</th><th class="text-end">Amount</th></tr></thead><tbody>@foreach($serviceJob->performedServices as $service)<tr><td>{{ $service->name }}</td><td class="d-none d-sm-table-cell"><span class="badge badge-soft-primary">Service</span></td><td class="text-end text-nowrap">{{ $service->quantity }}</td><td class="text-end text-nowrap">₹{{ number_format((float) $service->line_total, 2) }}</td></tr>@endforeach @foreach($serviceJob->materials as $material)<tr><td>{{ $material->name }}</td><td class="d-none d-sm-table-cell"><span class="badge badge-soft-warning">{{ $material->is_inventory ? 'Inventory' : 'Other material' }}</span></td><td class="text-end text-nowrap">{{ $material->quantity }} {{ $material->unit }}</td><td class="text-end text-nowrap">₹{{ number_format((float) $material->line_total, 2) }}</td></tr>@endforeach</tbody></table></div>@endif
                </div>
            </section>

            @if($serviceJob->inspections->isNotEmpty())
                <section class="nx-card mb-4">
                    <div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="list-checks"></i>Inspection details</h3><p class="nx-card-subtitle">Checklist responses recorded by the technician</p></div></div>
                    <div class="nx-card-body">
                        @foreach($serviceJob->inspections as $inspection)
                            <div class="fw-semibold mb-2">{{ $inspection->phase === 'pre' ? 'Pre-service inspection' : 'Post-service checklist' }} <span class="badge {{ $inspection->status === 'completed' ? 'badge-soft-success' : 'badge-soft-warning' }}">{{ str($inspection->status)->headline() }}</span></div>
                            <div class="table-responsive {{ $loop->last ? '' : 'mb-4' }}"><table class="table nx-table"><thead><tr><th>Check</th><th>Condition</th><th>Remark</th></tr></thead><tbody>@foreach($inspection->items as $item)<tr><td>{{ $item->label }}</td><td><x-pill :tone="$item->condition?->color ?? 'secondary'" :label="$item->condition?->name ?? 'Pending'" /></td><td>{{ $item->remark ?: '—' }}</td></tr>@endforeach</tbody></table></div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if($serviceJob->photos->isNotEmpty())
                <section class="nx-card mb-4">
                    <div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="images"></i>Service photos</h3><p class="nx-card-subtitle">Evidence captured in the technician app</p></div><span class="nx-count">{{ $serviceJob->photos->count() }}</span></div>
                    <div class="nx-card-body"><div class="row g-3">@foreach($serviceJob->photos as $photo)<div class="col-6 col-md-4"><a href="{{ asset('storage/'.$photo->path) }}" target="_blank" class="text-decoration-none"><img src="{{ asset('storage/'.$photo->path) }}" class="img-fluid rounded border w-100" style="height:150px;object-fit:cover" alt="{{ $photo->category }} service photo"><div class="small text-secondary mt-1 text-capitalize">{{ $photo->category }}{{ $photo->caption ? ' · '.$photo->caption : '' }}</div></a></div>@endforeach</div></div>
                </section>
            @endif

            <section class="nx-card">
                <div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="history"></i>Job timeline</h3><p class="nx-card-subtitle">Status changes recorded for this job</p></div></div>
                <div class="nx-card-body"><div class="nx-timeline">@foreach($serviceJob->statusHistory as $history)<div class="nx-timeline-item"><span class="nx-timeline-dot"></span><div class="d-flex flex-wrap justify-content-between gap-2"><div><div class="fw-semibold">{{ $workflow->label($history->to_status) }}</div><div class="small text-secondary">{{ $history->remark ?: $history->reason }}{{ $history->changedBy ? ' · '.$history->changedBy->name : '' }}</div></div><time class="small text-secondary">{{ $history->changed_at->format('d M, h:i A') }}</time></div></div>@endforeach</div></div>
            </section>
        </div>
        <div class="col-xl-4">
            @can('service_jobs.assign')
                <aside class="nx-card mb-4">
                    <div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="calendar-clock"></i>Assignment & schedule</h3><p class="nx-card-subtitle">Changes are recorded in the job timeline.</p></div></div>
                    <form method="POST" action="{{ route('service-jobs.update', $serviceJob) }}" class="nx-card-body d-grid gap-3">@csrf @method('PUT')<div><label class="form-label">Technician</label><select class="form-select" name="assigned_to" required>@foreach($technicians as $technician)<option value="{{ $technician->id }}" @selected($serviceJob->assigned_to === $technician->id)>{{ $technician->name }}</option>@endforeach</select></div><div><label class="form-label">Scheduled date & time</label><input class="form-control" type="datetime-local" name="scheduled_at" value="{{ $serviceJob->scheduled_at?->format('Y-m-d\TH:i') }}" required></div><div><label class="form-label">Priority</label><select class="form-select" name="priority">@foreach(['normal','high','very_high','emergency'] as $priority)<option value="{{ $priority }}" @selected($serviceJob->priority === $priority)>{{ str($priority)->headline() }}</option>@endforeach</select></div><div><label class="form-label">Internal notes</label><textarea class="form-control" name="notes" rows="2">{{ $serviceJob->notes }}</textarea></div><button class="btn btn-primary" type="submit">Update assignment</button></form>
                </aside>
            @endcan
            <aside class="nx-card mb-4">
                <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="indian-rupee"></i>Financial summary</h3></div>
                <div class="nx-card-body"><dl class="nx-dl-rows"><div><dt>Estimate</dt><dd>₹{{ number_format((float) ($serviceJob->estimates->first()?->final_amount ?? 0), 2) }}</dd></div><div><dt>Final amount</dt><dd>₹{{ number_format((float) $serviceJob->final_amount, 2) }}</dd></div><div><dt>Received</dt><dd class="text-success">₹{{ number_format($paidAmount, 2) }}</dd></div><div><dt>Pending</dt><dd class="text-danger">₹{{ number_format(max(0, (float) $serviceJob->final_amount - $paidAmount), 2) }}</dd></div></dl></div>
            </aside>
            <aside class="nx-card mb-4">
                <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="camera"></i>Service evidence</h3></div>
                <div class="nx-card-body"><dl class="nx-dl-rows"><div><dt>Before photos</dt><dd>{{ $serviceJob->photos->where('category', 'before')->count() }}</dd></div><div><dt>During photos</dt><dd>{{ $serviceJob->photos->where('category', 'during')->count() }}</dd></div><div><dt>After photos</dt><dd>{{ $serviceJob->photos->where('category', 'after')->count() }}</dd></div><div><dt>Customer signatures</dt><dd>{{ $serviceJob->signatures->count() }}</dd></div></dl></div>
            </aside>
            @if($serviceJob->estimates->isNotEmpty())
                @php($estimate = $serviceJob->estimates->first())
                <aside class="nx-card mb-4">
                    <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="badge-check"></i>Customer approval</h3></div>
                    <div class="nx-card-body"><dl class="nx-dl-rows"><div><dt>Estimate version</dt><dd>#{{ $estimate->version }}</dd></div><div><dt>Decision</dt><dd>{{ str($estimate->status)->replace('_', ' ')->headline() }}</dd></div>@if($estimate->customer_name || $estimate->decision_remark)<div><dt>{{ $estimate->customer_name ? 'Customer' : 'Remark' }}</dt><dd>{{ $estimate->customer_name ?: $estimate->decision_remark }}</dd></div>@endif</dl></div>
                </aside>
            @endif
            @if($serviceJob->feedback)
                <aside class="nx-card">
                    <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="star"></i>Customer feedback</h3></div>
                    <div class="nx-card-body">@if($serviceJob->feedback->submitted_at)<div class="h4 text-warning mb-1">{{ str_repeat('★', (int) $serviceJob->feedback->rating) }}<span class="text-secondary">{{ str_repeat('☆', 5 - (int) $serviceJob->feedback->rating) }}</span></div><div class="small mb-2">{{ $serviceJob->feedback->comments ?: 'No comments provided.' }}</div>@if($serviceJob->feedback->issue_still_exists || $serviceJob->feedback->request_callback)<div class="alert alert-warning py-2 small mb-0">{{ $serviceJob->feedback->issue_still_exists ? 'Issue still exists. ' : '' }}{{ $serviceJob->feedback->request_callback ? 'Callback requested.' : '' }}</div>@endif @else<div class="small text-secondary mb-2">Waiting for customer response.</div><a class="small" href="{{ route('service-feedback.show', $serviceJob->feedback->token) }}" target="_blank">Open feedback link</a>@endif</div>
                </aside>
            @endif
        </div>
    </div>
</x-app-layout>
