<x-app-layout>
    <x-slot name="title">Roles & Permissions</x-slot>
    <x-slot name="pageTitle">Roles & permissions</x-slot>
    <x-slot name="breadcrumb">Administration / Access Control</x-slot>

    @php
        $canEdit = auth()->user()->can('roles.update') && $selectedRole?->name !== 'Super Admin';
        $isSystemRole = in_array($selectedRole?->name, ['Admin', 'Manager', 'Technician', 'Viewer', 'Super Admin'], true);
        $granted = $selectedRole?->permissions->pluck('name') ?? collect();
        $actions = ['read' => 'View global', 'view_own' => 'View own', 'create' => 'Create', 'update' => 'Update', 'delete' => 'Delete'];
    @endphp

    <div class="nx-role-page" data-role-editor>
        <section class="nx-card nx-access-hero">
            <div class="nx-access-hero-heading">
                <span class="nx-access-hero-icon"><i data-lucide="shield-check"></i></span>
                <div>
                    <span class="nx-access-eyebrow">ACCESS CONTROL</span>
                    <h2>Shape access around the role</h2>
                    <p>Choose one role, then grant exactly what its team needs, module by module.</p>
                </div>
                @can('roles.create')
                    <button class="btn btn-primary nx-access-create" type="button" data-bs-toggle="modal" data-bs-target="#newRole"><i data-lucide="plus"></i> Create role</button>
                @endcan
            </div>
            <div class="nx-access-role-picker">
                <div class="nx-access-select-wrap">
                    <label for="roleSelector">1. Select a role</label>
                    <select id="roleSelector" class="form-select" data-role-select>
                        @foreach($roles as $role)
                            <option value="{{ route('roles.index', ['role' => $role->id]) }}" @selected($selectedRole?->is($role))>{{ $role->name }} · {{ $role->users_count }} {{ \Illuminate\Support\Str::plural('user', $role->users_count) }}</option>
                        @endforeach
                    </select>
                </div>
                @if($selectedRole)
                    <div class="nx-access-role-facts">
                        <span><i data-lucide="users-round"></i><strong>{{ $selectedRole->users_count }}</strong> {{ \Illuminate\Support\Str::plural('member', $selectedRole->users_count) }}</span>
                        <span><i data-lucide="key-round"></i><strong data-granted-count>{{ $granted->count() }}</strong> permissions</span>
                        @if($selectedRole->name === 'Super Admin')<span class="nx-access-full"><i data-lucide="lock-keyhole"></i> Full access · read only</span>@endif
                    </div>
                @endif
            </div>
        </section>

        @if($selectedRole)
            <form id="role-permissions-{{ $selectedRole->id }}" method="POST" action="{{ route('roles.update', $selectedRole) }}" data-permission-form>
                @csrf
                @method('PUT')
                <section class="nx-card nx-access-editor-head">
                    <div class="nx-access-editor-title"><span class="nx-access-step">2</span><div><h3>Configure {{ $selectedRole->name }}</h3><p>View global opens all records. View own limits Customers, Service Jobs and Workflow/Tickets to assigned or created records.</p></div></div>
                    <div class="nx-access-role-name">
                        <label for="roleName">Role name</label>
                        @if($canEdit && ! $isSystemRole)
                            <input id="roleName" class="form-control" name="name" value="{{ old('name', $selectedRole->name) }}" maxlength="100" required>
                        @else
                            <input id="roleName" class="form-control" value="{{ $selectedRole->name }}" disabled>
                            <input type="hidden" name="name" value="{{ $selectedRole->name }}">
                        @endif
                    </div>
                </section>
                @if($errors->any())<div class="alert alert-danger" role="alert">Please review the role name and permissions, then try again.</div>@endif

                <div class="nx-access-section-list">
                    @foreach($sections as $section)
                        @php $sectionItems = collect($section['modules'])->filter(fn ($label, $module) => $permissions->has($module)); @endphp
                        @continue($sectionItems->isEmpty())
                        <section class="nx-card nx-access-section" data-permission-section aria-label="{{ $section['name'] }} permissions">
                            <div class="nx-access-section-head">
                                <div class="nx-access-section-title"><span class="nx-access-section-icon"><i data-lucide="{{ $section['icon'] }}"></i></span><div><h3>{{ $section['name'] }}</h3><p>{{ $section['description'] }}</p></div></div>
                                <label class="nx-access-select-all"><input class="form-check-input" type="checkbox" data-section-all @disabled(! $canEdit) aria-label="Select all {{ $section['name'] }} permissions"><span>Select all in {{ $section['name'] }}</span></label>
                            </div>
                            <div class="nx-access-table-scroll">
                                <table class="nx-access-table">
                                    <thead><tr><th scope="col">Submodule</th>@foreach($actions as $actionLabel)<th scope="col">{{ $actionLabel }}</th>@endforeach<th scope="col">All</th></tr></thead>
                                    <tbody>
                                        @foreach($sectionItems as $module => $label)
                                            @php
                                                $available = $permissions[$module]->pluck('name');
                                                $extras = $permissions[$module]->filter(fn ($permission) => ! in_array(str($permission->name)->after('.')->toString(), array_keys($actions), true));
                                            @endphp
                                            <tr data-permission-row data-module="{{ $module }}">
                                                <th scope="row"><span class="nx-access-module-name">{{ $label }}</span><span class="nx-access-module-key">{{ $module }}</span></th>
                                                @foreach($actions as $action => $actionLabel)
                                                    @php $key = $module.'.'.$action; @endphp
                                                    <td>
                                                        @if($available->contains($key))
                                                            <label class="nx-access-check" title="{{ $actionLabel }} · {{ $label }}"><input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $key }}" data-permission-input @checked($granted->contains($key)) @disabled(! $canEdit) aria-label="{{ $actionLabel }} for {{ $label }}"></label>
                                                        @else
                                                            <span class="nx-access-na" title="Not available for {{ $label }}">—</span>
                                                        @endif
                                                    </td>
                                                @endforeach
                                                <td><label class="nx-access-check nx-access-row-all" title="Select all {{ $label }} permissions"><input class="form-check-input" type="checkbox" data-row-all @disabled(! $canEdit) aria-label="Select all {{ $label }} permissions"></label></td>
                                            </tr>
                                            @if($extras->isNotEmpty())
                                                <tr class="nx-access-extra-row"><td colspan="7"><details class="nx-access-extra"><summary><i data-lucide="sliders-horizontal"></i> More {{ $label }} actions <span>{{ $extras->count() }}</span></summary><div class="nx-access-extra-options">
                                                    @foreach($extras as $permission)
                                                        <label class="nx-access-extra-option"><input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->name }}" data-permission-input data-module="{{ $module }}" @checked($granted->contains($permission->name)) @disabled(! $canEdit)><span>{{ str($permission->name)->after('.')->replace('_', ' ')->headline() }}</span></label>
                                                    @endforeach
                                                </div></details></td></tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    @endforeach
                </div>
                <div class="nx-access-save-bar"><span class="nx-access-save-status" data-save-status><i data-lucide="circle-check"></i> All changes saved</span>@if($canEdit)<button class="btn btn-primary" type="submit"><i data-lucide="save"></i> Save permissions</button>@endif</div>
            </form>
        @endif
    </div>

    @can('roles.create')
        <div class="modal fade" id="newRole" tabindex="-1" aria-labelledby="newRoleTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered"><form class="modal-content nx-role-create-modal" method="POST" action="{{ route('roles.store') }}">
                @csrf
                <div class="modal-header"><div><h2 class="modal-title h5 fw-bold" id="newRoleTitle">Create a role</h2><p class="small text-secondary mb-0">Name it now; choose its access on the next screen.</p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body"><label class="form-label fw-semibold" for="newRoleName">Role name</label><input class="form-control" id="newRoleName" name="name" maxlength="100" required placeholder="e.g. Service Coordinator"></div>
                <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit"><i data-lucide="plus"></i> Create & configure</button></div>
            </form></div>
        </div>
    @endcan
</x-app-layout>
