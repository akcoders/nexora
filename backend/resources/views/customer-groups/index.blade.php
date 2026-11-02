<x-app-layout>
    <x-slot name="title">Customer Groups</x-slot>
    <x-slot name="pageTitle">Customer group master</x-slot>
    <x-slot name="breadcrumb">Masters / Customer Groups</x-slot>

    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="nx-page-header">
        <div><div class="nx-page-kicker">Customers</div><h2>Customer relationship groups</h2><p>Reusable group badges assigned during customer creation.</p></div>
        <div class="nx-page-actions"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#groupModal"><i data-lucide="plus"></i>New group</button></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-kpi label="Groups" :value="$groups->count()" icon="tags" tone="primary" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Active groups" :value="$groups->where('status', 'active')->count()" icon="badge-check" tone="success" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Linked customers" :value="$groups->sum('customers_count')" icon="building-2" tone="info" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Unused groups" :value="$groups->where('customers_count', 0)->count()" icon="circle-dashed" tone="secondary" /></div>
    </div>
    <section class="nx-card nx-datatable-card">
        <table class="table nx-table" data-datatable data-dt-title="Customer groups" data-dt-empty="No customer groups yet. Create the first group to start customer classification.">
            <thead><tr><th>Group</th><th>Code</th><th>Description</th><th>Linked customers</th><th>Status</th><th class="no-export no-sort text-end" data-orderable="false"></th></tr></thead>
            <tbody>
                @foreach($groups as $group)
                    <tr>
                        <td><span class="nx-group-badge"><i data-lucide="tag"></i>{{ $group->name }}</span></td>
                        <td class="nx-code notranslate">{{ $group->code }}</td>
                        <td class="text-secondary" style="min-width:220px">{{ $group->description ?: '—' }}</td>
                        <td class="notranslate">{{ $group->customers_count }}</td>
                        <td><x-pill :tone="$group->status === 'active' ? 'success' : 'secondary'" :label="ucfirst($group->status)" /></td>
                        <td class="text-end text-nowrap"><button class="btn btn-sm btn-light border btn-icon" data-bs-toggle="modal" data-bs-target="#editGroup{{ $group->id }}" aria-label="Edit {{ $group->name }}" title="Edit"><i data-lucide="pencil"></i></button>@if($group->customers_count === 0) <form class="d-inline" method="POST" action="{{ route('customer-groups.destroy', $group) }}" data-confirm="Delete the customer group “{{ $group->name }}”?">@csrf @method('DELETE')<button class="btn btn-sm btn-light border btn-icon text-danger" aria-label="Delete {{ $group->name }}" title="Delete"><i data-lucide="trash-2"></i></button></form>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
    @foreach($groups as $group)
        <div class="modal fade" id="editGroup{{ $group->id }}" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" action="{{ route('customer-groups.update', $group) }}">@csrf @method('PUT')<div class="modal-header"><h5 class="modal-title">Edit customer group</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label">Group name *</label><input class="form-control" name="name" value="{{ $group->name }}" required maxlength="150"></div><div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" maxlength="1000">{{ $group->description }}</textarea></div><label class="form-label">Status *</label><select class="form-select" name="status" required><option value="active" @selected($group->status === 'active')>Active</option><option value="inactive" @selected($group->status === 'inactive')>Inactive</option></select></div><div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save changes</button></div></form></div></div>
    @endforeach

    <div class="modal fade" id="groupModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" action="{{ route('customer-groups.store') }}">@csrf<div class="modal-header"><div><h5 class="modal-title">New customer group</h5><small class="text-secondary">This name appears as a badge on customer records.</small></div><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="mb-3"><label class="form-label">Group name *</label><input class="form-control" name="name" value="{{ old('name') }}" placeholder="Enterprise Customers" required maxlength="150"></div><div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" maxlength="1000">{{ old('description') }}</textarea></div><label class="form-label">Status *</label><select class="form-select" name="status" required><option value="active">Active</option><option value="inactive">Inactive</option></select></div><div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create group</button></div></form></div></div>

    @if($errors->any())@push('scripts')<script>new bootstrap.Modal(document.getElementById('groupModal')).show();</script>@endpush @endif
</x-app-layout>
