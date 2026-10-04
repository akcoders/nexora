<x-app-layout>
    <x-slot name="title">Service Jobs</x-slot>
    <x-slot name="pageTitle">Service management</x-slot>
    <x-slot name="breadcrumb">Operations / Service Jobs</x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><h2 class="h5 mb-1">Field service jobs</h2><div class="small text-secondary">Track every visit from assignment through inspection, payment and completion.</div></div>
        @can('create', App\Models\ServiceJob::class)<a href="{{ route('service-jobs.create') }}" class="btn btn-primary"><i data-lucide="plus" style="width:17px"></i>New service job</a>@endcan
    </div>

    @php
        $cards = [
            ['Today', $metrics['today'], 'calendar-days', '#2563eb', '#dbeafe'],
            ['Open jobs', $metrics['pending'], 'clipboard-clock', '#d97706', '#fef3c7'],
            ['In progress', $metrics['in_progress'], 'wrench', '#7c3aed', '#ede9fe'],
            ['Completed', $metrics['completed'], 'circle-check-big', '#059669', '#d1fae5'],
            ['Payment pending', $metrics['payment_pending'], 'indian-rupee', '#dc2626', '#fee2e2'],
        ];
    @endphp
    <div class="row g-3 mb-4">
        @foreach($cards as [$label, $value, $icon, $color, $soft])
            <div class="col-6 col-lg"><div class="nx-card nx-metric" style="--metric:{{ $color }};--metric-soft:{{ $soft }}"><div><div class="nx-metric-value">{{ $value }}</div><div class="nx-metric-label">{{ $label }}</div></div><span class="nx-metric-icon"><i data-lucide="{{ $icon }}"></i></span></div></div>
        @endforeach
    </div>

    <section class="nx-card overflow-hidden">
        <form class="p-3 p-lg-4 border-bottom" method="GET">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4"><label class="form-label">Search</label><input class="form-control" name="search" value="{{ request('search') }}" placeholder="Job no, customer or complaint"></div>
                <div class="col-md-4 col-lg-2"><label class="form-label">Status</label><select class="form-select" name="status"><option value="">All statuses</option>@foreach($statusLabels as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-4 col-lg-2"><label class="form-label">Priority</label><select class="form-select" name="priority"><option value="">All priorities</option>@foreach(['normal','high','very_high','emergency'] as $priority)<option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ str($priority)->headline() }}</option>@endforeach</select></div>
                <div class="col-md-4 col-lg-2"><label class="form-label">Technician</label><select class="form-select" name="technician"><option value="">All technicians</option>@foreach($technicians as $technician)<option value="{{ $technician->id }}" @selected((string) request('technician') === (string) $technician->id)>{{ $technician->name }}</option>@endforeach</select></div>
                <div class="col-lg-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1">Filter</button><a class="btn btn-light border" href="{{ route('service-jobs.index') }}" title="Reset"><i data-lucide="rotate-ccw" style="width:16px"></i></a></div>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table nx-table align-middle mb-0">
                <thead><tr><th class="ps-4">Job</th><th>Customer & complaint</th><th>Schedule</th><th>Technician</th><th>Status</th><th class="text-end pe-4">Action</th></tr></thead>
                <tbody>
                    @forelse($jobs as $job)
                        @php
                            $statusClass = match($job->status) { 'completed','paid','customer_approved' => 'badge-soft-success', 'cancelled','customer_declined' => 'badge-soft-danger', 'on_the_way','arrived','inspection_in_progress','service_in_progress' => 'badge-soft-primary', default => 'badge-soft-warning' };
                        @endphp
                        <tr>
                            <td class="ps-4"><div class="fw-bold text-primary">{{ $job->job_no }}</div><div class="small text-secondary">{{ str($job->priority)->headline() }} priority</div></td>
                            <td style="min-width:260px"><div class="fw-semibold">{{ $job->customer->name }}</div><div class="small text-secondary text-truncate" style="max-width:330px">{{ $job->complaint }}</div><div class="small text-secondary">{{ $job->serviceType?->name ?? 'General service' }}</div></td>
                            <td><div class="small fw-semibold">{{ $job->scheduled_at?->format('d M Y') ?? 'Not scheduled' }}</div><div class="small text-secondary">{{ $job->scheduled_at?->format('h:i A') }}</div></td>
                            <td>{{ $job->technician->name }}</td>
                            <td><span class="badge rounded-pill {{ $statusClass }}">{{ $workflow->label($job->status) }}</span></td>
                            <td class="text-end pe-4"><a href="{{ route('service-jobs.show', $job) }}" class="btn btn-sm btn-light border">Open<i data-lucide="arrow-right" style="width:15px"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-5"><i data-lucide="clipboard-x" class="text-secondary mb-2"></i><div class="fw-semibold">No service jobs found</div><div class="small text-secondary">Create the first job or change the filters.</div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($jobs->hasPages())<div class="p-3 border-top">{{ $jobs->links() }}</div>@endif
    </section>
</x-app-layout>
