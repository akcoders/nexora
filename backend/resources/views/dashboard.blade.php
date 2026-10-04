<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>
    <x-slot name="pageTitle">Operations overview</x-slot>
    <x-slot name="breadcrumb">Dashboard</x-slot>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div><h2 class="h5 mb-1">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', auth()->user()->name)[0] }}</h2><div class="text-secondary small">Here is what is happening across field operations today.</div></div>
        <div class="small text-secondary d-flex align-items-center gap-2"><i data-lucide="calendar-days" style="width:17px"></i>{{ now()->format('l, d F Y') }}</div>
    </div>

    @php
        $cards = [
            ['Technicians', $metrics['technicians'], 'users', '#2563eb', '#dbeafe'],
            ['Present today', $metrics['present'], 'user-check', '#16a34a', '#dcfce7'],
            ['Absent today', $metrics['absent'], 'user-x', '#dc2626', '#fee2e2'],
            ['Outside premises', $metrics['outside'], 'map-pin-off', '#ea580c', '#ffedd5'],
            ['Pending reviews', $metrics['reviews'], 'shield-question', '#7c3aed', '#ede9fe'],
            ['Pending tasks', $metrics['pendingTasks'], 'clock-3', '#d97706', '#fef3c7'],
            ['Closed tasks', $metrics['closedTasks'], 'circle-check-big', '#059669', '#d1fae5'],
            ['Overdue tasks', $metrics['overdueTasks'], 'triangle-alert', '#e11d48', '#ffe4e6'],
            ['Service jobs today', $metrics['serviceJobsToday'], 'wrench', '#2563eb', '#dbeafe'],
            ['Services in progress', $metrics['serviceJobsInProgress'], 'route', '#7c3aed', '#ede9fe'],
        ];
    @endphp
    <div class="row g-3 mb-4">
        @foreach($cards as [$label, $value, $icon, $color, $soft])
            <div class="col-6 col-xl-3"><div class="nx-card nx-metric" style="--metric:{{ $color }};--metric-soft:{{ $soft }}"><div><div class="nx-metric-value">{{ $value }}</div><div class="nx-metric-label">{{ $label }}</div></div><span class="nx-metric-icon"><i data-lucide="{{ $icon }}"></i></span></div></div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-8"><section class="nx-card p-4 h-100"><div class="d-flex justify-content-between align-items-center mb-3"><div><h3 class="h6 fw-bold mb-1">Attendance trend</h3><div class="small text-secondary">Daily check-ins for the past week</div></div><span class="badge badge-soft-primary rounded-pill px-3 py-2">Last 7 days</span></div><div id="attendanceChart" style="min-height:290px"></div></section></div>
        <div class="col-xl-4"><section class="nx-card p-4 h-100"><div class="mb-3"><h3 class="h6 fw-bold mb-1">Task status</h3><div class="small text-secondary">Current workflow distribution</div></div><div id="taskChart" style="min-height:290px"></div></section></div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8"><section class="nx-card overflow-hidden"><div class="p-4 pb-2 d-flex justify-content-between"><div><h3 class="h6 fw-bold mb-1">Recent tasks</h3><div class="small text-secondary">Latest work assigned to the field team</div></div><a href="#" class="small fw-semibold">View all</a></div><div class="table-responsive"><table class="table nx-table mb-0"><thead><tr><th class="ps-4">Task</th><th>Customer</th><th>Assignee</th><th>Due</th><th>Status</th></tr></thead><tbody>@forelse($recentTasks as $task)<tr><td class="ps-4"><div class="fw-semibold">{{ $task->title }}</div><div class="small text-secondary">{{ $task->task_no }}</div></td><td>{{ $task->customer?->name ?? '—' }}</td><td>{{ $task->assignee?->name }}</td><td class="small {{ $task->due_at?->isPast() && $task->status === 'pending' ? 'text-danger' : '' }}">{{ $task->due_at?->format('d M, h:i A') ?? '—' }}</td><td><span class="badge rounded-pill {{ $task->status === 'closed' ? 'badge-soft-success' : 'badge-soft-warning' }}">{{ ucfirst($task->status) }}</span></td></tr>@empty<tr><td colspan="5" class="text-center text-secondary py-5">No tasks yet</td></tr>@endforelse</tbody></table></div></section></div>
        <div class="col-xl-4"><section class="nx-card p-4 h-100"><h3 class="h6 fw-bold mb-3">Quick actions</h3><div class="d-grid gap-2"><a href="{{ route('service-jobs.create') }}" class="btn btn-primary text-start p-3 d-flex align-items-center gap-3"><i data-lucide="wrench"></i><span><span class="d-block">Create service job</span><small class="fw-normal opacity-75">Assign a customer visit</small></span></a><a href="{{ route('customers.create') }}" class="btn btn-light border text-start p-3 d-flex align-items-center gap-3"><i data-lucide="building-2"></i><span><span class="d-block">Add customer</span><small class="fw-normal text-secondary">Start the 7-step wizard</small></span></a><a href="{{ route('tasks.index') }}" class="btn btn-light border text-start p-3 d-flex align-items-center gap-3"><i data-lucide="list-plus"></i><span><span class="d-block">Create workflow task</span><small class="fw-normal text-secondary">Assign internal work</small></span></a></div></section></div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            new ApexCharts(document.querySelector('#attendanceChart'), {chart:{type:'area',height:290,toolbar:{show:false},fontFamily:'Inter'},series:[{name:'Check-ins',data:@json($attendanceTrend->pluck('count'))}],xaxis:{categories:@json($attendanceTrend->pluck('date')),axisBorder:{show:false},axisTicks:{show:false}},yaxis:{min:0,forceNiceScale:true},stroke:{curve:'smooth',width:3},colors:['#2563eb'],fill:{type:'gradient',gradient:{shadeIntensity:1,opacityFrom:.32,opacityTo:.03}},grid:{borderColor:'#eef2f7',strokeDashArray:4},dataLabels:{enabled:false}}).render();
            new ApexCharts(document.querySelector('#taskChart'), {chart:{type:'donut',height:290,fontFamily:'Inter'},series:[{{ $metrics['pendingTasks'] }},{{ $metrics['closedTasks'] }}],labels:['Pending','Closed'],colors:['#f59e0b','#16a34a'],legend:{position:'bottom'},plotOptions:{pie:{donut:{size:'70%',labels:{show:true,total:{show:true,label:'Total'}}}}},dataLabels:{enabled:false}}).render();
        });
    </script>
    @endpush
</x-app-layout>
