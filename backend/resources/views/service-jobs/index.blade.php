<x-app-layout>
    <x-slot name="title">Service Jobs</x-slot>
    <x-slot name="pageTitle">Service management</x-slot>
    <x-slot name="breadcrumb">Operations / Service Jobs</x-slot>

    <div class="nx-page-header">
        <div><div class="nx-page-kicker">Service & catalog</div><h2>Field service jobs</h2><p>Mobile-app service jobs from assignment through inspection, payment and completion.</p></div>
        @can('create', App\Models\ServiceJob::class)<div class="nx-page-actions"><a href="{{ route('service-jobs.create') }}" class="btn btn-primary"><i data-lucide="plus"></i>New service job</a></div>@endcan
    </div>

    @php
        $cards = [
            ['Today', $metrics['today'], 'calendar-days', 'primary'],
            ['Open jobs', $metrics['pending'], 'clipboard-clock', 'warning'],
            ['In progress', $metrics['in_progress'], 'wrench', 'purple'],
            ['Completed', $metrics['completed'], 'circle-check-big', 'success'],
            ['Payment pending', $metrics['payment_pending'], 'indian-rupee', 'danger'],
        ];
    @endphp
    <div class="row g-3 mb-4">
        @foreach($cards as [$label, $value, $icon, $tone])
            <div class="{{ $loop->last ? 'col-12' : 'col-6' }} col-lg"><x-kpi :label="$label" :value="$value" :icon="$icon" :tone="$tone" /></div>
        @endforeach
    </div>

    <section class="nx-card nx-datatable-card">
        <form class="nx-datatable-filters" method="GET">
            <div style="flex:1 1 260px"><label class="form-label" for="sjSearch">Search</label><input class="form-control" id="sjSearch" name="search" value="{{ request('search') }}" placeholder="Job no, customer or complaint"></div>
            <div style="min-width:170px"><label class="form-label" for="sjStatus">Status</label><select class="form-select" id="sjStatus" name="status"><option value="">All statuses</option>@foreach($statusLabels as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select></div>
            <div style="min-width:170px"><label class="form-label" for="sjPriority">Priority</label><select class="form-select" id="sjPriority" name="priority"><option value="">All priorities</option>@foreach(['normal','high','very_high','emergency'] as $priority)<option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ str($priority)->headline() }}</option>@endforeach</select></div>
            <div style="min-width:170px"><label class="form-label" for="sjTechnician">Technician</label><select class="form-select" id="sjTechnician" name="technician"><option value="">All technicians</option>@foreach($technicians as $technician)<option value="{{ $technician->id }}" @selected((string) request('technician') === (string) $technician->id)>{{ $technician->name }}</option>@endforeach</select></div>
            <div class="d-flex gap-2"><button class="btn btn-primary btn-sm" style="min-height:34px"><i data-lucide="filter"></i>Apply</button>@if(request()->anyFilled(['search', 'status', 'priority', 'technician']))<a class="btn btn-light border btn-sm" style="min-height:34px" href="{{ route('service-jobs.index') }}">Reset</a>@endif</div>
        </form>
        <table data-datatable data-dt-title="Service jobs" data-dt-search="false" class="table nx-table align-middle mb-0">
            <thead><tr><th class="ps-4" data-priority="1">Job</th><th>Customer & complaint</th><th>Schedule</th><th>Technician</th><th data-priority="2">Status</th><th class="text-end pe-4 no-export no-sort" data-orderable="false">Action</th></tr></thead>
            <tbody>
                @forelse($jobs as $job)
                    @php
                        $statusTone = match($job->status) { 'completed','paid','customer_approved' => 'success', 'cancelled','customer_declined' => 'danger', 'on_the_way','arrived','inspection_in_progress','service_in_progress' => 'primary', default => 'warning' };
                    @endphp
                    <tr>
                        <td class="ps-4"><a href="{{ route('service-jobs.show', $job) }}" class="nx-row-title nx-code notranslate">{{ $job->job_no }}</a><span class="nx-row-sub">{{ str($job->priority)->headline() }} priority</span></td>
                        <td style="min-width:260px"><span class="nx-row-title notranslate">{{ $job->customer->name }}</span><span class="nx-row-sub text-truncate" style="max-width:330px">{{ $job->complaint }}</span><span class="nx-tag mt-1">{{ $job->serviceType?->name ?? 'General service' }}</span></td>
                        <td><div class="small fw-semibold">{{ $job->scheduled_at?->format('d M Y') ?? 'Not scheduled' }}</div><div class="small text-secondary">{{ $job->scheduled_at?->format('h:i A') }}</div></td>
                        <td>{{ $job->technician->name }}</td>
                        <td><x-pill :tone="$statusTone" :label="$workflow->label($job->status)" /></td>
                        <td class="text-end pe-4"><a href="{{ route('service-jobs.show', $job) }}" class="btn btn-sm btn-light border">Open<i data-lucide="arrow-right"></i></a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-5"><i data-lucide="clipboard-x" class="text-secondary mb-2"></i><div class="fw-semibold">No service jobs found</div><div class="small text-secondary">Create the first job or change the filters.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</x-app-layout>
