<x-app-layout>
    @php $isTicket = $task->task_type === 'ticket'; @endphp
    <x-slot name="title">{{ $task->task_no }}</x-slot>
    <x-slot name="pageTitle">{{ $isTicket ? 'Customer ticket' : 'Internal workflow' }}</x-slot>
    <x-slot name="breadcrumb">{{ $isTicket ? 'Tickets' : 'Workflow' }} / {{ $task->task_no }}</x-slot>

    <div class="d-flex flex-wrap gap-3 mb-3"><a href="{{ route('tasks.index', ['type' => $task->task_type]) }}" class="small d-inline-flex align-items-center gap-1"><i data-lucide="arrow-left" style="width:15px"></i>All {{ $isTicket ? 'tickets' : 'workflows' }}</a><a href="{{ route('tasks.board', ['type' => $task->task_type]) }}" class="small d-inline-flex align-items-center gap-1"><i data-lucide="square-kanban" style="width:15px"></i>Kanban board</a></div>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="nx-card p-4 mb-4">
                <div class="d-flex flex-wrap justify-content-between gap-3">
                    <div>
                        <div class="d-flex gap-2 mb-3">
                            <span class="badge rounded-pill {{ $isTicket ? 'badge-soft-success' : 'badge-soft-primary' }}">{{ $isTicket ? 'Customer Ticket' : 'Internal Workflow' }}</span>
                            <span class="badge rounded-pill {{ $task->status === 'closed' ? 'badge-soft-success' : 'badge-soft-warning' }}">{{ $task->status === 'closed' ? 'Closed' : 'Active' }}</span>
                            <span class="badge rounded-pill {{ $task->priority === 'very_high' ? 'badge-soft-danger' : 'badge-soft-primary' }}">{{ str($task->priority)->headline() }}</span>
                        </div>
                        <h2 class="h4 fw-bold">{{ $task->title }}</h2>
                        <p class="text-secondary mb-0">{{ $task->description ?: 'No description provided.' }}</p>
                    </div>
                    <div class="text-end"><div class="fw-bold">{{ $task->task_no }}</div><small class="text-secondary">Created {{ $task->created_at->diffForHumans() }}</small></div>
                </div>
                <div class="nx-workflow-progress my-4">
                    <div class="done"><span><i data-lucide="plus"></i></span><small>Created</small></div>
                    <div class="done"><span><i data-lucide="user-check"></i></span><small>Assigned</small></div>
                    <div class="{{ $task->status !== 'closed' ? 'active' : 'done' }}"><span><i data-lucide="activity"></i></span><small>{{ $task->status !== 'closed' ? 'In progress' : 'Processed' }}</small></div>
                    <div class="{{ $task->status === 'closed' ? 'done' : '' }}"><span><i data-lucide="circle-check-big"></i></span><small>Closed</small></div>
                </div>
                <div class="row g-3 small">
                    <div class="col-md-4"><span class="text-secondary d-block">Customer reference</span><strong>{{ $task->customer?->name ?? 'Internal only' }}</strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Category</span><strong>{{ $task->category ?: 'General' }}</strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Due date</span><strong>{{ $task->due_at?->format('d M Y, h:i A') ?? 'No due date' }}</strong></div>
                </div>
            </section>
            <section class="nx-card nx-activity-section p-3 p-md-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                    <div>
                        <div class="nx-overline">WORK ITEM HISTORY</div>
                        <h3 class="h5 fw-bold mb-1">Activity timeline</h3>
                        <p class="small text-secondary mb-0">Every update, handover and decision in one place. Latest first.</p>
                    </div>
                    <span class="nx-activity-count"><i data-lucide="history" aria-hidden="true"></i>{{ $task->actions->count() }} {{ $task->actions->count() === 1 ? 'update' : 'updates' }}</span>
                </div>
                <ol class="nx-activity-timeline list-unstyled mb-0">
                    @forelse($task->actions as $action)
                        @php
                            $activityTone = match ($action->action) { 'close' => 'success', 'assign' => 'primary', 'priority' => 'warning', 'created' => 'slate', default => 'primary' };
                            $activityIcon = match ($action->action) { 'close' => 'circle-check-big', 'assign' => 'user-round-check', 'priority' => 'flag', 'created' => 'plus', default => 'activity' };
                        @endphp
                        <li class="nx-activity-entry nx-activity-entry--{{ $activityTone }}">
                            <span class="nx-activity-marker" aria-hidden="true"><i data-lucide="{{ $activityIcon }}"></i></span>
                            <article class="nx-activity-card">
                                <div class="nx-activity-card-head">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <h4 class="nx-activity-title mb-0">{{ $action->action === 'priority' ? 'Priority changed' : str($action->action)->headline() }}</h4>
                                        @if($loop->first)<span class="nx-activity-latest">Latest update</span>@endif
                                    </div>
                                    <time class="nx-activity-time" datetime="{{ $action->created_at->toIso8601String() }}"><i data-lucide="clock-3" aria-hidden="true"></i>{{ $action->created_at->format('d M Y') }} <span aria-hidden="true">·</span> {{ $action->created_at->format('h:i A') }}</time>
                                </div>
                                <div class="nx-activity-people">
                                    <div class="nx-activity-person"><span class="nx-activity-avatar" aria-hidden="true">{{ strtoupper(substr($action->user?->name ?? 'System', 0, 1)) }}</span><span><small>Performed by</small><strong>{{ $action->user?->name ?? 'System' }}</strong></span></div>
                                    @if($action->assignee)
                                        <div class="nx-activity-person"><span class="nx-activity-person-icon" aria-hidden="true"><i data-lucide="arrow-right"></i></span><span><small>{{ $action->action === 'created' ? 'Initially assigned to' : 'Assigned to' }}</small><strong>{{ $action->assignee->name }}</strong></span></div>
                                    @endif
                                </div>
                                @if($action->remark)
                                    <div class="nx-activity-remark"><i data-lucide="message-square-text" aria-hidden="true"></i><p class="mb-0">{{ $action->remark }}</p></div>
                                @endif
                            </article>
                        </li>
                    @empty
                        <li class="nx-activity-empty">No activity has been recorded yet.</li>
                    @endforelse
                </ol>
            </section>
        </div>
        <div class="col-xl-4">
            <aside class="nx-card p-4 mb-4">
                <h3 class="h6 fw-bold mb-3">Assignment</h3>
                <div class="d-flex align-items-center gap-3 mb-4"><span class="nx-avatar">{{ strtoupper(substr($task->assignee->name, 0, 1)) }}</span><div><div class="fw-semibold">{{ $task->assignee->name }}</div><small class="text-secondary">Current assignee</small></div></div>
                <div class="small text-secondary mb-2">Observers & previous owners</div>
                @forelse($task->members as $member)<div class="small border-top py-2">{{ $member->user?->name }} · {{ str($member->role)->headline() }}</div>@empty<div class="small">No observers</div>@endforelse
            </aside>
            @if($task->status !== 'closed')
                <aside class="nx-card p-4">
                    <div class="d-flex align-items-center gap-2 mb-2"><span class="nx-metric-icon" style="width:36px;height:36px"><i data-lucide="send" style="width:17px"></i></span><h3 class="h6 fw-bold mb-0">Move {{ $isTicket ? 'ticket' : 'workflow' }}</h3></div>
                    <p class="small text-secondary">Add a clear remark, then close this item or hand it to the next owner.</p>
                    <form method="POST" action="{{ route('tasks.action', $task) }}">
                        @csrf
                        <label class="form-label">Remark *</label><textarea class="form-control mb-3" name="remark" rows="4" placeholder="Work completed, outcome or next step" required maxlength="3000"></textarea>
                        <label class="form-label">Action *</label><select class="form-select mb-3" name="action" id="taskAction" required><option value="assign">Assign next</option><option value="close">Close {{ $isTicket ? 'ticket' : 'workflow' }}</option></select>
                        <div id="nextAssignee" class="mb-3"><label class="form-label">Next assignee</label><select class="form-select" name="assigned_to" data-v="required_if:action,assign"><option value="">Select user</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
                        <button class="btn btn-primary w-100">Submit action</button>
                    </form>
                </aside>
            @endif
        </div>
    </div>
    @push('scripts')<script>document.getElementById('taskAction')?.addEventListener('change', e => document.getElementById('nextAssignee').classList.toggle('d-none', e.target.value !== 'assign'));</script>@endpush
</x-app-layout>
