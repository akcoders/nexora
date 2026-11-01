<aside class="nx-card p-4" id="workflowUpdateCard">
    <h3 class="h6 fw-bold mb-3">Task update</h3>
    <div class="d-flex align-items-center gap-3 mb-2"><span class="nx-avatar">{{ strtoupper(substr($task->assignee->name, 0, 1)) }}</span><div><div class="fw-semibold">{{ $task->assignee->name }}</div><small class="text-secondary">Current owner</small></div></div>
    <p class="small text-secondary mb-4">Last updated {{ $task->updated_at->format('d M Y, h:i A') }}</p>

    @if(!$task->isClosed() && auth()->user()->can('act', $task))
        <form method="POST" enctype="multipart/form-data" action="{{ route('tasks.action', $task) }}" id="workflowUpdateForm">
            @csrf
            <input type="hidden" name="_form" value="action">
            <input type="hidden" name="action" value="update">

            <div class="mb-3"><label class="form-label" for="workflowRemark">Remark *</label><textarea class="form-control" id="workflowRemark" name="remark" rows="3" required maxlength="3000" placeholder="Progress, work completed or next step">{{ old('remark') }}</textarea></div>
            <div class="mb-3"><label class="form-label" for="workflowStatus">Status *</label><select class="form-select" name="status" id="workflowStatus" required><option value="pending" @selected(old('status', $task->status) === 'pending')>Active</option><option value="in_progress" @selected(old('status', $task->status) === 'in_progress')>In progress</option><option value="closed" @selected(old('status') === 'closed')>Completed</option></select></div>

            <div class="mb-3"><label class="form-label" for="workflowReassign">Assign to someone else?</label><select class="form-select" name="reassign" id="workflowReassign"><option value="0" @selected(!old('reassign'))>No — keep current owner</option><option value="1" @selected(old('reassign') == '1')>Yes</option></select></div>
            <fieldset id="workflowAssigneeFields" class="mb-3" @disabled(!old('reassign')) @if(!old('reassign')) hidden @endif><label class="form-label" for="workflowNextAssignee">Next assignee *</label><select class="form-select" name="assigned_to" id="workflowNextAssignee" required><option value="">Select user</option>@foreach($users as $user)@if($user->id !== $task->assigned_to)<option value="{{ $user->id }}" @selected(old('assigned_to') == $user->id)>{{ $user->name }}</option>@endif @endforeach</select></fieldset>

            <div class="mb-3"><label class="form-label" for="workflowChangeDueDate">Change due date?</label><select class="form-select" name="change_due_date" id="workflowChangeDueDate"><option value="0" @selected(!old('change_due_date'))>No — keep {{ $task->due_at?->format('d M Y, h:i A') ?? 'no due date' }}</option><option value="1" @selected(old('change_due_date') == '1')>Yes</option></select></div>
            <fieldset id="workflowDueDateFields" class="mb-3" @disabled(!old('change_due_date')) @if(!old('change_due_date')) hidden @endif>
                <div class="mb-3"><label class="form-label" for="workflowDueDate">New due date & time</label><input class="form-control" type="datetime-local" id="workflowDueDate" name="due_at" value="{{ old('due_at', $task->due_at?->format('Y-m-d\TH:i')) }}"><div class="form-text">Leave blank to remove the due date.</div></div>
                <label class="form-label" for="workflowDueDateReason">Reason for changing the due date *</label><textarea class="form-control" id="workflowDueDateReason" name="reason" rows="2" required maxlength="3000">{{ old('reason') }}</textarea>
            </fieldset>

            <div class="mb-3"><label class="form-label" for="workflowAttachments">Attachments{{ $task->attachment_required_on_completion ? ' (required to complete)' : ' (optional)' }}</label><input type="file" class="form-control" id="workflowAttachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv"><div class="form-text">Up to 5 files, 10 MB each.</div></div>
            @if($task->subtasks->contains(fn ($subtask) => !$subtask->isClosed()))<p class="small text-warning">Complete the open subtasks before completing this workflow.</p>@endif
            <button class="btn btn-primary w-100">Save update</button>
        </form>
    @else
        <p class="small text-secondary">{{ $task->isClosed() ? 'This workflow is completed.' : 'Only the owner or an authorised user can update this workflow.' }}</p>
    @endif

    @if($task->members->isNotEmpty())<div class="small text-secondary mt-4 mb-2">Observers & previous owners</div>@foreach($task->members as $member)<div class="small border-top py-2">{{ $member->user?->name }} · {{ str($member->role)->headline() }}</div>@endforeach @endif
</aside>

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('workflowUpdateForm');
            if (!form) return;
            const status = form.querySelector('#workflowStatus');
            const reassign = form.querySelector('#workflowReassign');
            const changeDueDate = form.querySelector('#workflowChangeDueDate');
            const syncWorkflowUpdate = () => {
                const completing = status.value === 'closed';
                reassign.disabled = completing;
                const assigneeFields = form.querySelector('#workflowAssigneeFields');
                assigneeFields.hidden = completing || reassign.value !== '1';
                assigneeFields.disabled = assigneeFields.hidden;
                const dueDateFields = form.querySelector('#workflowDueDateFields');
                dueDateFields.hidden = changeDueDate.value !== '1';
                dueDateFields.disabled = dueDateFields.hidden;
                form.querySelector('#workflowAttachments').required = completing && @json($task->attachment_required_on_completion);
            };
            [status, reassign, changeDueDate].forEach(field => field.addEventListener('change', syncWorkflowUpdate));
            syncWorkflowUpdate();
        })();
    </script>
@endpush
