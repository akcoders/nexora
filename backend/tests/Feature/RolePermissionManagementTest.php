<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_permissions_render_as_a_persistent_access_matrix(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $manager = Role::where('name', 'Manager')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertSee('Role access matrix')
            ->assertSee('employees.read')
            ->assertSee('id="role-permissions-'.$manager->id.'"', false)
            ->assertSee('form="role-permissions-'.$manager->id.'"', false)
            ->assertDontSee('data-bs-toggle="collapse"', false);
    }

    public function test_super_admin_can_create_a_detailed_permission_role(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();

        $this->actingAs($admin)->post(route('roles.store'), [
            'name' => 'HR Approver',
            'permissions' => ['employees.read', 'hr.read', 'leaves.approve', 'vouchers.approve', 'payroll.read'],
        ])->assertRedirect();

        $role = Role::where('name', 'HR Approver')->firstOrFail();
        $this->assertTrue($role->hasPermissionTo('leaves.approve'));
        $this->assertTrue($role->hasPermissionTo('vouchers.approve'));
    }
}
