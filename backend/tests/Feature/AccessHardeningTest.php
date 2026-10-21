<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\CustomerGroup;
use App\Models\Premises;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function technician(): User
    {
        return User::role('Technician')->where('email', 'not like', 'demo.%')->firstOrFail();
    }

    public function test_technician_cannot_escalate_role_or_change_settings_or_masters(): void
    {
        $this->seed();
        $technician = $this->technician();

        $this->actingAs($technician)->put(route('users.update', $technician), ['status' => 'active', 'role' => 'Super Admin'])->assertForbidden();
        $this->actingAs($technician)->post(route('users.store'), ['name' => 'X', 'email' => 'x@x.test', 'password' => 'Password123!', 'role' => 'Admin'])->assertForbidden();
        $this->actingAs($technician)->put(route('settings.update'), ['company_name' => 'Hacked'])->assertForbidden();
        $this->actingAs($technician)->get(route('settings.index'))->assertForbidden();
        $this->actingAs($technician)->post(route('products.store'), ['name' => 'Rogue product'])->assertForbidden();
        $this->actingAs($technician)->post(route('premises.store'), ['name' => 'Rogue', 'latitude' => 1, 'longitude' => 1, 'radius_meters' => 50])->assertForbidden();
        $this->actingAs($technician)->post(route('customers.store'), ['name' => 'Rogue customer'])->assertForbidden();
        $this->assertFalse($technician->fresh()->hasRole('Super Admin'));
    }

    public function test_admin_cannot_grant_super_admin_or_change_own_access(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $admin->syncRoles(['Admin']);
        $technician = $this->technician();

        $this->actingAs($admin)->put(route('users.update', $technician), ['status' => 'active', 'role' => 'Super Admin'])->assertForbidden();
        $this->actingAs($admin)->from(route('users.index'))->put(route('users.update', $admin), ['status' => 'inactive', 'role' => 'Technician'])
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('error');
        $this->assertSame('active', $admin->fresh()->status);
        $this->assertTrue($admin->fresh()->hasRole('Admin'));
    }

    public function test_inactive_users_cannot_sign_in_to_the_web_app(): void
    {
        $this->seed();
        $technician = $this->technician();
        $technician->update(['status' => 'suspended']);

        $this->post(route('login'), ['email' => $technician->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_attendance_can_only_be_reviewed_once_and_business_rule_aborts_flash_back(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $attendance = Attendance::firstOrFail();
        $attendance->update(['status' => 'pending']);

        $this->actingAs($admin)->post(route('attendance.review', $attendance), ['decision' => 'approved', 'remark' => 'ok'])->assertRedirect();
        $this->assertSame('approved', $attendance->fresh()->status);
        $this->actingAs($admin)->from(route('attendance.index'))->post(route('attendance.review', $attendance), ['decision' => 'rejected', 'remark' => 'flip'])
            ->assertRedirect(route('attendance.index'))
            ->assertSessionHas('error');
        $this->assertSame('approved', $attendance->fresh()->status);

        $group = CustomerGroup::where('code', 'GRP0001')->firstOrFail();
        $this->actingAs($admin)->from(route('customer-groups.index'))->delete(route('customer-groups.destroy', $group))
            ->assertRedirect(route('customer-groups.index'))
            ->assertSessionHas('error');
        $this->assertNotNull(CustomerGroup::find($group->id));
    }

    public function test_deleted_customer_group_name_cannot_collide_with_the_database_index(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $group = CustomerGroup::create(['code' => 'GRP-TMP', 'name' => 'Retail', 'status' => 'active']);
        $group->delete();

        $this->actingAs($admin)->from(route('customer-groups.index'))->post(route('customer-groups.store'), ['name' => 'Retail', 'status' => 'active'])
            ->assertRedirect(route('customer-groups.index'))
            ->assertSessionHasErrors('name');
    }

    public function test_task_actions_are_limited_to_assignee_creator_or_task_managers(): void
    {
        $this->seed();
        $technician = $this->technician();
        $other = User::factory()->create(['status' => 'active']);
        $task = Task::where('assigned_to', $technician->id)->firstOrFail();

        $this->actingAs($other)->post(route('tasks.action', $task), ['action' => 'close', 'remark' => 'not mine'])->assertForbidden();
        $this->actingAs($technician)->post(route('tasks.action', $task), ['action' => 'close', 'remark' => 'done'])->assertRedirect();
        $this->assertSame('closed', $task->fresh()->status);
        $this->actingAs($technician)->from(route('tasks.show', $task))->post(route('tasks.action', $task), ['action' => 'close', 'remark' => 'again'])
            ->assertRedirect(route('tasks.show', $task))
            ->assertSessionHas('error');
        $this->assertSame(1, $task->actions()->where('action', 'close')->count());
        $this->assertNotNull(Premises::first());
    }
}
