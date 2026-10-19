<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('users.read'), 403);

        return view('users.index', ['users' => User::with('roles')->latest()->paginate(20), 'roles' => Role::orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('users.create'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'employee_code' => ['nullable', 'string', 'max:30', 'unique:users,employee_code'],
            'department' => ['nullable', 'string', 'max:100'],
            'designation' => ['nullable', 'string', 'max:100'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', 'exists:roles,name'],
        ]);
        $role = $data['role'];
        $this->ensureRoleCanBeAssigned($request, $role);
        unset($data['role']);
        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);
        $user->assignRole($role);

        return back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->can('users.update'), 403);
        $data = $request->validate(['status' => ['required', 'in:active,inactive'], 'role' => ['required', 'exists:roles,name']]);

        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot change your own role or account status. Ask another administrator.');
        }
        $this->ensureRoleCanBeAssigned($request, $data['role']);
        if ($user->hasRole('Super Admin') && ! $request->user()->hasRole('Super Admin')) {
            return back()->with('error', 'Only a Super Admin can change another Super Admin account.');
        }
        $user->update(['status' => $data['status']]);
        $user->syncRoles([$data['role']]);

        return back()->with('success', 'User access updated.');
    }

    private function ensureRoleCanBeAssigned(Request $request, string $role): void
    {
        abort_if($role === 'Super Admin' && ! $request->user()->hasRole('Super Admin'), 403, 'Only a Super Admin can grant the Super Admin role.');
    }
}
