<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_permissions_render_a_single_role_module_editor(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $manager = Role::where('name', 'Manager')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('roles.index', ['role' => $manager->id]))
            ->assertOk()
            ->assertSee('Select a role')
            ->assertSee('View global')
            ->assertSee('View own')
            ->assertSee('Select all in Operations')
            ->assertSee('Service jobs')
            ->assertSee('id="role-permissions-'.$manager->id.'"', false)
            ->assertSee('value="tasks.view_own"', false)
            ->assertDontSee('id="role-permissions-'.Role::where('name', 'Technician')->firstOrFail()->id.'"', false);
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

    public function test_permissions_save_only_for_the_selected_role(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $manager = Role::where('name', 'Manager')->firstOrFail();
        $viewer = Role::where('name', 'Viewer')->firstOrFail();

        $this->actingAs($admin)->put(route('roles.update', $manager), [
            'name' => 'Manager',
            'permissions' => ['customers.view_own', 'tasks.view_own', 'tasks.create'],
        ])->assertRedirect(route('roles.index', ['role' => $manager->id]));

        $this->assertTrue($manager->fresh()->hasPermissionTo('tasks.view_own'));
        $this->assertFalse($manager->fresh()->hasPermissionTo('tasks.read'));
        $this->assertFalse($viewer->fresh()->hasPermissionTo('tasks.view_own'));
    }

    public function test_new_permission_modules_remain_visible_in_the_editor(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        Permission::findOrCreate('inventory.read', 'web');

        $this->actingAs($admin)->get(route('roles.index'))
            ->assertOk()
            ->assertSee('Other modules')
            ->assertSee('value="inventory.read"', false);
    }

    public function test_super_admin_permissions_cannot_be_edited(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $superAdmin = Role::where('name', 'Super Admin')->firstOrFail();

        $this->actingAs($admin)->put(route('roles.update', $superAdmin), [
            'name' => 'Super Admin', 'permissions' => [],
        ])->assertRedirect();

        $this->assertTrue($superAdmin->fresh()->hasPermissionTo('customers.read'));
    }
}
