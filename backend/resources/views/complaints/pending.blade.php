@use('App\Services\ComplaintOptions')
<x-app-layout>
    <x-slot name="title">Pending management</x-slot>
    <x-slot name="pageTitle">Pending management</x-slot>
    <x-slot name="breadcrumb">Complaints / Pending management</x-slot>
    @php
        $query = fn (array $changes = []) => array_filter(array_merge(request()->only(['group', 'owner', 'bucket', 'responsible', 'follow_up']), $changes), fn ($value) => $value !== null && $value !== '');
        $total = max(1, $all->count());
        $today = today();
    @endphp

    <div class="nx-page-header">
        <div>
            <div class="nx-page-kicker">Complaints</div>
            <h2>Pending management</h2>
            <p>Every pending call with its reason, owner, next action and age. Nothing should stay pending without a plan.</p>
        </div>
        <div class="nx-page-actions">
            <a class="btn btn-light border" href="{{ route('complaints.index', ['status' => 'pending']) }}"><i data-lucide="clipboard-list"></i>Open in register</a>
            <a class="btn btn-light border" href="{{ route('reports.builder', ['dataset' => 'complaints', 'group_by' => 'pending_reason', 'chart' => 'hbar']) }}"><i data-lucide="chart-bar"></i>Analyse in report builder</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-kpi label="Pending & observation" :value="$all->count()" icon="hourglass" tone="danger" :href="route('complaints.pending')" :active="! request()->hasAny(['group', 'owner', 'bucket', 'responsible', 'follow_up'])" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Follow-ups due (today & overdue)" :value="$followUpsToday" icon="phone-forwarded" tone="warning" :href="route('complaints.pending', ['follow_up' => 'overdue'])" :active="($filters['follow_up'] ?? null) === 'overdue'" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Over 9 days old" :value="($buckets['management']['count'] ?? 0) + ($buckets['critical']['count'] ?? 0)" icon="siren" tone="dark" :href="route('complaints.pending', ['bucket' => 'management'])" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Missing reason or follow-up" :value="$withoutPlan" icon="circle-help" :tone="$withoutPlan ? 'orange' : 'success'" :foot="$withoutPlan ? 'Update these first' : 'Every pending call has a plan'" /></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-5">
            <section class="nx-card h-100">
                <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="timer"></i>Pending age</h3><span class="small text-secondary">Click a band to filter</span></div>
                <div class="nx-card-body">
                    <div class="nx-age-bars">
                        @foreach($buckets as $key => $bucket)
                            <a class="nx-age-bar tone-{{ $bucket['tone'] }} {{ ($filters['bucket'] ?? null) === $key ? 'fw-bold' : '' }}" href="{{ route('complaints.pending', $query(['bucket' => ($filters['bucket'] ?? null) === $key ? null : $key])) }}">
                                <span><x-pill :tone="$bucket['tone']" :label="$bucket['label']" /> <small class="text-secondary">{{ $bucket['max'] === null ? 'over '.($bucket['min'] - 1) : $bucket['min'].'–'.$bucket['max'] }} days</small></span>
                                <span class="nx-age-track"><span class="nx-age-fill" style="width: {{ round($bucket['count'] / $total * 100) }}%"></span></span>
                                <strong class="notranslate">{{ $bucket['count'] }}</strong>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>
        <div class="col-md-6 col-xl-3">
            <section class="nx-card h-100">
                <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="layers"></i>By pending group</h3></div>
                <div class="nx-card-body py-2">
                    <ul class="nx-mini-list">
                        @foreach(ComplaintOptions::options('pending_groups') as $key => $label)
                            <li><a class="{{ ($filters['group'] ?? null) === $key ? 'fw-bold' : '' }}" href="{{ route('complaints.pending', $query(['group' => ($filters['group'] ?? null) === $key ? null : $key])) }}">{{ $label }}</a><span class="nx-count notranslate">{{ (int) ($groupCounts[$key] ?? 0) }}</span></li>
                        @endforeach
                    </ul>
                </div>
            </section>
        </div>
        <div class="col-md-6 col-xl-4">
            <section class="nx-card h-100">
                <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="user-round-cog"></i>By pending owner</h3></div>
                <div class="nx-card-body py-2">
                    <ul class="nx-mini-list" style="columns:2;column-gap:32px;column-rule:1px solid #f0f3f7">
                        @foreach(ComplaintOptions::options('pending_owners') as $key => $label)
                            <li style="break-inside:avoid"><a class="{{ ($filters['owner'] ?? null) === $key ? 'fw-bold' : '' }}" href="{{ route('complaints.pending', $query(['owner' => ($filters['owner'] ?? null) === $key ? null : $key])) }}">{{ $label }}</a><span class="nx-count notranslate">{{ (int) ($ownerCounts[$key] ?? 0) }}</span></li>
                        @endforeach
                    </ul>
                </div>
            </section>
        </div>
    </div>

    <section class="nx-card nx-datatable-card">
        <div class="nx-datatable-filters">
            <nav class="nx-filter-pills" aria-label="Follow-up">
                <a class="{{ empty($filters['follow_up']) ? 'active' : '' }}" href="{{ route('complaints.pending', $query(['follow_up' => null])) }}">All follow-ups</a>
                <a class="{{ ($filters['follow_up'] ?? null) === 'today' ? 'active' : '' }}" href="{{ route('complaints.pending', $query(['follow_up' => 'today'])) }}">Due today</a>
                <a class="{{ ($filters['follow_up'] ?? null) === 'overdue' ? 'active' : '' }}" href="{{ route('complaints.pending', $query(['follow_up' => 'overdue'])) }}">Overdue</a>
                <a class="{{ ($filters['follow_up'] ?? null) === 'upcoming' ? 'active' : '' }}" href="{{ route('complaints.pending', $query(['follow_up' => 'upcoming'])) }}">Upcoming</a>
            </nav>
            <form class="d-flex flex-wrap align-items-end gap-2 ms-auto" method="GET">
                @foreach(['group', 'owner', 'bucket', 'follow_up'] as $keep)@if(!empty($filters[$keep]))<input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">@endif @endforeach
                <div style="min-width:200px"><label class="form-label" for="pResp">Responsible person</label><select id="pResp" class="form-select" name="responsible" onchange="this.form.submit()"><option value="">Anyone</option>@foreach($users as $person)<option value="{{ $person->id }}" @selected((int) ($filters['responsible'] ?? 0) === $person->id)>{{ $person->name }}</option>@endforeach</select></div>
                @if(request()->hasAny(['group', 'owner', 'bucket', 'responsible', 'follow_up']))<a class="btn btn-light border btn-sm" style="min-height:34px" href="{{ route('complaints.pending') }}">Clear filters</a>@endif
            </form>
        </div>
        <table class="table nx-table" data-datatable data-dt-title="Pending complaints" data-dt-order='[[4,"desc"]]' data-dt-empty="No pending complaints. Great work!">
            <thead>
                <tr>
                    <th data-priority="1">Complaint no</th>
                    <th data-priority="2">Customer</th>
                    <th data-priority="9">Problem</th>
                    <th data-priority="6">Reason for pending</th>
                    <th data-priority="3" data-class-name="dt-left">Age</th>
                    <th>Pending owner</th>
                    <th>Responsible</th>
                    <th data-priority="5">Next action</th>
                    <th data-priority="4" data-class-name="dt-left">Follow-up</th>
                    <th data-class-name="dt-left">Expected material</th>
                    <th data-class-name="dt-left">Target date</th>
                    <th>Reason for delay</th>
                    <th class="no-export no-sort all text-end" data-orderable="false"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $complaint)
                    @php $bucket = $complaint->pendingBucket(); $age = $complaint->pendingAgeInDays(); $due = $complaint->follow_up_date; @endphp
                    <tr class="{{ $due && $due->lt($today) ? 'nx-overdue-row' : '' }}">
                        <td><a class="nx-row-title nx-code notranslate" href="{{ route('complaints.show', [$complaint, 'tab' => 'pending']) }}">{{ $complaint->complaint_no }}</a>@if($complaint->status === 'under_observation')<span class="badge badge-soft-purple mt-1">Observation</span>@endif</td>
                        <td style="min-width:170px"><span class="nx-row-title notranslate">{{ $complaint->customer?->name }}</span><span class="nx-row-sub">{{ $complaint->technician?->name }}</span></td>
                        <td style="min-width:160px">{{ $complaint->nature_of_complaint }}</td>
                        <td style="min-width:190px">@if($complaint->pending_reason)<span class="nx-row-title">{{ $complaint->pending_reason }}</span><span class="nx-row-sub">{{ collect([ComplaintOptions::label('pending_groups', $complaint->pending_group), $complaint->pending_sub_reason])->filter()->join(' · ') }}</span>@else<span class="badge badge-soft-orange">Reason missing</span>@endif</td>
                        <td data-order="{{ $age }}"><span class="badge badge-soft-{{ $bucket['tone'] }}">{{ $age }} days</span><span class="nx-row-sub">{{ $bucket['label'] }}</span></td>
                        <td>{{ ComplaintOptions::label('pending_owners', $complaint->pending_owner) ?? '—' }}</td>
                        <td>{{ $complaint->responsibleUser?->name ?? '—' }}</td>
                        <td style="min-width:180px">{{ $complaint->next_action ?? '—' }}</td>
                        <td data-order="{{ $due?->timestamp ?? 0 }}" class="text-nowrap {{ $due && $due->lte($today) ? 'text-danger fw-semibold' : '' }}">{{ $due?->format('d M Y') ?? 'Not set' }}@if($due && $due->isToday())<span class="nx-row-sub text-danger">Today</span>@elseif($due && $due->lt($today))<span class="nx-row-sub text-danger">{{ (int) $due->diffInDays($today) }}d overdue</span>@endif</td>
                        <td data-order="{{ $complaint->expected_material_date?->timestamp ?? 0 }}" class="text-nowrap">{{ $complaint->expected_material_date?->format('d M Y') ?? '—' }}</td>
                        <td data-order="{{ $complaint->target_date?->timestamp ?? 0 }}" class="text-nowrap {{ $complaint->target_date?->lt($today) ? 'text-danger' : '' }}">{{ $complaint->target_date?->format('d M Y') ?? '—' }}</td>
                        <td>{{ ComplaintOptions::label('delay_reasons', $complaint->delay_reason) ?? '—' }}</td>
                        <td class="text-end text-nowrap">
                            @can('update', $complaint)<button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#quickFollowUp" data-follow-url="{{ route('complaints.follow-ups.store', $complaint) }}" data-follow-no="{{ $complaint->complaint_no }}" data-follow-action="{{ $complaint->next_action }}" data-follow-target="{{ $complaint->target_date?->toDateString() }}" aria-label="Record follow-up"><i data-lucide="phone-forwarded"></i><span class="d-none d-md-inline">Follow-up</span></button>@endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <div class="modal fade" id="quickFollowUp" tabindex="-1" aria-labelledby="quickFollowTitle">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="#" data-quick-follow-form>
                @csrf
                <div class="modal-header"><div><h5 class="modal-title" id="quickFollowTitle">Record follow-up</h5><small class="text-secondary notranslate" data-quick-follow-no></small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">What happened? <span class="text-danger">*</span></label><textarea class="form-control" name="remarks" rows="3" required maxlength="2000"></textarea></div>
                        <div class="col-12"><label class="form-label">Next action</label><input class="form-control" name="next_action" maxlength="255" data-quick-follow-action></div>
                        <div class="col-md-6"><label class="form-label">Next follow-up date <span class="text-danger">*</span></label><input type="date" class="form-control" name="follow_up_date" min="{{ today()->toDateString() }}" value="{{ today()->addDay()->toDateString() }}" required></div>
                        <div class="col-md-6"><label class="form-label">Targeted closing date</label><input type="date" class="form-control" name="target_date" data-quick-follow-target data-v="after_or_equal:follow_up_date"></div>
                        <div class="col-md-6"><label class="form-label">Expected material date</label><input type="date" class="form-control" name="expected_material_date"></div>
                        <div class="col-md-6"><label class="form-label">Reason for delay</label><select class="form-select" name="delay_reason"><option value="">Keep current</option>@foreach(ComplaintOptions::options('delay_reasons') as $value => $text)<option value="{{ $value }}">{{ $text }}</option>@endforeach</select></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i data-lucide="save"></i>Save follow-up</button></div>
            </form>
        </div>
    </div>
    @push('scripts')@vite('resources/js/complaints.js')@endpush
</x-app-layout>
