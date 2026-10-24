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

        $roles = Role::query()->withCount('users')->with('permissions')->orderBy('name')->get();
        $selectedRole = $roles->firstWhere('id', $request->integer('role')) ?? $roles->first();
        $permissions = Permission::query()->orderBy('name')->get()->groupBy(fn (Permission $permission): string => str($permission->name)->before('.')->toString());

        $sections = [
            ['name' => 'Operations', 'description' => 'Customer work, field service and daily workflows', 'icon' => 'briefcase-business', 'modules' => [
                'customers' => 'Customers', 'products' => 'Products', 'checklists' => 'Service checklists', 'service_jobs' => 'Service jobs',
                'attendance' => 'Attendance', 'tasks' => 'Workflow & tickets',
            ]],
            ['name' => 'People & finance', 'description' => 'Employee records, requests and payroll', 'icon' => 'users-round', 'modules' => [
                'employees' => 'Employees', 'hr' => 'HR', 'leaves' => 'Leave requests', 'vouchers' => 'Expense vouchers', 'payroll' => 'Payroll',
            ]],
            ['name' => 'Administration', 'description' => 'Company setup and access management', 'icon' => 'settings-2', 'modules' => [
                'companies' => 'Company master', 'users' => 'User accounts', 'roles' => 'Roles & permissions', 'settings' => 'Settings',
            ]],
            ['name' => 'Insights', 'description' => 'Reports and exports', 'icon' => 'chart-no-axes-combined', 'modules' => [
                'reports' => 'Reports',
            ]],
        ];

        $listedModules = collect($sections)->flatMap(fn (array $section) => array_keys($section['modules']));
        $otherModules = $permissions->keys()->diff($listedModules)->mapWithKeys(fn (string $module): array => [$module => str($module)->headline()->toString()])->all();
        if ($otherModules !== []) {
            $sections[] = ['name' => 'Other modules', 'description' => 'Additional permission groups', 'icon' => 'blocks', 'modules' => $otherModules];
        }

        return view('roles.index', [
            'roles' => $roles,
            'selectedRole' => $selectedRole,
            'sections' => $sections,
            'permissions' => $permissions,
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

        return redirect()->route('roles.index', ['role' => $role->id])->with('success', 'Role created. Set its permissions below.');
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
        if (in_array($role->name, ['Admin', 'Manager', 'Technician', 'Viewer'], true) && $validated['name'] !== $role->name) {
            return back()->with('error', 'System roles (Admin, Manager, Technician, Viewer) cannot be renamed because the apps rely on their names. You can still change their permissions.');
        }
        $role->update(['name' => $validated['name']]);
        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('roles.index', ['role' => $role->id])->with('success', 'Role permissions updated.');
    }
}
