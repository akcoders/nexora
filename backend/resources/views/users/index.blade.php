<x-app-layout>
    <x-slot name="title">User accounts</x-slot>
    <x-slot name="pageTitle">User accounts</x-slot>
    <x-slot name="breadcrumb">Administration / User accounts</x-slot>

    <div class="nx-page-header">
        <div><div class="nx-page-kicker">Administration</div><h2>Team access</h2><p>Login accounts, roles and access status. Employee details are managed from the employee master.</p></div>
        <div class="nx-page-actions">
            <a class="btn btn-light border" href="{{ route('roles.index') }}"><i data-lucide="shield-check"></i>Roles & permissions</a>
            @can('users.create')<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal"><i data-lucide="user-plus"></i>New user</button>@endcan
        </div>
    </div>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-kpi label="Accounts" :value="$users->count()" icon="users-round" tone="primary" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Active" :value="$users->where('status', 'active')->count()" icon="badge-check" tone="success" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Inactive" :value="$users->where('status', '!=', 'active')->count()" icon="user-x" tone="secondary" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Roles in use" :value="$users->flatMap->roles->pluck('name')->unique()->count()" icon="shield-check" tone="purple" /></div>
    </div>

    <section class="nx-card nx-datatable-card">
        <table class="table nx-table" data-datatable data-dt-title="User accounts" data-dt-empty="No users yet.">
            <thead><tr><th data-priority="1">Name</th><th>Employee code</th><th>Email</th><th>Phone</th><th>Department</th><th>Designation</th><th data-priority="2">Role</th><th data-priority="3">Status</th><th>Created</th><th class="no-export no-sort text-end" data-orderable="false"></th></tr></thead>
            <tbody>
                @foreach($users as $user)
                    <tr>
                        <td><div class="d-flex align-items-center gap-2"><span class="nx-avatar notranslate" style="width:32px;height:32px;font-size:12px">{{ strtoupper(substr($user->name, 0, 1)) }}</span><span class="nx-row-title notranslate text-nowrap">{{ $user->name }}</span></div></td>
                        <td class="nx-code notranslate">{{ $user->employee_code ?: '—' }}</td>
                        <td class="notranslate">{{ $user->email }}</td>
                        <td class="notranslate">{{ $user->phone ?: '—' }}</td>
                        <td>{{ $user->department ?: '—' }}</td>
                        <td>{{ $user->designation ?: '—' }}</td>
                        <td><span class="nx-tag">{{ $user->getRoleNames()->first() ?? 'Unassigned' }}</span></td>
                        <td><x-pill :tone="$user->status === 'active' ? 'success' : 'secondary'" :label="ucfirst($user->status)" /></td>
                        <td data-order="{{ $user->created_at?->timestamp }}">{{ $user->created_at?->format('d M Y') }}</td>
                        <td class="text-end">@can('users.update')<button class="btn btn-sm btn-light border btn-icon" data-bs-toggle="modal" data-bs-target="#manageUserModal" data-manage-url="{{ route('users.update', $user) }}" data-manage-name="{{ $user->name }}" data-manage-role="{{ $user->getRoleNames()->first() }}" data-manage-status="{{ $user->status }}" aria-label="Manage access for {{ $user->name }}" title="Manage access"><i data-lucide="user-cog"></i></button>@endcan</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    @can('users.create')
    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalTitle"><div class="modal-dialog modal-lg modal-dialog-centered"><form method="POST" action="{{ route('users.store') }}" class="modal-content">@csrf
        <div class="modal-header"><h5 class="modal-title" id="userModalTitle">Create user account</h5><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><div class="row g-3">
            <div class="col-md-6"><label class="form-label">Full name <span class="text-danger">*</span></label><input class="form-control" name="name" value="{{ old('name') }}" required data-v="person_name" maxlength="255"></div>
            <div class="col-md-6"><label class="form-label">Email <span class="text-danger">*</span></label><input type="email" class="form-control" name="email" value="{{ old('email') }}" required></div>
            <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="phone" value="{{ old('phone') }}"></div>
            <div class="col-md-4"><label class="form-label">Employee code</label><input class="form-control" name="employee_code" value="{{ old('employee_code') }}" data-v="alpha_dash" maxlength="30"></div>
            <div class="col-md-4"><label class="form-label">Role <span class="text-danger">*</span></label><select class="form-select" name="role" required>@foreach($roles as $role)<option @selected(old('role') === $role->name)>{{ $role->name }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Department</label><input class="form-control" name="department" value="{{ old('department') }}" maxlength="100"></div>
            <div class="col-md-6"><label class="form-label">Designation</label><input class="form-control" name="designation" value="{{ old('designation') }}" maxlength="100"></div>
            <div class="col-12"><label class="form-label">Temporary password <span class="text-danger">*</span></label><input type="password" class="form-control" name="password" required minlength="8" autocomplete="new-password"></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create user</button></div>
    </form></div></div>
    @endcan

    @can('users.update')
    <div class="modal fade" id="manageUserModal" tabindex="-1" aria-labelledby="manageUserTitle"><div class="modal-dialog modal-dialog-centered"><form method="POST" action="#" class="modal-content" data-manage-form>@csrf @method('PUT')
        <div class="modal-header"><div><h5 class="modal-title" id="manageUserTitle">Manage access</h5><small class="text-secondary notranslate" data-manage-label></small></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><label class="form-label">Role</label><select class="form-select mb-3" name="role" required data-manage-role-input>@foreach($roles as $role)<option>{{ $role->name }}</option>@endforeach</select><label class="form-label">Account status</label><select class="form-select" name="status" required data-manage-status-input><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
        <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save access</button></div>
    </form></div></div>
    @push('scripts')<script>
        document.getElementById('manageUserModal')?.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget, form = event.target.querySelector('[data-manage-form]');
            if (!trigger || !form) return;
            form.action = trigger.dataset.manageUrl;
            form.querySelector('[data-manage-label]').textContent = trigger.dataset.manageName;
            form.querySelector('[data-manage-role-input]').value = trigger.dataset.manageRole || form.querySelector('[data-manage-role-input]').value;
            form.querySelector('[data-manage-status-input]').value = trigger.dataset.manageStatus === 'active' ? 'active' : 'inactive';
        });
    </script>@endpush
    @endcan
</x-app-layout>
