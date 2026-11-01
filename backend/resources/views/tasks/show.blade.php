<x-app-layout>
    @php $isTicket = $task->task_type === 'ticket'; @endphp
    <x-slot name="title">{{ $task->task_no }}</x-slot>
    <x-slot name="pageTitle">{{ $isTicket ? 'Customer complaint' : 'Internal workflow' }}</x-slot>
    <x-slot name="breadcrumb">{{ $isTicket ? 'Complaints' : 'Workflow' }} / {{ $task->task_no }}</x-slot>

    <div class="d-flex flex-wrap gap-3 mb-3"><a href="{{ route($isTicket ? 'complaints.index' : 'workflows.index') }}" class="small d-inline-flex align-items-center gap-1"><i data-lucide="arrow-left" style="width:15px"></i>All {{ $isTicket ? 'complaints' : 'workflows' }}</a><a href="{{ route($isTicket ? 'complaints.board' : 'workflows.board') }}" class="small d-inline-flex align-items-center gap-1"><i data-lucide="square-kanban" style="width:15px"></i>Kanban board</a></div>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @if($task->parent)<div class="nx-subtask-banner mb-3"><i data-lucide="corner-down-right"></i><div><strong>Subtask</strong><a class="d-block" href="{{ route('tasks.show', $task->parent) }}">{{ $task->parent->task_no }} · {{ $task->parent->title }}</a></div></div>@endif
    <div class="row g-4">
        <div class="col-xl-8">
            <section class="nx-card p-4 mb-4 {{ $task->parent_task_id ? 'nx-subtask-card' : '' }}">
                <div class="d-flex flex-wrap justify-content-between gap-3">
                    <div>
                        <div class="d-flex gap-2 mb-3">
                            <span class="badge rounded-pill {{ $isTicket ? 'badge-soft-success' : 'badge-soft-primary' }}">{{ $isTicket ? 'Customer Complaint' : 'Internal Workflow' }}</span>
                            <span class="badge rounded-pill {{ $task->status === 'closed' ? 'badge-soft-success' : ($task->isOverdue() ? 'badge-soft-danger' : 'badge-soft-warning') }}">{{ $task->status === 'closed' ? 'Completed' : ($task->isOverdue() ? 'Overdue' : 'Pending') }}</span>
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
                    <div class="{{ $task->status !== 'closed' ? 'active' : 'done' }}"><span><i data-lucide="activity"></i></span><small>{{ $task->status !== 'closed' ? 'Pending' : 'Completed' }}</small></div>
                    <div class="{{ $task->status === 'closed' ? 'done' : '' }}"><span><i data-lucide="circle-check-big"></i></span><small>Completed</small></div>
                </div>
                <div class="row g-3 small">
                    @if($isTicket)<div class="col-md-4"><span class="text-secondary d-block">Customer</span><strong>{{ $task->customer?->name }}</strong></div>@endif
                    <div class="col-md-4"><span class="text-secondary d-block">Completion attachment</span><strong>{{ $task->attachment_required_on_completion ? 'Required' : 'Optional' }}</strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Original due date</span><strong>{{ $task->due_at?->format('d M Y, h:i A') ?? 'No due date' }}</strong></div>
                    @if($task->next_follow_up_at)<div class="col-md-4"><span class="text-secondary d-block">Next follow-up</span><strong>{{ $task->next_follow_up_at->format('d M Y, h:i A') }}</strong></div>@endif
                    @if($task->revised_due_at)<div class="col-md-4"><span class="text-secondary d-block">Latest revised due date</span><strong>{{ $task->revised_due_at->format('d M Y, h:i A') }}</strong></div>@endif
                </div>
            </section>
            @if(!$isTicket)
                <section class="nx-card p-3 p-md-4 mb-4">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-3"><div><h3 class="h5 fw-bold mb-1">Subtasks</h3><span class="small text-secondary">{{ $task->subtasks->where('status', 'closed')->count() }}/{{ $task->subtasks->count() }} completed</span></div>
                        @if(!$task->isClosed() && auth()->user()->can('act', $task))<button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#taskModal"><i data-lucide="plus" style="width:16px"></i> Create subtask</button>@endif
                    </div>
                    <div class="d-grid gap-3">@forelse($task->subtasks as $subtask)
                        <a class="nx-subtask-card d-block p-3 text-decoration-none" href="{{ route('tasks.show', $subtask) }}"><div class="d-flex justify-content-between gap-2"><strong class="small">{{ $subtask->task_no }} · Subtask</strong><span class="badge {{ $subtask->isClosed() ? 'badge-soft-success' : 'badge-soft-warning' }}">{{ $subtask->isClosed() ? 'Completed' : 'Pending' }}</span></div><div class="fw-semibold text-dark mt-2">{{ $subtask->title }}</div><div class="small text-secondary mt-1">{{ $subtask->assignee?->name }} · Due {{ $subtask->due_at?->format('d M Y, h:i A') ?? 'Not set' }}</div></a>
                    @empty<div class="small text-secondary">No subtasks yet.</div>@endforelse</div>
                </section>
            @endif
            <section class="nx-card nx-activity-section p-3 p-md-4">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
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
                            $activityTone = match ($action->action) { 'close', 'returned' => 'success', 'assign' => 'primary', 'priority', 'due_date' => 'warning', 'created' => 'slate', default => 'primary' };
                            $activityIcon = match ($action->action) { 'close' => 'circle-check-big', 'returned' => 'corner-up-left', 'assign' => 'user-round-check', 'priority' => 'flag', 'due_date' => 'calendar-clock', 'created' => 'plus', default => 'activity' };
                        @endphp
                        <li class="nx-activity-entry nx-activity-entry--{{ $activityTone }}">
                            <span class="nx-activity-marker" aria-hidden="true"><i data-lucide="{{ $activityIcon }}"></i></span>
                            @php
                                $isSubtaskCreation = $action->action === 'created' && ($action->related_task_id || $task->parent_task_id);
                                $activityTitle = $isSubtaskCreation ? 'Subtask created and assigned' : (($action->related_task_id ? 'Subtask · ' : '').match($action->action) { 'priority' => 'Priority changed', 'due_date' => 'Due date changed', 'close' => 'Completed', 'update' => 'Progress update', 'status' => 'Status changed', 'returned' => 'Returned to creator', default => str($action->action)->headline() });
                            @endphp
                            <article class="nx-activity-card">
                                <div class="nx-activity-card-head">
                                    <div class="d-flex flex-wrap align-items-center gap-2"><h4 class="nx-activity-title mb-0">{{ $activityTitle }}</h4>@if($loop->first)<span class="nx-activity-latest">Latest</span>@endif</div>
                                    <time class="nx-activity-time" datetime="{{ $action->created_at->toIso8601String() }}" title="{{ $action->created_at->format('d M Y, h:i A') }}">{{ $action->created_at->format('d M, h:i A') }}</time>
                                </div>
                                <div class="nx-activity-meta">
                                    <x-person-avatar :user="$action->user" :size="22" />
                                    <strong>{{ $action->user?->name ?? 'System' }}</strong>
                                    @if($action->assignee)<span class="nx-activity-arrow" aria-hidden="true">→</span><span class="text-secondary">Responsible Person:</span><strong>{{ $action->assignee->name }}</strong>@endif
                                    @if($action->relatedTask)<a class="nx-activity-task-link" href="{{ route('tasks.show', $action->relatedTask) }}" title="{{ $action->relatedTask->title }}">{{ $action->relatedTask->task_no }}</a>@endif
                                </div>
                                @if($action->remark)
                                    @if(mb_strlen($action->remark) > 240 || substr_count($action->remark, "\n") > 2)
                                        <details class="nx-activity-details"><summary><span class="nx-remark-preview">{{ str($action->remark)->squish()->limit(180) }}</span><span class="nx-expand-label">Read more</span><span class="nx-expanded-label">Hide details</span></summary><p class="nx-activity-note">{{ $action->remark }}</p></details>
                                    @else
                                        <p class="nx-activity-note">{{ $action->remark }}</p>
                                    @endif
                                @endif
                                @if($action->next_follow_up_at)<div class="small text-secondary mt-1">Next follow-up: <strong>{{ $action->next_follow_up_at->format('d M Y, h:i A') }}</strong>@if($action->revised_due_at) · Revised due: <strong>{{ $action->revised_due_at->format('d M Y, h:i A') }}</strong><div class="text-warning">Reason for delay: {{ $action->delay_reason }}</div>@endif</div>@endif
                                @if($action->attachments)<div class="nx-activity-attachments">@foreach($action->attachments as $index => $attachment)<a href="{{ route('tasks.attachments.show', [$task, $action, $index]) }}"><i data-lucide="paperclip" aria-hidden="true"></i>{{ $attachment['name'] }}</a>@endforeach</div>@endif
                            </article>
                        </li>
                    @empty
                        <li class="nx-activity-empty">No activity has been recorded yet.</li>
                    @endforelse
                </ol>
            </section>
        </div>
        <div class="col-xl-4">
            @if(!$isTicket)
                @include('tasks.partials.workflow-update')
            @else
            <aside class="nx-card p-4 mb-4">
                <h3 class="h6 fw-bold mb-3">Assignment</h3>
                <div class="d-flex align-items-center gap-3 mb-4"><span class="nx-avatar">{{ strtoupper(substr($task->assignee->name, 0, 1)) }}</span><div><div class="fw-semibold">{{ $task->assignee->name }}</div><small class="text-secondary">Responsible Person</small></div></div>
                @include('tasks.partials.participants')
            </aside>
            @if(!$task->isClosed() && auth()->user()->can('act', $task))
                <aside class="nx-card p-4">
                    <div class="d-flex align-items-center gap-2 mb-2"><span class="nx-metric-icon" style="width:36px;height:36px"><i data-lucide="send" style="width:17px"></i></span><h3 class="h6 fw-bold mb-0">Move {{ $isTicket ? 'ticket' : 'workflow' }}</h3></div>
                    <p class="small text-secondary">Add a clear remark, then close this item or hand it to the next owner.</p>
                    <form method="POST" enctype="multipart/form-data" action="{{ route('tasks.action', $task) }}">
                        <input type="hidden" name="_form" value="action">
                        @csrf
                        <label class="form-label">Remark *</label><textarea class="form-control mb-3" name="remark" rows="4" placeholder="Work completed, outcome or next step" required maxlength="3000">{{ old('remark') }}</textarea>
                        <label class="form-label">Action *</label><select class="form-select mb-3" name="action" id="taskAction" required><option value="assign">Assign next</option><option value="close">Close {{ $isTicket ? 'ticket' : 'workflow' }}</option></select>
                        <div id="nextAssignee" class="mb-3"><label class="form-label">Responsible Person</label><select class="form-select" name="assigned_to" data-v="required_if:action,assign"><option value="">Select user</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
                        @include('tasks.partials.follow-up')
                        <div class="mb-3"><label class="form-label" for="taskAttachments">Attachments{{ $task->attachment_required_on_completion ? ' (required to complete)' : ' (optional)' }}</label><input type="file" class="form-control" id="taskAttachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv"><div class="form-text">Up to 5 files, 10 MB each. Images, PDF, Word, Excel or text.</div></div>
                        @if($task->subtasks->contains(fn ($subtask) => !$subtask->isClosed()))<p class="small text-warning">Complete the open subtasks before completing this workflow.</p>@endif
                        <button class="btn btn-primary w-100">Submit action</button>
                    </form>
                </aside>

            @endif
            @endif
        </div>
    </div>
    @if(!$isTicket && !$task->isClosed() && auth()->user()->can('act', $task))@include('tasks.partials.create-modal', ['parentWorkflow' => $task])@endif
    @push('scripts')
        <script>
            const taskAction = document.getElementById('taskAction');
            const syncTaskAction = () => {
                const assigning = taskAction.value === 'assign';
                document.getElementById('nextAssignee').classList.toggle('d-none', !assigning);
                document.querySelector('#nextAssignee select').disabled = !assigning;
                document.getElementById('taskAttachments').required = !assigning && @json($task->attachment_required_on_completion);
            };
            taskAction?.addEventListener('change', syncTaskAction);
            if (taskAction) syncTaskAction();
        </script>
    @endpush
</x-app-layout>
