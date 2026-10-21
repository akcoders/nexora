<div class="modal fade" id="taskModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="{{ route('tasks.store') }}" class="modal-content" id="workItemForm">
            @csrf
            @if($returnTo ?? null)<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
            <div class="modal-header"><div><h5 class="modal-title">Create workflow or ticket</h5><small class="text-secondary">Workflow is internal. A ticket must always reference a customer.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Work type *</label><select class="form-select" name="task_type" id="workItemType" required><option value="workflow" @selected(old('task_type', request('type')) === 'workflow')>Internal workflow</option><option value="ticket" @selected(old('task_type', request('type')) === 'ticket')>Customer ticket</option></select></div>
                    <div class="col-md-8"><label class="form-label">Title *</label><input class="form-control" name="title" value="{{ old('title') }}" placeholder="Clear action-oriented title" required minlength="3"></div>
                    <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" placeholder="Expected outcome, context and hand-off notes">{{ old('description') }}</textarea></div>
                    <div class="col-md-6" id="ticketCustomerField"><label class="form-label">Customer *</label><select class="form-select" name="customer_id" id="ticketCustomer"><option value="">Select customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }} · {{ $customer->code }}</option>@endforeach</select><div class="form-text">Required for every customer ticket.</div></div>
                    <div class="col-md-3"><label class="form-label">Category</label><select class="form-select" name="category" maxlength="100"><option>Internal</option><option>Customer Support</option><option>Complaint</option><option>Approval</option><option>Follow-up</option><option>Escalation</option><option>Inspection</option></select></div>
                    <div class="col-md-3"><label class="form-label">Priority *</label><select class="form-select" name="priority" required><option value="normal" @selected(old('priority') === 'normal')>Normal</option><option value="high" @selected(old('priority') === 'high')>High</option><option value="very_high" @selected(old('priority') === 'very_high')>Very high</option></select></div>
                    <div class="col-md-4"><label class="form-label">Due date & time</label><input type="datetime-local" class="form-control" name="due_at" value="{{ old('due_at') }}" data-v="not_past"></div>
                    <div class="col-md-4"><label class="form-label">Current assignee *</label><select class="form-select" name="assigned_to" required><option value="">Select owner</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(old('assigned_to') == $user->id)>{{ $user->name }} · {{ $user->designation }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label">Observers</label><select class="form-select" name="observers[]" multiple size="4">@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i data-lucide="send" style="width:17px"></i> Create & notify</button></div>
        </form>
    </div>
</div>

@push('scripts')
    <script>
        const workType = document.getElementById('workItemType');
        const customerField = document.getElementById('ticketCustomerField');
        const customerSelect = document.getElementById('ticketCustomer');
        const syncWorkType = () => {
            const ticket = workType.value === 'ticket';
            customerField.classList.toggle('d-none', !ticket);
            customerSelect.required = ticket;
            customerSelect.disabled = !ticket;
        };
        workType.addEventListener('change', syncWorkType);
        syncWorkType();
        @if($errors->any()) new bootstrap.Modal(document.getElementById('taskModal')).show(); @endif
    </script>
@endpush
