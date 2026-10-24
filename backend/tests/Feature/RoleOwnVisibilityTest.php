<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ServiceJob;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleOwnVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(): User
    {
        $role = Role::create(['name' => 'Own Records Viewer', 'guard_name' => 'web']);
        $role->givePermissionTo(['customers.view_own', 'service_jobs.view_own', 'tasks.view_own']);
        $viewer = User::factory()->create();
        $viewer->assignRole($role);

        return $viewer;
    }

    public function test_customer_own_view_limits_list_and_detail_to_created_records(): void
    {
        $this->seed();
        $viewer = $this->viewer();
        $own = Customer::factory()->create(['name' => 'Viewer-owned customer', 'created_by' => $viewer->id]);
        $other = Customer::factory()->create(['name' => 'Hidden customer', 'created_by' => User::factory()->create()->id]);

        $this->actingAs($viewer)->get(route('customers.index'))->assertOk()->assertSee($own->name)->assertDontSee($other->name);
        $this->get(route('customers.show', $own))->assertOk();
        $this->get(route('customers.show', $other))->assertForbidden();
    }

    public function test_task_own_view_includes_assigned_and_created_but_not_other_tasks(): void
    {
        $this->seed();
        $viewer = $this->viewer();
        $other = User::factory()->create();
        $created = Task::create(['task_no' => 'WFL900001', 'task_type' => 'workflow', 'title' => 'Created by viewer', 'priority' => 'normal', 'created_by' => $viewer->id, 'assigned_to' => $other->id, 'status' => 'pending']);
        $assigned = Task::create(['task_no' => 'WFL900002', 'task_type' => 'workflow', 'title' => 'Assigned to viewer', 'priority' => 'normal', 'created_by' => $other->id, 'assigned_to' => $viewer->id, 'status' => 'pending']);
        $hidden = Task::create(['task_no' => 'WFL900003', 'task_type' => 'workflow', 'title' => 'Hidden from viewer', 'priority' => 'normal', 'created_by' => $other->id, 'assigned_to' => $other->id, 'status' => 'pending']);

        $this->actingAs($viewer)->get(route('tasks.index'))->assertOk()->assertSee($created->title)->assertSee($assigned->title)->assertDontSee($hidden->title);
        $this->get(route('tasks.show', $created))->assertOk();
        $this->get(route('tasks.show', $hidden))->assertForbidden();

        Sanctum::actingAs($viewer);
        $this->getJson('/api/v1/tasks')->assertOk()->assertJsonFragment(['task_no' => $created->task_no])->assertJsonFragment(['task_no' => $assigned->task_no])->assertJsonMissing(['task_no' => $hidden->task_no]);
        $this->getJson('/api/v1/tasks/'.$hidden->id)->assertForbidden();
    }

    public function test_service_job_own_view_includes_assigned_and_created_but_not_other_jobs(): void
    {
        $this->seed();
        $viewer = $this->viewer();
        $other = User::factory()->create();
        $created = ServiceJob::factory()->create(['job_no' => 'SRV-OWN-01', 'created_by' => $viewer->id, 'assigned_to' => $other->id]);
        $assigned = ServiceJob::factory()->create(['job_no' => 'SRV-OWN-02', 'created_by' => $other->id, 'assigned_to' => $viewer->id]);
        $hidden = ServiceJob::factory()->create(['job_no' => 'SRV-HIDDEN', 'created_by' => $other->id, 'assigned_to' => $other->id]);

        $this->actingAs($viewer)->get(route('service-jobs.index'))->assertOk()->assertSee($created->job_no)->assertSee($assigned->job_no)->assertDontSee($hidden->job_no);
        $this->get(route('service-jobs.show', $created))->assertOk();
        $this->get(route('service-jobs.show', $hidden))->assertForbidden();

        Sanctum::actingAs($viewer);
        $this->getJson('/api/v1/service-jobs')->assertOk()->assertJsonFragment(['job_no' => $created->job_no])->assertJsonFragment(['job_no' => $assigned->job_no])->assertJsonMissing(['job_no' => $hidden->job_no]);
        $this->getJson('/api/v1/service-jobs/'.$hidden->id)->assertForbidden();
    }

    public function test_global_view_can_see_records_not_assigned_or_created_by_the_viewer(): void
    {
        $this->seed();
        $role = Role::create(['name' => 'Global Records Viewer', 'guard_name' => 'web']);
        $role->givePermissionTo(['customers.read', 'service_jobs.read', 'tasks.read']);
        $viewer = User::factory()->create();
        $viewer->assignRole($role);
        $other = User::factory()->create();
        $customer = Customer::factory()->create(['name' => 'Visible to global viewer', 'created_by' => $other->id]);
        $job = ServiceJob::factory()->create(['job_no' => 'SRV-GLOBAL', 'customer_id' => $customer->id, 'created_by' => $other->id, 'assigned_to' => $other->id]);
        $task = Task::create(['task_no' => 'WFL900004', 'task_type' => 'workflow', 'title' => 'Global task', 'priority' => 'normal', 'created_by' => $other->id, 'assigned_to' => $other->id, 'status' => 'pending']);

        $this->actingAs($viewer)->get(route('customers.show', $customer))->assertOk();
        $this->get(route('service-jobs.show', $job))->assertOk();
        $this->get(route('tasks.show', $task))->assertOk();
    }
}
