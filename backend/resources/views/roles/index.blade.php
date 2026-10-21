<x-app-layout>
    <x-slot name="title">Roles & Permissions</x-slot>
    <x-slot name="pageTitle">Roles & permissions</x-slot>
    <x-slot name="breadcrumb">Administration / Access Control</x-slot>

    <div class="nx-role-page">
        <section class="nx-card nx-role-intro mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="nx-role-intro-icon"><i data-lucide="shield-check"></i></span>
                    <div>
                        <h2 class="h5 fw-bold mb-1">Role access matrix</h2>
                        <p class="small text-secondary mb-0">Review and manage permissions for each role in one place.</p>
                    </div>
                </div>
                @can('roles.create')
                    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#newRole">
                        <i data-lucide="plus" style="width:17px"></i>
                        Create role
                    </button>
                @endcan
            </div>
            <div class="nx-role-summary">
                <div><strong>{{ $roles->count() }}</strong><span>Roles</span></div>
                <div><strong>{{ $permissions->count() }}</strong><span>Permission groups</span></div>
                <div><strong>{{ $permissions->flatten()->count() }}</strong><span>Available permissions</span></div>
            </div>
        </section>

        @php
            $canUpdateRoles = auth()->user()->can('roles.update');
        @endphp

        <section class="nx-card nx-role-matrix-card" aria-label="Role permissions">
            <div class="nx-role-matrix-heading">
                <div>
                    <h3 class="h6 fw-bold mb-1">Permissions by role</h3>
                    <p class="small text-secondary mb-0">Each checkbox grants that permission to the role shown above it.</p>
                </div>
                @if($canUpdateRoles)
                    <span class="nx-role-edit-hint"><i data-lucide="info"></i>Changes are saved per role</span>
                @endif
            </div>

            <div class="nx-permission-scroll">
                <table class="nx-permission-table">
                    <thead>
                        <tr>
                            <th class="nx-permission-label-column" scope="col">
                                <span class="nx-table-eyebrow">Access control</span>
                                <span class="nx-table-title">Permission</span>
                            </th>
                            @foreach($roles as $role)
                                @php
                                    $isSuperAdmin = $role->name === 'Super Admin';
                                    $canEditRole = $canUpdateRoles && ! $isSuperAdmin;
                                    $roleFormId = 'role-permissions-'.$role->id;
                                @endphp
                                <th class="nx-role-column" scope="col">
                                    <div class="nx-role-column-card">
                                        <div class="d-flex align-items-start gap-2">
                                            <span class="nx-role-column-icon"><i data-lucide="{{ $isSuperAdmin ? 'crown' : 'shield' }}"></i></span>
                                            <div class="min-w-0 flex-grow-1">
                                                <span class="nx-table-eyebrow">{{ $role->users_count }} {{ \Illuminate\Support\Str::plural('user', $role->users_count) }}</span>
                                                @if($canEditRole)
                                                    <label class="visually-hidden" for="role-name-{{ $role->id }}">Role name</label>
                                                    <input id="role-name-{{ $role->id }}" class="nx-role-name-input" type="text" name="name" value="{{ $role->name }}" maxlength="100" required form="{{ $roleFormId }}">
                                                @else
                                                    <span class="nx-role-name">{{ $role->name }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="nx-role-column-footer">
                                            <span><strong>{{ $role->permissions->count() }}</strong> permissions</span>
                                            @if($canEditRole)
                                                <button class="btn btn-sm btn-outline-primary" type="submit" form="{{ $roleFormId }}">
                                                    <i data-lucide="save" style="width:15px"></i>
                                                    Save
                                                </button>
                                            @elseif($isSuperAdmin)
                                                <span class="nx-role-locked"><i data-lucide="lock-keyhole"></i>Full access</span>
                                            @endif
                                        </div>
                                        <form id="{{ $roleFormId }}" method="POST" action="{{ route('roles.update', $role) }}">
                                            @csrf
                                            @method('PUT')
                                        </form>
                                    </div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($permissions as $module => $items)
                            <tr class="nx-permission-group-row">
                                <th scope="row">
                                    <span class="nx-permission-group-icon"><i data-lucide="folder-key"></i></span>
                                    {{ str($module)->headline() }}
                                    <span class="nx-module-count">{{ $items->count() }}</span>
                                </th>
                                @foreach($roles as $role)
                                    <td aria-hidden="true"></td>
                                @endforeach
                            </tr>
                            @foreach($items as $permission)
                                <tr class="nx-permission-row">
                                    <th scope="row">
                                        <span class="nx-permission-name">{{ str($permission->name)->after('.')->headline() }}</span>
                                        <span class="nx-permission-key">{{ $permission->name }}</span>
                                    </th>
                                    @foreach($roles as $role)
                                        @php
                                            $roleFormId = 'role-permissions-'.$role->id;
                                            $isGranted = $role->permissions->contains('id', $permission->id);
                                            $canEditPermission = $canUpdateRoles && $role->name !== 'Super Admin';
                                        @endphp
                                        <td>
                                            <label class="nx-permission-check-cell">
                                                <input
                                                    class="form-check-input nx-permission-checkbox"
                                                    type="checkbox"
                                                    name="permissions[]"
                                                    value="{{ $permission->name }}"
                                                    form="{{ $roleFormId }}"
                                                    @checked($isGranted)
                                                    @disabled(! $canEditPermission)
                                                    aria-label="{{ str($permission->name)->headline() }} for {{ $role->name }}"
                                                >
                                                <span class="visually-hidden">{{ $isGranted ? 'Granted' : 'Not granted' }}</span>
                                            </label>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="nx-role-matrix-footer">
                <span><i class="nx-access-indicator granted"></i>Granted</span>
                <span><i class="nx-access-indicator not-granted"></i>Not granted</span>
                <span class="ms-auto">Scroll horizontally to view all roles</span>
            </div>
        </section>
    </div>

    @can('roles.create')
        <div class="modal fade" id="newRole" tabindex="-1" aria-labelledby="newRoleTitle" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <form class="modal-content nx-role-create-modal" method="POST" action="{{ route('roles.store') }}">
                    @csrf
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title h5 fw-bold" id="newRoleTitle">Create a role</h2>
                            <p class="small text-secondary mb-0">Choose a name and the permissions this role needs.</p>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="nx-role-create-name mb-4">
                            <label class="form-label" for="new-role-name">Role name <span class="text-danger">*</span></label>
                            <input class="form-control" id="new-role-name" name="name" maxlength="100" required placeholder="e.g. Service Coordinator">
                        </div>
                        <div class="nx-role-create-groups">
                            @foreach($permissions as $module => $items)
                                <fieldset class="nx-role-create-group">
                                    <legend>{{ str($module)->headline() }}</legend>
                                    <div class="nx-role-create-permissions">
                                        @foreach($items as $permission)
                                            <label class="form-check nx-role-create-permission">
                                                <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->name }}">
                                                <span class="form-check-label">{{ str($permission->name)->after('.')->headline() }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary" type="submit"><i data-lucide="shield-plus" style="width:17px"></i>Create role</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
</x-app-layout>
