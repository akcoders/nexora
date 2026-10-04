<x-app-layout>
    <x-slot name="title">Customer Groups</x-slot>
    <x-slot name="pageTitle">Customer group master</x-slot>
    <x-slot name="breadcrumb">Masters / Customer Groups</x-slot>

    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><h2 class="h5 fw-bold mb-1">Customer relationship groups</h2><div class="small text-secondary">Create reusable group badges and assign them during customer creation.</div></div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#groupModal"><i data-lucide="plus" style="width:17px"></i>New group</button>
    </div>

    <div class="row g-4">
        @forelse($groups as $group)
            <div class="col-md-6 col-xl-4"><article class="nx-card p-4 h-100"><div class="d-flex justify-content-between gap-3 mb-3"><span class="nx-group-badge"><i data-lucide="tag"></i>{{ $group->name }}</span><span class="badge rounded-pill {{ $group->status === 'active' ? 'badge-soft-success' : 'bg-light text-secondary' }}">{{ ucfirst($group->status) }}</span></div><div class="small text-secondary mb-4" style="min-height:44px">{{ $group->description ?: 'No description provided.' }}</div><div class="d-flex justify-content-between align-items-end border-top pt-3"><div><strong class="d-block">{{ $group->customers_count }}</strong><small class="text-secondary">Linked customers · {{ $group->code }}</small></div><div class="d-flex gap-2"><button class="nx-icon-btn" data-bs-toggle="modal" data-bs-target="#editGroup{{ $group->id }}" aria-label="Edit {{ $group->name }}"><i data-lucide="pencil" style="width:16px"></i></button>@if($group->customers_count === 0)<form method="POST" action="{{ route('customer-groups.destroy', $group) }}" onsubmit="return confirm('Delete this customer group?')">@csrf @method('DELETE')<button class="nx-icon-btn text-danger" aria-label="Delete {{ $group->name }}"><i data-lucide="trash-2" style="width:16px"></i></button></form>@endif</div></div></article></div>

            <div class="modal fade" id="editGroup{{ $group->id }}" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" action="{{ route('customer-groups.update', $group) }}">@csrf @method('PUT')<div class="modal-header"><h5 class="modal-title">Edit customer group</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label">Group name *</label><input class="form-control" name="name" value="{{ $group->name }}" required></div><div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3">{{ $group->description }}</textarea></div><label class="form-label">Status *</label><select class="form-select" name="status"><option value="active" @selected($group->status === 'active')>Active</option><option value="inactive" @selected($group->status === 'inactive')>Inactive</option></select></div><div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save changes</button></div></form></div></div>
        @empty
            <div class="col-12"><div class="nx-empty-panel"><span class="nx-empty-icon"><i data-lucide="tags"></i></span><div><h3 class="h6 fw-bold mb-1">No customer groups</h3><div class="small text-secondary">Create the first group to start customer classification.</div></div></div></div>
        @endforelse
    </div>

    <div class="modal fade" id="groupModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" action="{{ route('customer-groups.store') }}">@csrf<div class="modal-header"><div><h5 class="modal-title">New customer group</h5><small class="text-secondary">This name appears as a badge on customer records.</small></div><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label">Group name *</label><input class="form-control" name="name" value="{{ old('name') }}" placeholder="Enterprise Customers" required></div><div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3">{{ old('description') }}</textarea></div><label class="form-label">Status *</label><select class="form-select" name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select></div><div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create group</button></div></form></div></div>

    @if($errors->any())@push('scripts')<script>new bootstrap.Modal(document.getElementById('groupModal')).show();</script>@endpush @endif
</x-app-layout>
