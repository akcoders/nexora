<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('roles.read'), 403);

        return view('roles.index', [
            'roles' => Role::query()->withCount('users')->with('permissions')->orderBy('name')->get(),
            'permissions' => Permission::query()->orderBy('name')->get()->groupBy(fn (Permission $permission): string => str($permission->name)->before('.')->toString()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('roles.create'), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);
        $role = Role::create(['name' => $validated['name'], 'guard_name' => 'web']);
        $role->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', 'Role and permission set created.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_unless($request->user()->can('roles.update'), 403);
        abort_if($role->name === 'Super Admin', 422, 'Super Admin always keeps complete access.');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);
        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', 'Role permissions updated.');
    }
}
