@php
    $creatingSubtask = isset($parentWorkflow);
    $creatingComplaint = !$creatingSubtask && $workType === 'ticket';
    $createUrl = $creatingSubtask ? route('tasks.subtasks.store', $parentWorkflow) : route($creatingComplaint ? 'complaints.store' : 'workflows.store');
@endphp
<div class="modal fade" id="taskModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="{{ $createUrl }}" class="modal-content nx-task-create" id="workItemForm">
            @csrf
            <input type="hidden" name="_form" value="create">
            <input type="hidden" name="task_type" value="{{ $creatingComplaint ? 'ticket' : 'workflow' }}">
            @if($returnTo ?? null)<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
            <div class="modal-header"><div><h5 class="modal-title">{{ $creatingSubtask ? 'Create subtask' : ($creatingComplaint ? 'Create customer complaint' : 'Create internal workflow') }}</h5><small class="text-secondary">{{ $creatingSubtask ? 'Linked to '.$parentWorkflow->task_no.'. The workflow will move to the subtask owner.' : ($creatingComplaint ? 'Record a customer issue and assign its owner.' : 'Assign internal work and track its completion.') }}</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">

                    <div class="col-12"><label class="form-label">Title *</label><input class="form-control" name="title" value="{{ old('title') }}" placeholder="Clear action-oriented title" required maxlength="255"></div>
                    <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" placeholder="Expected outcome, context and hand-off notes">{{ old('description') }}</textarea></div>
                    @if($creatingComplaint)
                    <div class="col-md-6" id="ticketCustomerField"><label class="form-label">Customer *</label><select class="form-select" name="customer_id" id="ticketCustomer" required><option value="">Select customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }} · {{ $customer->code }}</option>@endforeach</select><div class="form-text">Required for every complaint.</div></div>
                    @endif
                    <input type="hidden" name="category" value="Internal">
                    <div class="col-md-6"><label class="form-label">Priority *</label><select class="form-select" name="priority" required><option value="normal" @selected(old('priority') === 'normal')>Normal</option><option value="high" @selected(old('priority') === 'high')>High</option><option value="very_high" @selected(old('priority') === 'very_high')>Very high</option></select></div>
                    <div class="col-md-6"><x-date-time-picker :value="old('due_at')" id="createDueDate" /></div>
                    <div class="col-md-6"><label class="form-label">Responsible Person *</label><select class="form-select" name="assigned_to" required><option value="">Select responsible person</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(old('assigned_to') == $user->id)>{{ $user->name }} · {{ $user->designation }}</option>@endforeach</select></div>
                    <div class="col-12">
                        @if($creatingSubtask)
                            @php $inheritedObservers = $parentWorkflow->members->where('role', 'observer')->filter(fn ($member) => $member->user)->unique('user_id'); @endphp
                            <div class="nx-inherited-observers"><div class="d-flex justify-content-between align-items-center gap-2"><strong class="small">Observers</strong><span class="nx-inherited-label">Inherited from {{ $parentWorkflow->task_no }}</span></div>
                                @if($inheritedObservers->isNotEmpty())<div class="nx-avatar-stack mt-2">@foreach($inheritedObservers as $member)<x-person-avatar :user="$member->user" :size="32" detail="Observer" />@endforeach</div>@else<div class="small text-secondary mt-1">No observers on the parent workflow.</div>@endif
                            </div>
                        @else
                            <x-observer-picker :users="$users" :selected="old('observers', [])" />
                        @endif
                    </div>
                    <div class="col-12"><div class="nx-option-row"><div><label class="form-label mb-1" for="completionAttachment">Attachment required when complete</label><div class="small text-secondary">Require evidence before this item can be completed.</div></div><div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" name="attachment_required_on_completion" value="1" id="completionAttachment" @checked(old('attachment_required_on_completion'))></div></div></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i data-lucide="send" style="width:17px"></i> Create & notify</button></div>
        </form>
    </div>
</div>

@push('scripts')
    <script>
        @if($errors->any() && old('_form') === 'create') document.addEventListener('DOMContentLoaded', () => new bootstrap.Modal(document.getElementById('taskModal')).show()); @endif
    </script>
@endpush
