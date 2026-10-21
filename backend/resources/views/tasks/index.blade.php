<x-app-layout>
    <x-slot name="title">Workflow & Tickets</x-slot>
    <x-slot name="pageTitle">Work control center</x-slot>
    <x-slot name="breadcrumb">Operations / Internal Workflow & Customer Tickets</x-slot>

    <section class="nx-workflow-hero mb-4">
        <div>
            <div class="nx-overline text-info">LIVE OWNERSHIP QUEUE</div>
            <h2 class="h4 fw-bold text-white mb-2">Internal workflows and customer tickets, clearly separated.</h2>
            <p class="mb-0">Every item stays visible to its assigned team until the final close action.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-light" href="{{ route('tasks.board', array_filter(['type' => request('type')])) }}"><i data-lucide="square-kanban" style="width:17px"></i> Kanban board</a>
            <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#taskModal"><i data-lucide="plus" style="width:17px"></i> New item</button>
        </div>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><a href="{{ route('tasks.index', ['scope' => 'open']) }}" class="nx-card nx-metric text-dark" style="--metric:#2563eb;--metric-soft:#dbeafe"><div><div class="nx-metric-value">{{ $openCount }}</div><div class="nx-metric-label">Active work</div><small class="text-secondary">Until final closure</small></div><span class="nx-metric-icon"><i data-lucide="activity"></i></span></a></div>
        <div class="col-sm-6 col-xl-3"><a href="{{ route('tasks.index', ['scope' => 'open', 'type' => 'workflow']) }}" class="nx-card nx-metric text-dark" style="--metric:#7c3aed;--metric-soft:#ede9fe"><div><div class="nx-metric-value">{{ $workflowOpenCount }}</div><div class="nx-metric-label">Internal workflows</div><small class="text-secondary">Team hand-offs</small></div><span class="nx-metric-icon"><i data-lucide="git-branch"></i></span></a></div>
        <div class="col-sm-6 col-xl-3"><a href="{{ route('tasks.index', ['scope' => 'open', 'type' => 'ticket']) }}" class="nx-card nx-metric text-dark" style="--metric:#0284c7;--metric-soft:#e0f2fe"><div><div class="nx-metric-value">{{ $ticketOpenCount }}</div><div class="nx-metric-label">Customer tickets</div><small class="text-secondary">Customer-linked issues</small></div><span class="nx-metric-icon"><i data-lucide="ticket-check"></i></span></a></div>
        <div class="col-sm-6 col-xl-3"><a href="{{ route('tasks.index', ['status' => 'closed']) }}" class="nx-card nx-metric text-dark" style="--metric:#16a34a;--metric-soft:#dcfce7"><div><div class="nx-metric-value">{{ $closedCount }}</div><div class="nx-metric-label">Closed</div><small class="text-secondary">Completed items</small></div><span class="nx-metric-icon"><i data-lucide="circle-check-big"></i></span></a></div>
    </div>

    @if($errors->any())<div class="alert alert-danger d-flex align-items-center gap-2"><i data-lucide="circle-alert" style="width:18px"></i>{{ $errors->first() }}</div>@endif

    <section class="nx-card mb-4">
        <div class="p-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
            <nav class="nx-filter-pills" aria-label="Work view">
                <a class="{{ !request()->hasAny(['scope','status','type']) ? 'active' : '' }}" href="{{ route('tasks.index') }}">All</a>
                <a class="{{ request('scope') === 'open' && !request('type') ? 'active' : '' }}" href="{{ route('tasks.index', ['scope' => 'open']) }}">Active</a>
                <a class="{{ request('type') === 'workflow' ? 'active' : '' }}" href="{{ route('tasks.index', ['scope' => 'open', 'type' => 'workflow']) }}">Internal Workflow</a>
                <a class="{{ request('type') === 'ticket' ? 'active' : '' }}" href="{{ route('tasks.index', ['scope' => 'open', 'type' => 'ticket']) }}">Customer Tickets</a>
                <a class="{{ request('status') === 'closed' ? 'active' : '' }}" href="{{ route('tasks.index', ['status' => 'closed']) }}">Closed</a>
            </nav>
            <span class="small text-secondary">{{ $tasks->total() }} results · {{ $overdueCount }} overdue</span>
        </div>
        <form class="p-3" method="GET">
            @if(request('scope'))<input type="hidden" name="scope" value="{{ request('scope') }}">@endif
            <div class="row g-2 align-items-center">
                <div class="col-md-4"><div class="input-group"><span class="input-group-text bg-white border-end-0"><i data-lucide="search" style="width:17px"></i></span><input class="form-control border-start-0 ps-0" name="search" value="{{ request('search') }}" placeholder="Search number, title or customer"></div></div>
                <div class="col-md-2"><select class="form-select" name="type"><option value="">Workflow & tickets</option><option value="workflow" @selected(request('type') === 'workflow')>Internal workflow</option><option value="ticket" @selected(request('type') === 'ticket')>Customer ticket</option></select></div>
                <div class="col-md-2"><select class="form-select" name="priority"><option value="">All priorities</option>@foreach(['normal','high','very_high'] as $priority)<option value="{{ $priority }}" @selected(request('priority') === $priority)>{{ str($priority)->headline() }}</option>@endforeach</select></div>
                <div class="col-md-2"><select class="form-select" name="assignee"><option value="">All assignees</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(request('assignee') == $user->id)>{{ $user->name }}</option>@endforeach</select></div>
                <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-primary flex-grow-1">Apply</button>@if(request()->hasAny(['search','type','priority','assignee','scope','status']))<a class="btn btn-light border" href="{{ route('tasks.index') }}" aria-label="Clear filters"><i data-lucide="x" style="width:16px"></i></a>@endif</div>
            </div>
        </form>
    </section>

    <section class="nx-card overflow-hidden">
        <div class="table-responsive">
            <table class="table nx-table nx-workflow-table mb-0">
                <thead><tr><th class="ps-4">Work item</th><th>Current owner</th><th>Progress</th><th>Due</th><th>Latest activity</th><th>Status</th><th class="pe-4"></th></tr></thead>
                <tbody>
                    @forelse($tasks as $task)
                        @php
                            $isTicket = $task->task_type === 'ticket';
                            $isOpen = $task->status !== 'closed';
                            $isOverdue = $isOpen && $task->due_at?->isPast();
                            $priorityClass = $task->priority === 'very_high' ? 'badge-soft-danger' : ($task->priority === 'high' ? 'badge-soft-warning' : 'badge-soft-primary');
                        @endphp
                        <tr class="{{ $isOverdue ? 'nx-overdue-row' : '' }}">
                            <td class="ps-4" style="min-width:280px"><div class="d-flex align-items-start gap-3"><span class="nx-task-type-icon {{ $isTicket ? 'ticket' : 'workflow' }}"><i data-lucide="{{ $isTicket ? 'ticket-check' : 'git-branch' }}"></i></span><div class="min-w-0"><div class="d-flex flex-wrap gap-2 mb-1"><span class="small fw-bold text-primary">{{ $task->task_no }}</span><span class="badge rounded-pill {{ $isTicket ? 'badge-soft-info' : 'badge-soft-primary' }}">{{ $isTicket ? 'Customer Ticket' : 'Internal Workflow' }}</span><span class="badge rounded-pill {{ $priorityClass }}">{{ str($task->priority)->headline() }}</span></div><a class="fw-bold text-dark d-block mb-1" href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a><small class="text-secondary">{{ $task->customer?->name ?? 'Internal operation' }} · {{ $task->category ?: 'General' }}</small></div></div></td>
                            <td style="min-width:180px"><div class="d-flex align-items-center gap-2"><span class="nx-avatar" style="width:34px;height:34px;border-radius:10px">{{ strtoupper(substr($task->assignee?->name ?? '?', 0, 1)) }}</span><div><span class="small fw-semibold d-block">{{ $task->assignee?->name ?? 'Unassigned' }}</span><small class="text-secondary">{{ $task->assignee?->designation ?: 'Current assignee' }}</small></div></div></td>
                            <td style="min-width:180px"><div class="nx-flow-track"><span class="done"></span><span class="{{ $task->actions_count > 1 ? 'done' : 'active' }}"></span><span class="{{ $isOpen ? 'active' : 'done' }}"></span><span class="{{ $task->status === 'closed' ? 'done' : '' }}"></span></div><div class="d-flex justify-content-between nx-flow-label"><span>Created</span><span>Routed</span><span>{{ $isOpen ? 'Active' : 'Done' }}</span></div></td>
                            <td style="min-width:145px"><span class="small {{ $isOverdue ? 'text-danger fw-bold' : 'fw-semibold' }}">{{ $task->due_at?->format('d M Y') ?? 'No due date' }}</span><small class="d-block {{ $isOverdue ? 'text-danger' : 'text-secondary' }}">{{ $isOverdue ? $task->due_at->diffForHumans() : ($task->due_at?->format('h:i A') ?? 'Flexible') }}</small></td>
                            <td style="min-width:220px">@if($task->latestAction)<div class="small fw-semibold">{{ str($task->latestAction->action)->headline() }} by {{ $task->latestAction->user?->name }}</div><div class="small text-secondary text-truncate" style="max-width:230px">{{ $task->latestAction->remark }}</div><small class="text-secondary">{{ $task->latestAction->created_at->diffForHumans() }}</small>@else<span class="small text-secondary">No activity yet</span>@endif</td>
                            <td><span class="badge rounded-pill {{ $isOpen ? 'badge-soft-warning' : 'badge-soft-success' }}">{{ $isOpen ? 'Active' : 'Closed' }}</span></td>
                            <td class="pe-4"><a class="nx-icon-btn" href="{{ route('tasks.show', $task) }}" aria-label="Open {{ $task->task_no }}"><i data-lucide="arrow-right" style="width:17px"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="text-center py-5"><span class="nx-empty-icon mx-auto mb-3"><i data-lucide="list-checks"></i></span><div class="fw-bold">No work items match this view</div><div class="small text-secondary mt-1">Change the filters or create a new workflow or ticket.</div></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tasks->hasPages())<div class="p-3 border-top">{{ $tasks->links() }}</div>@endif
    </section>

    @include('tasks.partials.create-modal')
</x-app-layout>
