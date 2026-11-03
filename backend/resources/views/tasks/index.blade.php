<x-app-layout>
    @php
        $moduleRoute = 'workflows';
        $moduleTitle = 'Internal Workflow';
    @endphp
    <x-slot name="title">{{ $moduleTitle }}</x-slot>
    <x-slot name="pageTitle">{{ $moduleTitle }}</x-slot>
    <x-slot name="breadcrumb">Operations / {{ $moduleTitle }}</x-slot>

    <div class="nx-page-header">
        <div>
            <div class="nx-page-kicker">Team workflows</div>
            <h2>Internal workflows</h2>
            <p>Assign work, create linked subtasks and follow every action in the timeline.</p>
        </div>
        <div class="nx-page-actions">
            <a class="btn btn-light border" href="{{ route($moduleRoute.'.board') }}"><i data-lucide="square-kanban"></i>Kanban board</a>
            @if(auth()->user()->can('tasks.create') || auth()->user()->can('tasks.assign'))<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#taskModal"><i data-lucide="plus"></i>New workflow</button>@endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-sm-4"><x-kpi label="Pending" :value="$openCount" icon="hourglass" tone="warning" :href="route($moduleRoute.'.index', ['scope' => 'open'])" :active="request('scope') === 'open'" /></div>
        <div class="col-6 col-sm-4"><x-kpi label="Completed" :value="$closedCount" icon="circle-check-big" tone="success" :href="route($moduleRoute.'.index', ['status' => 'closed'])" :active="request('status') === 'closed'" /></div>
        <div class="col-12 col-sm-4"><x-kpi label="Overdue" :value="$overdueCount" icon="alarm-clock" tone="danger" :href="route($moduleRoute.'.index', ['status' => 'overdue'])" :active="request('status') === 'overdue'" /></div>
    </div>

    @if($errors->any())<div class="alert alert-danger d-flex align-items-center gap-2"><i data-lucide="circle-alert" style="width:18px"></i>{{ $errors->first() }}</div>@endif

    <section class="nx-card nx-datatable-card">
        <div class="nx-datatable-filters">
            <nav class="nx-filter-pills" aria-label="Status">
                <a class="{{ !request()->hasAny(['scope','status']) ? 'active' : '' }}" href="{{ route($moduleRoute.'.index') }}">All<span class="nx-count notranslate">{{ $openCount + $closedCount }}</span></a>
                <a class="{{ request('scope') === 'open' ? 'active' : '' }}" href="{{ route($moduleRoute.'.index', ['scope' => 'open']) }}">Pending<span class="nx-count notranslate">{{ $openCount }}</span></a>
                <a class="{{ request('status') === 'closed' ? 'active' : '' }}" href="{{ route($moduleRoute.'.index', ['status' => 'closed']) }}">Completed<span class="nx-count notranslate">{{ $closedCount }}</span></a>
                <a class="{{ request('status') === 'overdue' ? 'active' : '' }}" href="{{ route($moduleRoute.'.index', ['status' => 'overdue']) }}">Overdue<span class="nx-count notranslate">{{ $overdueCount }}</span></a>
            </nav>
        </div>
        <form class="nx-datatable-filters" method="GET" action="{{ route($moduleRoute.'.index') }}">
            @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            @if(request('scope'))<input type="hidden" name="scope" value="{{ request('scope') }}">@endif
            <div style="min-width:170px"><label class="form-label" for="fPriority">Priority</label><select id="fPriority" class="form-select" name="priority"><option value="">All priorities</option>@foreach(['normal','high','very_high'] as $priority)<option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ str($priority)->headline() }}</option>@endforeach</select></div>
            <div style="min-width:200px"><label class="form-label" for="fAssignee">Responsible person</label><select id="fAssignee" class="form-select" name="assignee"><option value="">All assignees</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(request('assignee') == $user->id)>{{ $user->name }}</option>@endforeach</select></div>
            <div class="d-flex gap-2"><button class="btn btn-primary btn-sm" style="min-height:34px"><i data-lucide="filter"></i>Apply</button>@if(request()->hasAny(['search','type','priority','assignee','scope','status']))<a class="btn btn-light border btn-sm" style="min-height:34px" href="{{ route($moduleRoute.'.index') }}">Reset</a>@endif</div>
        </form>

        <div>
            <table class="table nx-table nx-workflow-table mb-0" data-datatable data-dt-title="Internal workflows" data-dt-order="[]">
                <thead><tr><th class="ps-4" data-priority="1">Work item</th><th data-priority="3">Responsible person</th><th class="no-sort" data-orderable="false" data-priority="7">Progress</th><th class="dt-left" data-priority="4">Due / follow-up</th><th data-priority="6">Latest activity</th><th data-priority="2">Status</th><th class="pe-4 no-export no-sort" data-orderable="false" data-priority="2" style="width:52px"></th></tr></thead>
                <tbody>
                    @forelse($tasks as $task)
                        @php
                            $isOpen = $task->status !== 'closed';
                            $isOverdue = $isOpen && $task->due_at?->isPast();
                            $priorityClass = $task->priority === 'very_high' ? 'badge-soft-danger' : ($task->priority === 'high' ? 'badge-soft-warning' : 'badge-soft-secondary');
                        @endphp
                        <tr class="{{ $isOverdue ? 'nx-overdue-row' : '' }} {{ $task->parent_task_id ? 'nx-subtask-row' : '' }}">
                            <td class="ps-4" style="min-width:240px"><div class="d-flex align-items-start gap-2"><span class="nx-task-type-icon workflow"><i data-lucide="git-branch"></i></span><div class="min-w-0"><div class="d-flex flex-wrap align-items-center gap-2 mb-1"><span class="nx-code">{{ $task->task_no }}</span>@if($task->parent_task_id)<span class="badge rounded-pill badge-soft-purple">Subtask</span>@endif<span class="badge rounded-pill {{ $priorityClass }}">{{ str($task->priority)->headline() }}</span></div><a class="nx-row-title" href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>@if($task->parent)<a class="nx-parent-link d-block small mt-1" href="{{ route('tasks.show', $task->parent) }}"><i data-lucide="corner-down-right" style="width:14px"></i> {{ $task->parent->task_no }} · {{ $task->parent->title }}</a>@endif
                                @if($task->subtasks_count)<span class="small text-secondary d-block mt-1">{{ $task->completed_subtasks_count }}/{{ $task->subtasks_count }} subtasks completed</span>@endif
                                @if($task->observers->isNotEmpty())<div class="nx-avatar-stack mt-2" aria-label="Observers">@foreach($task->observers->take(4) as $member)@if($member->user)<x-person-avatar :user="$member->user" :size="26" detail="Observer" />@endif @endforeach @if($task->observers->count() > 4)<span class="nx-avatar-more" title="{{ $task->observers->skip(4)->map(fn ($member) => $member->user?->name)->filter()->implode(', ') }}">+{{ $task->observers->count() - 4 }}</span>@endif</div>@endif
                            </div></div></td>
                            <td><div class="d-flex align-items-center gap-2"><x-person-avatar :user="$task->assignee" :size="34" detail="Responsible Person" /><div class="min-w-0"><span class="small fw-semibold d-block text-nowrap">{{ $task->assignee?->name ?? 'Unassigned' }}</span><small class="text-secondary d-block text-truncate" style="max-width:170px">{{ $task->assignee?->designation ?: 'Responsible Person' }}</small></div></div></td>
                            <td><div style="max-width:192px"><div class="nx-flow-track"><span class="done"></span><span class="done"></span><span class="{{ $isOpen ? 'active' : 'done' }}"></span><span class="{{ $isOpen ? '' : 'done' }}"></span></div><div class="nx-flow-label row row-cols-4 g-0 flex-nowrap mt-2"><span>Created</span><span>Assigned</span><span>Pending</span><span>Completed</span></div></div></td>
                            <td class="dt-left" data-order="{{ $task->due_at?->timestamp ?? 9999999999 }}"><span class="small {{ $isOverdue ? 'text-danger fw-bold' : 'fw-semibold' }}">{{ $task->due_at?->format('d M Y') ?? 'No due date' }}</span><small class="d-block {{ $isOverdue ? 'text-danger' : 'text-secondary' }}">{{ $isOverdue ? $task->due_at->diffForHumans() : ($task->due_at?->format('h:i A') ?? 'Flexible') }}</small>@if($task->next_follow_up_at)<small class="d-block text-primary mt-1">Follow-up: {{ $task->next_follow_up_at->format('d M, h:i A') }}</small>@endif @if($task->revised_due_at)<small class="d-block text-secondary">Revised: {{ $task->revised_due_at->format('d M, h:i A') }}</small>@endif</td>
                            <td>@if($task->latestAction)<div class="small fw-semibold">{{ str($task->latestAction->action)->headline() }} by {{ $task->latestAction->user?->name }}</div><div class="small text-secondary text-truncate" style="max-width:180px" title="{{ $task->latestAction->remark }}">{{ $task->latestAction->remark }}</div><small class="text-secondary">{{ $task->latestAction->created_at->diffForHumans() }}</small>@else<span class="small text-secondary">No activity yet</span>@endif</td>
                            <td><span class="badge rounded-pill {{ $isOpen ? ($isOverdue ? 'badge-soft-danger' : 'badge-soft-warning') : 'badge-soft-success' }}">{{ $isOpen ? ($isOverdue ? 'Overdue' : 'Pending') : 'Completed' }}</span></td>
                            <td class="ps-0 pe-4"><a class="nx-icon-btn" href="{{ route('tasks.show', $task) }}" aria-label="Open {{ $task->task_no }}"><i data-lucide="arrow-right"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="text-center py-5"><span class="nx-empty-icon mx-auto mb-3"><i data-lucide="list-checks"></i></span><div class="fw-bold">No work items match this view</div><div class="small text-secondary mt-1">Change the filters or create a new item.</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @include('tasks.partials.create-modal')
</x-app-layout>
