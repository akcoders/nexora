<x-app-layout>
    <x-slot name="title">Employees</x-slot>
    <x-slot name="pageTitle">Employee master</x-slot>
    <x-slot name="breadcrumb">HR & people / Employees</x-slot>

    <div class="nx-page-header">
        <div><div class="nx-page-kicker">HR & people</div><h2>People directory</h2><p>Employee records, system access, premises and payroll readiness.</p></div>
        @can('employees.create')<div class="nx-page-actions"><a href="{{ route('employees.create') }}" class="btn btn-primary"><i data-lucide="user-plus"></i>Add employee</a></div>@endcan
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-kpi label="Employees" :value="$metrics['total']" icon="users" tone="primary" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Active" :value="$metrics['active']" icon="badge-check" tone="success" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="On probation" :value="$metrics['probation']" icon="timer" tone="warning" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Payroll ready" :value="$metrics['payroll']" icon="wallet-cards" tone="purple" /></div>
    </div>

    <section class="nx-card nx-datatable-card">
        <form class="nx-datatable-filters" method="GET">
            <div style="min-width:200px"><label class="form-label" for="eDept">Department</label><select class="form-select" id="eDept" name="department" onchange="this.form.submit()"><option value="">All departments</option>@foreach($departments as $department)<option @selected(request('department') === $department)>{{ $department }}</option>@endforeach</select></div>
            <div><label class="form-label" for="eStatus">Account status</label><select class="form-select" id="eStatus" name="status" onchange="this.form.submit()"><option value="">All</option>@foreach(['active', 'inactive', 'on_leave', 'suspended'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>@endforeach</select></div>
            @if(request()->hasAny(['department', 'status', 'search']))<a class="btn btn-light border btn-sm" style="min-height:34px" href="{{ route('employees.index') }}">Clear filters</a>@endif
        </form>
        <table class="table nx-table" data-datatable data-dt-title="Employees" data-dt-empty="No employees found.">
            <thead><tr><th data-priority="1">Employee</th><th data-priority="3">Code</th><th data-priority="5">Designation</th><th data-priority="6">Role</th><th data-priority="8">Premises</th><th data-priority="7">Joining</th><th data-priority="4">Account</th><th data-priority="2">Employment</th><th class="all no-export no-sort text-end" data-orderable="false"></th></tr></thead>
            <tbody>
                @foreach($employees as $employee)
                    @php $employment = $employee->employeeProfile?->employmentStatus(); @endphp
                    <tr>
                        <td style="min-width:220px"><div class="d-flex align-items-center gap-2">@if($employee->employeeProfile?->profile_picture_path)<img class="rounded-circle object-fit-cover flex-shrink-0" width="32" height="32" src="{{ asset('storage/'.$employee->employeeProfile->profile_picture_path) }}" alt="">@else<span class="nx-avatar notranslate" style="width:32px;height:32px;font-size:12px">{{ str($employee->name)->substr(0, 1)->upper() }}</span>@endif<div class="min-w-0"><a class="nx-row-title notranslate" href="{{ route('employees.show', $employee) }}">{{ $employee->name }}</a> <span class="nx-row-sub notranslate">{{ $employee->email }}</span></div></div></td>
                        <td class="nx-code text-nowrap notranslate">{{ $employee->employee_code }}</td>
                        <td>{{ $employee->designation ?: '—' }} <span class="nx-row-sub">{{ $employee->department ?: '—' }}</span></td>
                        <td class="text-nowrap">{{ $employee->getRoleNames()->join(', ') }}</td>
                        <td class="notranslate">{{ $employee->premises->count() }}</td>
                        <td class="text-nowrap" data-order="{{ $employee->employeeProfile?->date_of_joining?->timestamp }}">{{ $employee->employeeProfile?->date_of_joining?->format('d M Y') ?? '—' }}</td>
                        <td class="text-nowrap"><x-pill :tone="['active' => 'success', 'inactive' => 'secondary', 'on_leave' => 'warning', 'suspended' => 'danger'][$employee->status] ?? 'secondary'" :label="str($employee->status)->headline()" /></td>
                        <td>@if($employment)<x-pill :tone="$employment['tone']" :label="$employment['label']" :title="$employment['detail']" />@if($employment['detail']) <span class="nx-row-sub text-nowrap mt-1">{{ str($employment['detail'])->before(' · ') }}</span>@endif @else — @endif</td>
                        <td class="text-end text-nowrap"><div class="d-inline-flex gap-1">@can('employees.update')<a class="btn btn-sm btn-light border btn-icon" href="{{ route('employees.edit', $employee) }}" aria-label="Edit {{ $employee->name }}" title="Edit"><i data-lucide="pencil"></i></a>@endcan<a class="btn btn-sm btn-light border btn-icon" href="{{ route('employees.show', $employee) }}" aria-label="Open {{ $employee->name }}" title="Open"><i data-lucide="arrow-right"></i></a></div></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
</x-app-layout>
