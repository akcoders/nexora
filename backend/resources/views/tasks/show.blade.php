<x-app-layout>
    <x-slot name="title">{{ $task->task_no }}</x-slot>
    <x-slot name="pageTitle">Internal workflow</x-slot>
    <x-slot name="breadcrumb">Workflow / {{ $task->task_no }}</x-slot>

    <div class="d-flex flex-wrap gap-3 mb-3"><a href="{{ route('workflows.index') }}" class="small d-inline-flex align-items-center gap-1"><i data-lucide="arrow-left" style="width:15px"></i>All workflows</a><a href="{{ route('workflows.board') }}" class="small d-inline-flex align-items-center gap-1"><i data-lucide="square-kanban" style="width:15px"></i>Kanban board</a></div>
    @if($errors->any())<div class="alert alert-danger d-flex align-items-center gap-2"><i data-lucide="circle-alert" style="width:18px"></i>{{ $errors->first() }}</div>@endif
    @if($task->parent)<div class="nx-subtask-banner mb-3"><i data-lucide="corner-down-right"></i><div><strong>Subtask</strong><a class="d-block" href="{{ route('tasks.show', $task->parent) }}">{{ $task->parent->task_no }} · {{ $task->parent->title }}</a></div></div>@endif
    <div class="row g-4">
        <div class="col-xl-8">
            <section class="nx-card p-4 mb-4 {{ $task->parent_task_id ? 'nx-subtask-card' : '' }}">
                <div class="d-flex flex-wrap justify-content-between gap-3">
                    <div>
                        <div class="d-flex gap-2 mb-3">
                            <span class="badge rounded-pill badge-soft-primary">Internal Workflow</span>
                            <span class="badge rounded-pill {{ $task->status === 'closed' ? 'badge-soft-success' : ($task->isOverdue() ? 'badge-soft-danger' : 'badge-soft-warning') }}">{{ $task->status === 'closed' ? 'Completed' : ($task->isOverdue() ? 'Overdue' : 'Pending') }}</span>
                            <span class="badge rounded-pill {{ $task->priority === 'very_high' ? 'badge-soft-danger' : ($task->priority === 'high' ? 'badge-soft-warning' : 'badge-soft-secondary') }}">{{ str($task->priority)->headline() }}</span>
                        </div>
                        <h2 class="h4 fw-bold">{{ $task->title }}</h2>
                        <p class="text-secondary mb-0">{{ $task->description ?: 'No description provided.' }}</p>
                    </div>
                    <div class="text-sm-end"><div class="fw-bold">{{ $task->task_no }}</div><small class="text-secondary">Created {{ $task->created_at->diffForHumans() }}</small></div>
                </div>
                <div class="nx-workflow-progress my-4">
                    <div class="done"><span><i data-lucide="plus"></i></span><small>Created</small></div>
                    <div class="done"><span><i data-lucide="user-check"></i></span><small>Assigned</small></div>
                    <div class="{{ $task->status !== 'closed' ? 'active' : 'done' }}"><span><i data-lucide="activity"></i></span><small>{{ $task->status !== 'closed' ? 'Pending' : 'Completed' }}</small></div>
                    <div class="{{ $task->status === 'closed' ? 'done' : '' }}"><span><i data-lucide="circle-check-big"></i></span><small>Completed</small></div>
                </div>
                <div class="row g-3 small">
                    <div class="col-md-4"><span class="text-secondary d-block">Completion attachment</span><strong>{{ $task->attachment_required_on_completion ? 'Required' : 'Optional' }}</strong></div>
                    <div class="col-md-4"><span class="text-secondary d-block">Original due date</span><strong>{{ $task->due_at?->format('d M Y, h:i A') ?? 'No due date' }}</strong></div>
                    @if($task->next_follow_up_at)<div class="col-md-4"><span class="text-secondary d-block">Next follow-up</span><strong>{{ $task->next_follow_up_at->format('d M Y, h:i A') }}</strong></div>@endif
                    @if($task->revised_due_at)<div class="col-md-4"><span class="text-secondary d-block">Latest revised due date</span><strong>{{ $task->revised_due_at->format('d M Y, h:i A') }}</strong></div>@endif
                </div>
            </section>
            <section class="nx-card mb-4">
                <div class="nx-card-header">
                    <div><h3 class="nx-card-title"><i data-lucide="git-branch"></i>Subtasks</h3>@if($task->subtasks->isNotEmpty())<p class="nx-card-subtitle">{{ $task->subtasks->where('status', 'closed')->count() }}/{{ $task->subtasks->count() }} completed</p>@endif</div>
                    @if(!$task->isClosed() && auth()->user()->can('act', $task))<button class="btn btn-light border btn-sm" data-bs-toggle="modal" data-bs-target="#taskModal"><i data-lucide="plus"></i>Create subtask</button>@endif
                </div>
                <div class="nx-card-body">
                    <div class="d-grid gap-3">@forelse($task->subtasks as $subtask)
                        <a class="nx-subtask-card d-block p-3 text-decoration-none" href="{{ route('tasks.show', $subtask) }}"><div class="d-flex justify-content-between gap-2"><strong class="small">{{ $subtask->task_no }} · Subtask</strong><span class="badge {{ $subtask->isClosed() ? 'badge-soft-success' : 'badge-soft-warning' }}">{{ $subtask->isClosed() ? 'Completed' : 'Pending' }}</span></div><div class="fw-semibold text-dark mt-2">{{ $subtask->title }}</div><div class="small text-secondary mt-1">{{ $subtask->assignee?->name }} · Due {{ $subtask->due_at?->format('d M Y, h:i A') ?? 'Not set' }}</div></a>
                    @empty<div class="nx-empty py-3"><i data-lucide="git-branch"></i><strong>No subtasks yet</strong>Split this workflow into linked tasks for other owners.</div>@endforelse</div>
                </div>
            </section>
            <section class="nx-card nx-activity-section">
                <div class="nx-card-header">
                    <div>
                        <h3 class="nx-card-title"><i data-lucide="history"></i>Activity timeline<span class="nx-count notranslate" title="{{ $task->actions->count() }} {{ $task->actions->count() === 1 ? 'update' : 'updates' }}">{{ $task->actions->count() }}</span></h3>
                        <p class="nx-card-subtitle">Every update, handover and decision in one place. Latest first.</p>
                    </div>
                </div>
                <div class="nx-card-body">
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
                </div>
            </section>
        </div>
        <div class="col-xl-4">
            @include('tasks.partials.workflow-update')
        </div>
    </div>
    @if(!$task->isClosed() && auth()->user()->can('act', $task))@include('tasks.partials.create-modal', ['parentWorkflow' => $task])@endif
</x-app-layout>
