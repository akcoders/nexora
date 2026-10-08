<x-app-layout>
    @php $isTicket = $task->task_type === 'ticket'; @endphp
    <x-slot name="title">{{ $task->task_no }}</x-slot>
    <x-slot name="pageTitle">{{ $isTicket ? 'Customer ticket' : 'Internal workflow' }}</x-slot>
    <x-slot name="breadcrumb">{{ $isTicket ? 'Tickets' : 'Workflow' }} / {{ $task->task_no }}</x-slot>

    <a href="{{ route('tasks.index', ['type' => $task->task_type]) }}" class="small d-inline-flex align-items-center gap-1 mb-3"><i data-lucide="arrow-left" style="width:15px"></i>All {{ $isTicket ? 'tickets' : 'workflows' }}</a>
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
            <section class="nx-card p-4">
                <h3 class="h6 fw-bold mb-4">Activity timeline</h3>
                <div class="d-grid gap-4">
                    @foreach($task->actions as $action)
                        <div class="d-flex gap-3">
                            <span class="nx-metric-icon flex-shrink-0" style="width:38px;height:38px"><i data-lucide="{{ $action->action === 'close' ? 'circle-check-big' : ($action->action === 'assign' ? 'forward' : 'plus') }}" style="width:18px"></i></span>
                            <div class="flex-grow-1"><div class="d-flex justify-content-between"><strong>{{ str($action->action)->headline() }}</strong><small class="text-secondary">{{ $action->created_at->format('d M, h:i A') }}</small></div><div class="small text-secondary mb-1">by {{ $action->user?->name }} @if($action->assignee) · assigned to {{ $action->assignee->name }} @endif</div><div class="small">{{ $action->remark }}</div></div>
                        </div>
                    @endforeach
                </div>
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
                        <label class="form-label">Remark *</label><textarea class="form-control mb-3" name="remark" rows="4" placeholder="Work completed, outcome or next step" required></textarea>
                        <label class="form-label">Action *</label><select class="form-select mb-3" name="action" id="taskAction" required><option value="assign">Assign next</option><option value="close">Close {{ $isTicket ? 'ticket' : 'workflow' }}</option></select>
                        <div id="nextAssignee" class="mb-3"><label class="form-label">Next assignee</label><select class="form-select" name="assigned_to"><option value="">Select user</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
                        <button class="btn btn-primary w-100">Submit action</button>
                    </form>
                </aside>
            @endif
        </div>
    </div>
    @push('scripts')<script>document.getElementById('taskAction')?.addEventListener('change', e => document.getElementById('nextAssignee').classList.toggle('d-none', e.target.value !== 'assign'));</script>@endpush
</x-app-layout>
