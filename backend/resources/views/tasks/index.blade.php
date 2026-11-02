<x-app-layout>
    @php
        $moduleRoute = 'workflows';
        $moduleTitle = 'Internal Workflow';
    @endphp
    <x-slot name="title">{{ $moduleTitle }}</x-slot>
    <x-slot name="pageTitle">{{ $moduleTitle }}</x-slot>
    <x-slot name="breadcrumb">Operations / {{ $moduleTitle }}</x-slot>

    <section class="nx-workflow-hero mb-4">
        <div><div class="nx-overline text-info">TEAM WORKFLOWS</div>
            <h2 class="h4 fw-bold text-white mb-2">Internal workflows</h2>
            <p class="mb-0">Assign work, create linked subtasks and follow every action in the timeline.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-light" href="{{ route($moduleRoute.'.board') }}"><i data-lucide="square-kanban" style="width:17px"></i> Kanban board</a>
            @if(auth()->user()->can('tasks.create') || auth()->user()->can('tasks.assign'))<button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#taskModal"><i data-lucide="plus" style="width:17px"></i> New workflow</button>@endif
        </div>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-md-4"><a href="{{ route($moduleRoute.'.index', ['scope' => 'open']) }}" class="nx-card nx-metric text-dark"><div><div class="nx-metric-value">{{ $openCount }}</div><div class="nx-metric-label">Pending</div></div><span class="nx-metric-icon"><i data-lucide="activity"></i></span></a></div>
        <div class="col-md-4"><a href="{{ route($moduleRoute.'.index', ['status' => 'closed']) }}" class="nx-card nx-metric text-dark"><div><div class="nx-metric-value">{{ $closedCount }}</div><div class="nx-metric-label">Completed</div></div><span class="nx-metric-icon"><i data-lucide="circle-check-big"></i></span></a></div>
        <div class="col-md-4"><a href="{{ route($moduleRoute.'.index', ['status' => 'overdue']) }}" class="nx-card nx-metric text-dark"><div><div class="nx-metric-value">{{ $overdueCount }}</div><div class="nx-metric-label">Overdue</div></div><span class="nx-metric-icon"><i data-lucide="alarm-clock"></i></span></a></div>
    </div>

    @if($errors->any())<div class="alert alert-danger d-flex align-items-center gap-2"><i data-lucide="circle-alert" style="width:18px"></i>{{ $errors->first() }}</div>@endif

    <section class="nx-card mb-4">
        <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
            <nav class="nx-filter-pills" aria-label="Status">
                <a class="{{ !request()->hasAny(['scope','status']) ? 'active' : '' }}" href="{{ route($moduleRoute.'.index') }}">All</a>
                <a class="{{ request('scope') === 'open' ? 'active' : '' }}" href="{{ route($moduleRoute.'.index', ['scope' => 'open']) }}">Pending</a>
                <a class="{{ request('status') === 'closed' ? 'active' : '' }}" href="{{ route($moduleRoute.'.index', ['status' => 'closed']) }}">Completed</a>
                <a class="{{ request('status') === 'overdue' ? 'active' : '' }}" href="{{ route($moduleRoute.'.index', ['status' => 'overdue']) }}">Overdue</a>
            </nav>
            <span class="small text-secondary">{{ $tasks->count() }} results · {{ $overdueCount }} overdue</span>
        </div>
        <form class="p-3" method="GET" action="{{ route($moduleRoute.'.index') }}">
            @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            @if(request('scope'))<input type="hidden" name="scope" value="{{ request('scope') }}">@endif
            <div class="row g-2 align-items-center">
                <div class="col-md-6 small text-secondary">Use the search box above the table to find a workflow by number, title or person.</div>

                <div class="col-md-2"><select class="form-select" name="priority"><option value="">All priorities</option>@foreach(['normal','high','very_high'] as $priority)<option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ str($priority)->headline() }}</option>@endforeach</select></div>
                <div class="col-md-2"><select class="form-select" name="assignee"><option value="">All assignees</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(request('assignee') == $user->id)>{{ $user->name }}</option>@endforeach</select></div>
                <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-primary flex-grow-1">Apply</button>@if(request()->hasAny(['search','type','priority','assignee','scope','status']))<a class="btn btn-light border" href="{{ route($moduleRoute.'.index') }}" aria-label="Clear filters"><i data-lucide="x" style="width:16px"></i></a>@endif</div>
            </div>
        </form>
    </section>

    <section class="nx-card nx-datatable-card">
        <div>
            <table class="table nx-table nx-workflow-table mb-0" data-datatable data-dt-title="Internal workflows" data-dt-order="[]">
                <thead><tr><th class="ps-4" data-priority="1">Work item</th><th>Responsible Person</th><th class="no-sort" data-orderable="false">Progress</th><th>Original due / follow-up</th><th>Latest activity</th><th data-priority="2">Status</th><th class="pe-4 no-export no-sort" data-orderable="false"></th></tr></thead>
                <tbody>
                    @forelse($tasks as $task)
                        @php
                            $isOpen = $task->status !== 'closed';
                            $isOverdue = $isOpen && $task->due_at?->isPast();
                            $priorityClass = $task->priority === 'very_high' ? 'badge-soft-danger' : ($task->priority === 'high' ? 'badge-soft-warning' : 'badge-soft-primary');
                        @endphp
                        <tr class="{{ $isOverdue ? 'nx-overdue-row' : '' }} {{ $task->parent_task_id ? 'nx-subtask-row' : '' }}">
                            <td class="ps-4" style="min-width:280px"><div class="d-flex align-items-start gap-3"><span class="nx-task-type-icon workflow"><i data-lucide="git-branch"></i></span><div class="min-w-0"><div class="d-flex flex-wrap gap-2 mb-1"><span class="small fw-bold text-primary">{{ $task->task_no }}</span><span class="badge rounded-pill badge-soft-primary">{{ $task->parent_task_id ? 'Subtask' : 'Internal Workflow' }}</span><span class="badge rounded-pill {{ $priorityClass }}">{{ str($task->priority)->headline() }}</span></div><a class="fw-bold text-dark d-block mb-1" href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>@if($task->parent)<a class="nx-parent-link d-block small mb-1" href="{{ route('tasks.show', $task->parent) }}"><i data-lucide="corner-down-right" style="width:14px"></i> {{ $task->parent->task_no }} · {{ $task->parent->title }}</a>@endif
                                @if($task->subtasks_count)<span class="small text-secondary d-block mb-1">{{ $task->completed_subtasks_count }}/{{ $task->subtasks_count }} subtasks completed</span>@endif
                                @if($task->observers->isNotEmpty())<div class="nx-avatar-stack my-2" aria-label="Observers">@foreach($task->observers->take(4) as $member)@if($member->user)<x-person-avatar :user="$member->user" :size="26" detail="Observer" />@endif @endforeach @if($task->observers->count() > 4)<span class="nx-avatar-more" title="{{ $task->observers->skip(4)->map(fn ($member) => $member->user?->name)->filter()->implode(', ') }}">+{{ $task->observers->count() - 4 }}</span>@endif</div>@endif
                                <small class="text-secondary">Internal workflow</small></div></div></td>
                            <td style="min-width:180px"><div class="d-flex align-items-center gap-2"><x-person-avatar :user="$task->assignee" :size="34" detail="Responsible Person" /><div><span class="small fw-semibold d-block">{{ $task->assignee?->name ?? 'Unassigned' }}</span><small class="text-secondary">{{ $task->assignee?->designation ?: 'Responsible Person' }}</small></div></div></td>
                            <td style="min-width:180px"><div class="nx-flow-track"><span class="done"></span><span class="{{ $task->actions_count > 1 ? 'done' : 'active' }}"></span><span class="{{ $isOpen ? 'active' : 'done' }}"></span><span class="{{ $task->status === 'closed' ? 'done' : '' }}"></span></div><div class="d-flex justify-content-between nx-flow-label"><span>Created</span><span>Routed</span><span>{{ $isOpen ? 'Pending' : 'Done' }}</span></div></td>
                            <td style="min-width:145px" data-order="{{ $task->due_at?->timestamp ?? 9999999999 }}"><span class="small {{ $isOverdue ? 'text-danger fw-bold' : 'fw-semibold' }}">{{ $task->due_at?->format('d M Y') ?? 'No due date' }}</span><small class="d-block {{ $isOverdue ? 'text-danger' : 'text-secondary' }}">{{ $isOverdue ? $task->due_at->diffForHumans() : ($task->due_at?->format('h:i A') ?? 'Flexible') }}</small>@if($task->next_follow_up_at)<small class="d-block text-primary mt-1">Follow-up: {{ $task->next_follow_up_at->format('d M, h:i A') }}</small>@endif @if($task->revised_due_at)<small class="d-block text-secondary">Revised: {{ $task->revised_due_at->format('d M, h:i A') }}</small>@endif</td>
                            <td style="min-width:220px">@if($task->latestAction)<div class="small fw-semibold">{{ str($task->latestAction->action)->headline() }} by {{ $task->latestAction->user?->name }}</div><div class="small text-secondary text-truncate" style="max-width:230px">{{ $task->latestAction->remark }}</div><small class="text-secondary">{{ $task->latestAction->created_at->diffForHumans() }}</small>@else<span class="small text-secondary">No activity yet</span>@endif</td>
                            <td><span class="badge rounded-pill {{ $isOpen ? ($isOverdue ? 'badge-soft-danger' : 'badge-soft-warning') : 'badge-soft-success' }}">{{ $isOpen ? ($isOverdue ? 'Overdue' : 'Pending') : 'Completed' }}</span></td>
                            <td class="pe-4"><a class="nx-icon-btn" href="{{ route('tasks.show', $task) }}" aria-label="Open {{ $task->task_no }}"><i data-lucide="arrow-right" style="width:17px"></i></a></td>
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
