@use('App\Services\ComplaintOptions')
<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>
    <x-slot name="pageTitle">Operations dashboard</x-slot>
    <x-slot name="breadcrumb">Dashboard</x-slot>
    @php
        $show = fn (string $key) => ! in_array($key, $hidden, true);
        $w = fn (string $key) => 'data-widget="'.$key.'" data-hidden="'.($show($key) ? '0' : '1').'"';
        $firstName = explode(' ', auth()->user()->name)[0];
        $greeting = now()->hour < 12 ? 'Good morning' : (now()->hour < 17 ? 'Good afternoon' : 'Good evening');
    @endphp

    <div class="nx-dash-toolbar">
        <div>
            <h2 class="h5 mb-1">{{ $greeting }}, <span class="notranslate">{{ $firstName }}</span></h2>
            <div class="small text-secondary"><i data-lucide="calendar-days" style="width:14px"></i> {{ now()->format('l, d F Y') }} · Showing {{ strtolower($periods[$period]) }} ({{ $from->format('d M') }} – {{ $to->format('d M Y') }})</div>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <nav class="nx-filter-pills" aria-label="Period">
                @foreach($periods as $key => $label)<a class="{{ $period === $key ? 'active' : '' }}" href="{{ route('dashboard', ['period' => $key]) }}">{{ $label }}</a>@endforeach
            </nav>
            <button class="btn btn-light border" type="button" data-bs-toggle="offcanvas" data-bs-target="#dashboardCustomize"><i data-lucide="layout-panel-left"></i>Customize</button>
            <a class="btn btn-primary" href="{{ route('reports.builder') }}"><i data-lucide="table-properties"></i>Build a report</a>
        </div>
    </div>

    {{-- KPIs --}}
    <div {!! $w('kpis') !!}>
        <div class="row g-3 mb-3">
            @if($canComplaints)
                <div class="col-6 col-md-4 col-xl-3"><x-kpi label="Active complaints" :value="$metrics['active']" icon="clipboard-list" tone="primary" :href="route('complaints.index')" :foot="$metrics['registered'].' registered in period'" /></div>
                <div class="col-6 col-md-4 col-xl-3"><x-kpi label="Pending complaints" :value="$metrics['pending']" icon="hourglass" tone="danger" :href="route('complaints.pending')" :foot="$pendingTotal.' incl. observation'" /></div>
                <div class="col-6 col-md-4 col-xl-3"><x-kpi label="Follow-ups due" :value="$metrics['followUps']" icon="phone-forwarded" tone="warning" :href="route('complaints.pending', ['follow_up' => 'overdue'])" foot="Today and overdue" /></div>
                <div class="col-6 col-md-4 col-xl-3"><x-kpi label="SLA breached (open)" :value="$metrics['breached']" icon="alarm-clock-off" tone="orange" :href="route('complaints.index', ['flag' => 'sla_breached'])" foot="Past resolution time" /></div>
                <div class="col-6 col-md-4 col-xl-3"><x-kpi label="Closed in period" :value="$metrics['closed']" icon="circle-check-big" tone="success" :href="route('complaints.index', ['status' => 'closed', 'from' => $from->toDateString()])" :foot="$metrics['slaMet'] !== null ? $metrics['slaMet'].'% within SLA' : 'No closures yet'" /></div>
                <div class="col-6 col-md-4 col-xl-3"><x-kpi label="Avg. resolution time" :value="$metrics['avgResolution'] !== null ? $metrics['avgResolution'].' days' : '—'" icon="timer" tone="info" foot="Registration to closure" /></div>
            @endif
            @if($metrics['serviceJobsToday'] !== null)<div class="col-6 col-md-4 col-xl-3"><x-kpi label="Service jobs today" :value="$metrics['serviceJobsToday']" icon="wrench" tone="purple" :href="route('service-jobs.index')" /></div>@endif
            @if($canAttendance)<div class="col-6 col-md-4 col-xl-3"><x-kpi label="Present today" :value="$metrics['present'].' / '.$metrics['technicians']" icon="user-check" tone="secondary" :href="route('attendance.index')" :foot="max(0, $metrics['technicians'] - $metrics['present']).' technicians not checked in'" /></div>@endif
            <div class="col-6 col-md-4 col-xl-3"><x-kpi label="Open workflows" :value="$metrics['pendingTasks']" icon="git-branch" tone="dark" :href="route('workflows.index', ['scope' => 'open'])" :foot="$metrics['overdueTasks'].' overdue'" /></div>
        </div>
    </div>

    @if($canComplaints)
    <div class="row g-3 mb-3">
        <div class="col-xl-7" {!! $w('follow_ups') !!}>
            <section class="nx-card nx-widget">
                <div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="phone-forwarded"></i>Today's follow-ups</h3><p class="nx-card-subtitle">Pending complaints due for follow-up today or earlier</p></div><a class="btn btn-sm btn-light border" href="{{ route('complaints.pending', ['follow_up' => 'overdue']) }}">Pending board</a></div>
                <div class="table-responsive">
                    <table class="table nx-table nx-table-sm mb-0">
                        <thead><tr><th class="ps-4">Complaint</th><th>Customer</th><th>Reason / next action</th><th>Responsible</th><th class="pe-4">Due</th></tr></thead>
                        <tbody>
                            @forelse($followUps as $complaint)
                                <tr><td class="ps-4"><a class="nx-code notranslate" href="{{ route('complaints.show', [$complaint, 'tab' => 'pending']) }}">{{ $complaint->complaint_no }}</a></td><td class="notranslate">{{ $complaint->customer?->name }}</td><td style="min-width:180px"><span class="nx-row-title">{{ $complaint->pending_reason ?? ComplaintOptions::status($complaint->status)['label'] }}</span><span class="nx-row-sub">{{ $complaint->next_action }}</span></td><td>{{ $complaint->responsibleUser?->name ?? ComplaintOptions::label('pending_owners', $complaint->pending_owner) ?? '—' }}</td><td class="pe-4 text-nowrap {{ $complaint->follow_up_date->isToday() ? 'text-warning' : 'text-danger' }} fw-semibold">{{ $complaint->follow_up_date->isToday() ? 'Today' : $complaint->follow_up_date->format('d M') }}</td></tr>
                            @empty
                                <tr><td colspan="5"><div class="nx-empty py-4"><i data-lucide="party-popper"></i><strong>No follow-ups due</strong><div>Every pending complaint is on schedule.</div></div></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-xl-5" {!! $w('pending_age') !!}>
            <section class="nx-card nx-widget">
                <div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="timer"></i>Pending age</h3><p class="nx-card-subtitle">{{ $pendingTotal }} complaints pending or under observation</p></div></div>
                <div class="nx-card-body">
                    <div class="nx-age-bars">
                        @foreach($buckets as $key => $bucket)
                            <a class="nx-age-bar tone-{{ $bucket['tone'] }}" href="{{ route('complaints.pending', ['bucket' => $key]) }}"><span><x-pill :tone="$bucket['tone']" :label="$bucket['label']" /></span><span class="nx-age-track"><span class="nx-age-fill" style="width: {{ $pendingTotal ? round($bucket['count'] / $pendingTotal * 100) : 0 }}%"></span></span><strong class="notranslate">{{ $bucket['count'] }}</strong></a>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-8" {!! $w('complaint_trend') !!}>
            <section class="nx-card nx-widget"><div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="chart-spline"></i>Complaint trend</h3><p class="nx-card-subtitle">Registered vs closed · {{ strtolower($periods[$period]) }}</p></div></div><div class="nx-card-body"><div id="chartTrend" class="nx-chart"></div></div></section>
        </div>
        <div class="col-xl-4" {!! $w('call_types') !!}>
            <section class="nx-card nx-widget"><div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="chart-pie"></i>Calls by type</h3><p class="nx-card-subtitle">Registered in the period</p></div></div><div class="nx-card-body"><div id="chartCallTypes" class="nx-chart"></div></div></section>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-4" {!! $w('status_mix') !!}>
            <section class="nx-card nx-widget"><div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="chart-column"></i>Active by status</h3><p class="nx-card-subtitle">Click a bar to open the list</p></div></div><div class="nx-card-body"><div id="chartStatus" class="nx-chart-sm"></div></div></section>
        </div>
        <div class="col-xl-4" {!! $w('technician_load') !!}>
            <section class="nx-card nx-widget"><div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="hard-hat"></i>Technician workload</h3><p class="nx-card-subtitle">Open visits per technician</p></div></div><div class="nx-card-body"><div id="chartTechnicians" class="nx-chart-sm"></div></div></section>
        </div>
        <div class="col-xl-4" {!! $w('sla_risk') !!}>
            <section class="nx-card nx-widget">
                <div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="siren"></i>SLA watch list</h3><p class="nx-card-subtitle">Breached or due within 12 hours</p></div></div>
                <div class="nx-card-body py-2">
                    <ul class="nx-mini-list">
                        @forelse($slaRisk as $complaint)
                            @php $state = $complaint->slaState(); @endphp
                            <li><span class="min-w-0"><a class="nx-code notranslate" href="{{ route('complaints.show', $complaint) }}">{{ $complaint->complaint_no }}</a><span class="nx-row-sub text-truncate notranslate">{{ $complaint->customer?->name }} · {{ $complaint->technician?->name ?? 'Unassigned' }}</span></span><span class="text-end"><span class="badge badge-soft-{{ $state['tone'] }}">{{ $state['label'] }}</span><span class="nx-row-sub">{{ $complaint->resolution_due_at->diffForHumans() }}</span></span></li>
                        @empty
                            <li class="justify-content-center text-secondary">No complaint is close to breaching its SLA.</li>
                        @endforelse
                    </ul>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-6" {!! $w('top_customers') !!}>
            <section class="nx-card nx-widget"><div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="building-2"></i>Top customers</h3><p class="nx-card-subtitle">Most complaints in the period</p></div><a class="btn btn-sm btn-light border" href="{{ route('reports.builder', ['dataset' => 'complaints', 'group_by' => 'customer', 'chart' => 'hbar']) }}">Analyse</a></div><div class="nx-card-body"><div id="chartCustomers" class="nx-chart"></div></div></section>
        </div>
        <div class="col-xl-6" {!! $w('root_causes') !!}>
            <section class="nx-card nx-widget"><div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="search-check"></i>Top root causes</h3><p class="nx-card-subtitle">Closed complaints in the period</p></div><a class="btn btn-sm btn-light border" href="{{ route('reports.builder', ['dataset' => 'complaints', 'group_by' => 'root_cause', 'chart' => 'hbar']) }}">Analyse</a></div><div class="nx-card-body"><div id="chartRootCauses" class="nx-chart"></div></div></section>
        </div>
    </div>
    @endif

    <div {!! $w('my_reports') !!}>
        <div class="nx-section-head mt-1"><h3 class="h6 mb-0 d-flex align-items-center gap-2"><i data-lucide="pin" style="width:16px"></i>My pinned reports</h3><a class="small fw-semibold" href="{{ route('reports.builder') }}">Report builder →</a></div>
        <div class="row g-3 mb-3">
            @forelse($pinnedReports as $report)
                <div class="col-xl-6">
                    <section class="nx-card nx-widget" data-pinned-report data-url="{{ route('reports.saved.data', $report) }}">
                        <div class="nx-card-header"><div class="min-w-0"><h3 class="nx-card-title text-truncate"><i data-lucide="chart-bar"></i>{{ $report->name }}</h3><p class="nx-card-subtitle" data-report-meta>Loading…</p></div><a class="btn btn-sm btn-light border" href="{{ route('reports.builder', ['report' => $report->id]) }}">Open</a></div>
                        <div class="nx-card-body"><div data-report-body class="nx-chart"><div class="nx-empty py-5"><span class="spinner-border spinner-border-sm text-primary"></span></div></div></div>
                    </section>
                </div>
            @empty
                <div class="col-12"><div class="nx-card"><div class="nx-empty"><i data-lucide="chart-no-axes-combined"></i><strong>Make this dashboard yours</strong><div>Build any report — complaints by technician, pending by reason, parts by month — and pin it here.</div><a class="btn btn-primary btn-sm mt-3" href="{{ route('reports.builder') }}"><i data-lucide="plus"></i>Create a report</a></div></div></div>
            @endforelse
        </div>
    </div>

    <div class="row g-3">
        @if($canAttendance)
        <div class="col-xl-5" {!! $w('attendance') !!}>
            <section class="nx-card nx-widget"><div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="map-pin-check"></i>Attendance trend</h3><p class="nx-card-subtitle">Check-ins over the last 7 days</p></div><a class="btn btn-sm btn-light border" href="{{ route('attendance.index') }}">Attendance</a></div><div class="nx-card-body"><div id="chartAttendance" class="nx-chart-sm"></div></div></section>
        </div>
        @endif
        <div class="{{ $canAttendance ? 'col-xl-7' : 'col-12' }}" {!! $w('workflows') !!}>
            <section class="nx-card nx-widget">
                <div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="git-branch"></i>Recent workflows</h3><p class="nx-card-subtitle">Latest internal work items you can see</p></div><a class="btn btn-sm btn-light border" href="{{ route('workflows.index', ['scope' => 'open']) }}">All workflows</a></div>
                <div class="table-responsive">
                    <table class="table nx-table nx-table-sm mb-0">
                        <thead><tr><th class="ps-4">Workflow</th><th>Responsible</th><th>Due</th><th class="pe-4">Status</th></tr></thead>
                        <tbody>
                            @forelse($recentTasks as $task)
                                <tr><td class="ps-4"><a class="nx-row-title" href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a><span class="nx-row-sub notranslate">{{ $task->task_no }}</span></td><td>{{ $task->assignee?->name ?? '—' }}</td><td class="small {{ $task->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $task->due_at?->format('d M, h:i A') ?? '—' }}</td><td class="pe-4"><x-pill :tone="$task->isClosed() ? 'success' : ($task->isOverdue() ? 'danger' : 'warning')" :label="$task->isClosed() ? 'Completed' : ($task->isOverdue() ? 'Overdue' : 'Pending')" /></td></tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-secondary py-4">No workflows yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <div class="offcanvas offcanvas-end" tabindex="-1" id="dashboardCustomize" aria-labelledby="customizeTitle">
        <div class="offcanvas-header"><div><h5 class="offcanvas-title" id="customizeTitle">Customize dashboard</h5><small class="text-secondary">Choose the widgets you want to see. Saved for your login.</small></div><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div>
        <div class="offcanvas-body d-grid gap-2 align-content-start">
            @foreach($widgets as $key => $widget)
                <label class="nx-widget-toggle"><input class="form-check-input mt-1" type="checkbox" value="{{ $key }}" data-widget-toggle @checked($show($key))><span><strong class="d-block small">{{ $widget['label'] }}</strong><small>{{ $widget['hint'] }}</small></span></label>
            @endforeach
            <div class="d-flex justify-content-between align-items-center mt-2"><button type="button" class="btn btn-sm btn-light border" data-widgets-reset>Show all</button><span class="small text-secondary" data-save-status></span></div>
        </div>
    </div>

    <script type="application/json" id="dashboardData">@json(['charts' => $charts, 'urls' => ['register' => route('complaints.index'), 'preferences' => route('dashboard.preferences')]])</script>
    @push('scripts')@vite('resources/js/dashboard.js')@endpush
</x-app-layout>
