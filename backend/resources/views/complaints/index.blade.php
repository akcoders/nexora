@use('App\Services\ComplaintOptions')
<x-app-layout>
    <x-slot name="title">Complaint register</x-slot>
    <x-slot name="pageTitle">Complaint register</x-slot>
    <x-slot name="breadcrumb">Complaints / Register</x-slot>
    @php
        $query = fn (array $changes = []) => array_filter(array_merge(request()->only(['type', 'status', 'priority', 'technician', 'customer', 'from', 'to', 'flag']), $changes), fn ($value) => $value !== null && $value !== '');
        $typeTotal = $typeCounts->sum();
        $statusPills = ['active' => 'All active', 'open' => 'Open', 'assigned' => 'Assigned', 'scheduled' => 'Scheduled', 'in_progress' => 'In progress', 'pending' => 'Pending', 'under_observation' => 'Observation', 'completed' => 'Work completed', 'closed' => 'Closed', 'cancelled' => 'Cancelled', 'all' => 'Everything'];
        $activeCount = collect(ComplaintOptions::ACTIVE_STATUSES)->sum(fn ($key) => (int) ($statusCounts[$key] ?? 0));
    @endphp

    <div class="nx-page-header">
        <div>
            <div class="nx-page-kicker">Customer service</div>
            <h2>Complaint register</h2>
            <p>Every customer call in one place — registration, visits, pending reasons and closure.</p>
        </div>
        <div class="nx-page-actions">
            <a class="btn btn-light border" href="{{ route('complaints.pending') }}"><i data-lucide="hourglass"></i>Pending management @if($metrics['followUps'])<span class="badge badge-soft-warning rounded-pill">{{ $metrics['followUps'] }} due</span>@endif</a>
            @can('complaints.create')<a class="btn btn-primary" href="{{ route('complaints.create') }}"><i data-lucide="circle-plus"></i>Register complaint</a>@endcan
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Active complaints" :value="$metrics['active']" icon="activity" tone="primary" :href="route('complaints.index', $query(['status' => 'active', 'flag' => null]))" /></div>
        <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Not yet assigned" :value="$metrics['unassigned']" icon="user-x" tone="info" :href="route('complaints.index', $query(['flag' => 'unassigned', 'status' => 'active']))" :active="($filters['flag'] ?? null) === 'unassigned'" /></div>
        <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Pending" :value="$metrics['pending']" icon="hourglass" tone="danger" :href="route('complaints.index', $query(['status' => 'pending', 'flag' => null]))" :active="$status === 'pending'" /></div>
        <div class="col-6 col-md-4 col-xl-2"><x-kpi label="SLA breached" :value="$metrics['breached']" icon="alarm-clock-off" tone="orange" :href="route('complaints.index', $query(['flag' => 'sla_breached', 'status' => 'active']))" :active="($filters['flag'] ?? null) === 'sla_breached'" /></div>
        <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Follow-ups due today" :value="$metrics['followUps']" icon="phone-forwarded" tone="warning" :href="route('complaints.index', $query(['flag' => 'follow_up_today', 'status' => 'active']))" :active="($filters['flag'] ?? null) === 'follow_up_today'" /></div>
        <div class="col-6 col-md-4 col-xl-2"><x-kpi label="Closed this month" :value="$metrics['closedThisMonth']" icon="circle-check-big" tone="success" :href="route('complaints.index', $query(['status' => 'closed', 'flag' => null, 'from' => now()->startOfMonth()->toDateString()]))" /></div>
    </div>

    <section class="nx-card nx-datatable-card">
        <div class="nx-card-tabs">
            <ul class="nav nav-tabs nx-tabs-scroll border-0" role="tablist" aria-label="Type of call">
                <li class="nav-item"><a class="nav-link {{ $type === 'all' ? 'active' : '' }}" href="{{ route('complaints.index', $query(['type' => null])) }}"><i data-lucide="layers"></i>All calls<span class="nx-count notranslate">{{ $typeTotal }}</span></a></li>
                @foreach(ComplaintOptions::CALL_TYPES as $key => $callType)
                    <li class="nav-item"><a class="nav-link {{ $type === $key ? 'active' : '' }}" href="{{ route('complaints.index', $query(['type' => $key])) }}"><i data-lucide="{{ $callType['icon'] }}"></i>{{ $callType['label'] }}<span class="nx-count notranslate">{{ (int) ($typeCounts[$key] ?? 0) }}</span></a></li>
                @endforeach
            </ul>
        </div>
        <div class="nx-datatable-filters">
            <nav class="nx-filter-pills" aria-label="Status">
                @foreach($statusPills as $key => $label)
                    @php $count = $key === 'active' ? $activeCount : ($key === 'all' ? $statusCounts->sum() : (int) ($statusCounts[$key] ?? 0)); @endphp
                    <a class="{{ $status === $key && empty($filters['flag']) ? 'active' : '' }}" href="{{ route('complaints.index', $query(['status' => $key, 'flag' => null])) }}">{{ $label }}<span class="nx-count notranslate">{{ $count }}</span></a>
                @endforeach
            </nav>
        </div>
        <form class="nx-datatable-filters" method="GET" action="{{ route('complaints.index') }}">
            <input type="hidden" name="type" value="{{ $type === 'all' ? '' : $type }}">
            <input type="hidden" name="status" value="{{ $status }}">
            @if(!empty($filters['flag']))<input type="hidden" name="flag" value="{{ $filters['flag'] }}">@endif
            <div><label class="form-label" for="fFrom">From</label><input type="date" id="fFrom" class="form-control" name="from" value="{{ $from?->toDateString() }}"></div>
            <div><label class="form-label" for="fTo">To</label><input type="date" id="fTo" class="form-control" name="to" value="{{ $to?->toDateString() ?? request('to') }}"></div>
            <div><label class="form-label" for="fPriority">Priority</label><select id="fPriority" class="form-select" name="priority"><option value="">Any</option>@foreach(ComplaintOptions::priorityOptions() as $value => $label)<option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div style="min-width:190px"><label class="form-label" for="fTech">Technician</label><select id="fTech" class="form-select" name="technician" data-searchable data-placeholder="Any technician"><option value="">Any technician</option>@foreach($technicians as $technician)<option value="{{ $technician->id }}" @selected((int) ($filters['technician'] ?? 0) === $technician->id)>{{ $technician->name }}</option>@endforeach</select></div>
            <div style="min-width:220px"><label class="form-label" for="fCustomer">Customer</label><select id="fCustomer" class="form-select" name="customer" data-searchable data-placeholder="Any customer"><option value="">Any customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected((int) ($filters['customer'] ?? 0) === $customer->id)>{{ $customer->name }} · {{ $customer->code }}</option>@endforeach</select></div>
            <div class="d-flex gap-2"><button class="btn btn-primary btn-sm" style="min-height:34px"><i data-lucide="filter"></i>Apply</button>@if(request()->hasAny(['from', 'to', 'priority', 'technician', 'customer', 'flag']))<a class="btn btn-light border btn-sm" style="min-height:34px" href="{{ route('complaints.index', ['type' => $type === 'all' ? null : $type, 'status' => $status]) }}">Reset</a>@endif</div>
            @if($from && ! request('from'))<div class="small text-secondary align-self-center">Showing the last 90 days. Pick dates to see older calls.</div>@endif
        </form>

        <table class="table nx-table" data-datatable data-dt-title="Complaint register" data-dt-order='[]' data-dt-empty="No complaints match these filters.">
            <thead>
                <tr>
                    <th data-priority="1">Complaint</th>
                    <th>Registered</th>
                    <th data-priority="2">Customer / site</th>
                    <th>Call type</th>
                    <th>Nature of complaint</th>
                    <th>Equipment</th>
                    <th>Priority</th>
                    <th data-priority="3">Status</th>
                    <th>Technician</th>
                    <th>Visits</th>
                    <th>SLA</th>
                    <th>Pending age</th>
                    <th>Follow-up</th>
                    <th class="no-export no-sort text-end" data-orderable="false"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($complaints as $complaint)
                    @php $meta = $complaint->statusMeta(); $sla = $complaint->slaState(); $bucket = in_array($complaint->status, ['pending', 'under_observation'], true) ? $complaint->pendingBucket() : null; @endphp
                    <tr class="{{ $sla['key'] === 'breached' ? 'nx-overdue-row' : '' }}">
                        <td data-order="{{ $complaint->registered_at->timestamp }}"><a class="nx-row-title nx-code notranslate" href="{{ route('complaints.show', $complaint) }}">{{ $complaint->complaint_no }}</a><span class="nx-row-sub">{{ $complaint->callTaker?->name ? 'By '.$complaint->callTaker->name : '' }}</span></td>
                        <td data-order="{{ $complaint->registered_at->timestamp }}" class="text-nowrap">{{ $complaint->registered_at->format('d M Y') }}<span class="nx-row-sub">{{ $complaint->registered_at->format('h:i A') }}</span></td>
                        <td style="min-width:200px"><span class="nx-row-title notranslate">{{ $complaint->customer?->name }}</span><span class="nx-row-sub">{{ collect([$complaint->branch?->name, $complaint->site_location])->filter()->unique()->join(' · ') ?: ($complaint->customer?->code) }}</span></td>
                        <td class="text-nowrap">{{ $complaint->callTypeLabel() }}</td>
                        <td style="min-width:180px">{{ $complaint->nature_of_complaint }}</td>
                        <td style="min-width:160px"><span class="nx-row-sub text-body">{{ $complaint->equipmentSummary() }}</span></td>
                        <td data-order="{{ array_search($complaint->priority, array_keys(ComplaintOptions::PRIORITIES)) }}"><x-pill :tone="$complaint->priorityTone()" :label="$complaint->priorityLabel()" /></td>
                        <td><x-pill :tone="$meta['tone']" :label="$meta['label']" /></td>
                        <td class="text-nowrap">{{ $complaint->technician?->name ?? '—' }}</td>
                        <td class="text-center notranslate">{{ $complaint->visits_count }}</td>
                        <td data-order="{{ $complaint->resolution_due_at?->timestamp }}"><span class="badge badge-soft-{{ $sla['tone'] }}" title="Resolve by {{ $complaint->resolution_due_at?->format('d M Y, h:i A') }}">{{ $sla['label'] }}</span></td>
                        <td data-order="{{ $bucket ? $complaint->pendingAgeInDays() : -1 }}">@if($bucket)<span class="badge badge-soft-{{ $bucket['tone'] === 'orange' ? 'orange' : $bucket['tone'] }}">{{ $complaint->pendingAgeInDays() }}d · {{ $bucket['label'] }}</span>@else<span class="text-secondary">—</span>@endif</td>
                        <td data-order="{{ $complaint->follow_up_date?->timestamp ?? 0 }}" class="text-nowrap {{ $complaint->follow_up_date && $complaint->follow_up_date->lte(today()) ? 'text-danger fw-semibold' : '' }}">{{ $complaint->follow_up_date?->format('d M Y') ?? '—' }}</td>
                        <td class="text-end"><a class="btn btn-sm btn-light border" href="{{ route('complaints.show', $complaint) }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="14" class="text-center text-secondary py-5">No complaints match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</x-app-layout>
