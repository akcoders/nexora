<x-app-layout>
    <x-slot name="title">Reports</x-slot>
    <x-slot name="pageTitle">Reports & analytics</x-slot>
    <x-slot name="breadcrumb">Reports / Standard reports</x-slot>
    @php
        $standard = [
            ['Complaint register', 'Every complaint with status, SLA and pending age', 'clipboard-list', route('complaints.index', ['status' => 'all'])],
            ['Pending complaints', 'Complaint no · customer · problem · reason · age · owner · expected resolution', 'hourglass', route('complaints.pending')],
            ['Complaints by technician', 'Workload and status split per technician', 'hard-hat', route('reports.builder', ['dataset' => 'complaints', 'group_by' => 'technician', 'chart' => 'hbar'])],
            ['Calls by type', 'Breakdown, PM, installation and site visits', 'layers', route('reports.builder', ['dataset' => 'complaints', 'group_by' => 'call_type', 'chart' => 'donut'])],
            ['Root cause analysis', 'Why complaints happen, from closed calls', 'search-check', route('reports.builder', ['dataset' => 'complaints', 'group_by' => 'root_cause', 'chart' => 'hbar'])],
            ['Visits by outcome', 'Resolved, pending and observation visits', 'route', route('reports.builder', ['dataset' => 'complaint_visits', 'group_by' => 'outcome', 'chart' => 'pie'])],
            ['Parts consumption', 'Parts used and recommended', 'cog', route('reports.builder', ['dataset' => 'complaint_parts', 'group_by' => 'part_name', 'chart' => 'hbar'])],
            ['Attendance by employee', 'Check-ins per employee', 'map-pin-check', route('reports.builder', ['dataset' => 'attendance', 'group_by' => 'employee', 'chart' => 'hbar'])],
        ];
    @endphp

    <div class="nx-page-header">
        <div><div class="nx-page-kicker">Reports</div><h2>Operational analytics</h2><p>Standard reports, attendance and workflow performance for the last 30 days. Build anything else in the report builder.</p></div>
        <div class="nx-page-actions"><a class="btn btn-primary" href="{{ route('reports.builder') }}"><i data-lucide="table-properties"></i>Report builder</a></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-kpi label="Customers" :value="$summary['customers']" icon="building-2" tone="primary" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Technicians" :value="$summary['technicians']" icon="hard-hat" tone="info" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Check-ins this month" :value="$summary['attendance']" icon="calendar-check" tone="purple" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Workflow completion" :value="$summary['completion'].'%'" icon="circle-check-big" tone="success" /></div>
    </div>

    <section class="nx-card mb-3">
        <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="files"></i>Standard reports</h3><span class="small text-secondary">Open, filter and export to Excel, CSV, PDF or print</span></div>
        <div class="nx-card-body">
            <div class="row g-2">
                @foreach($standard as [$label, $hint, $icon, $url])
                    <div class="col-md-6 col-xl-3"><a class="nx-widget-toggle h-100 text-body" href="{{ $url }}"><span class="nx-metric-icon flex-shrink-0" style="width:34px;height:34px"><i data-lucide="{{ $icon }}" style="width:16px"></i></span><span><strong class="d-block small">{{ $label }}</strong><small>{{ $hint }}</small></span></a></div>
                @endforeach
            </div>
        </div>
    </section>

    <div class="row g-3 mb-3">
        <div class="col-xl-8"><section class="nx-card h-100"><div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="chart-spline"></i>Attendance & workflow volume</h3><p class="nx-card-subtitle">Daily activity across the last month</p></div></div><div class="nx-card-body"><div id="operationsChart" style="min-height:320px"></div></div></section></div>
        <div class="col-xl-4"><section class="nx-card h-100"><div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="gauge"></i>Workflow completion</h3><p class="nx-card-subtitle">Overall internal workflow status</p></div></div><div class="nx-card-body"><div id="completionChart" style="min-height:320px"></div></div></section></div>
    </div>

    <section class="nx-card nx-datatable-card mb-3">
        <div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="hard-hat"></i>Technician summary</h3><p class="nx-card-subtitle">Attendance records and assigned workload</p></div></div>
        <table class="table nx-table" data-datatable data-dt-title="Technician summary" data-dt-page-length="10" data-dt-empty="No technician data.">
            <thead><tr><th>Technician</th><th>Code</th><th>Department</th><th>Attendance records</th><th>Assigned workflows</th></tr></thead>
            <tbody>@foreach($technicianStats as $technician)<tr><td class="nx-row-title notranslate">{{ $technician->name }}</td><td class="nx-code notranslate">{{ $technician->employee_code }}</td><td>{{ $technician->department ?: '—' }}</td><td class="notranslate">{{ $technician->attendances_count }}</td><td class="notranslate">{{ $technician->assigned_tasks_count }}</td></tr>@endforeach</tbody>
        </table>
    </section>

    <section class="nx-card nx-datatable-card">
        <div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="calendar-x"></i>Workflow delays & revised dates</h3><p class="nx-card-subtitle">Every revision and reason for delay. Overdue status uses the original due date.</p></div></div>
        <table class="table nx-table" data-datatable data-dt-title="Workflow delays" data-dt-order='[[5,"desc"]]' data-dt-empty="No workflow revisions recorded.">
            <thead><tr><th>Workflow</th><th>Original due date</th><th>Revised due / next follow-up</th><th>Reason for delay</th><th>Updated by</th><th>Recorded at</th></tr></thead>
            <tbody>
                @foreach($revisions as $revision)
                    <tr><td><a class="nx-code notranslate" href="{{ route('tasks.show', $revision->task) }}">{{ $revision->task->task_no }}</a><span class="nx-row-sub">{{ $revision->task->title }}</span></td><td data-order="{{ $revision->task->due_at?->timestamp }}">{{ $revision->task->due_at?->format('d M Y, h:i A') ?? 'Not set' }}</td><td data-order="{{ $revision->revised_due_at->timestamp }}">{{ $revision->revised_due_at->format('d M Y, h:i A') }}</td><td style="min-width:220px;white-space:pre-wrap">{{ $revision->delay_reason }}</td><td class="notranslate">{{ $revision->user?->name }}</td><td data-order="{{ $revision->created_at->timestamp }}">{{ $revision->created_at->format('d M Y, h:i A') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </section>

    @push('scripts')<script>
        document.addEventListener('DOMContentLoaded', () => {
            const palette = ['#1f5fbf', '#0f8b8d'];
            new ApexCharts(document.querySelector('#operationsChart'), { chart: { type: 'area', height: 320, toolbar: { show: false }, fontFamily: 'Inter' }, series: [{ name: 'Attendance', data: @json($attendanceSeries) }, { name: 'New workflows', data: @json($taskSeries) }], xaxis: { categories: @json($labels), labels: { rotate: -45, hideOverlappingLabels: true } }, colors: palette, stroke: { curve: 'smooth', width: 3 }, fill: { type: 'gradient', gradient: { opacityFrom: .28, opacityTo: .03 } }, dataLabels: { enabled: false }, grid: { borderColor: '#edf0f5', strokeDashArray: 4 }, legend: { position: 'bottom' } }).render();
            new ApexCharts(document.querySelector('#completionChart'), { chart: { type: 'radialBar', height: 320, fontFamily: 'Inter' }, series: [{{ $summary['completion'] }}], colors: ['#15803d'], plotOptions: { radialBar: { hollow: { size: '64%' }, dataLabels: { name: { show: true, offsetY: 22 }, value: { fontSize: '30px', fontWeight: 700, offsetY: -14 } } } }, labels: ['Completed'] }).render();
        });
    </script>@endpush
</x-app-layout>
