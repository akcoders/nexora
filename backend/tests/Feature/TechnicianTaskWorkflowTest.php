<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TechnicianTaskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_technician_can_filter_open_and_close_a_task_with_remark(): void
    {
        $this->seed();
        $technician = User::where('email', 'technician@nexora.test')->firstOrFail();
        $task = Task::where('assigned_to', $technician->id)->firstOrFail();
        Setting::updateOrCreate(['key' => 'require_attendance_for_tasks'], ['value' => '0']);
        Sanctum::actingAs($technician);

        $this->getJson('/api/v1/tasks?status=pending&type=job')
            ->assertOk()
            ->assertJsonPath('data.0.task_type', 'job');

        $this->getJson('/api/v1/tasks/'.$task->id)
            ->assertOk()
            ->assertJsonStructure(['task' => ['actions'], 'assignees']);

        $this->postJson('/api/v1/tasks/'.$task->id.'/action', [
            'action' => 'close',
            'remark' => 'Service completed and readings verified.',
        ])->assertOk();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'closed']);
        $this->assertDatabaseHas('task_actions', ['task_id' => $task->id, 'action' => 'close']);
    }

    public function test_workflow_scope_keeps_every_non_closed_task_visible(): void
    {
        $this->seed();
        $technician = User::where('email', 'technician@nexora.test')->firstOrFail();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();

        $activeWorkflow = Task::create([
            'task_no' => 'TSK000099',
            'task_type' => 'workflow',
            'title' => 'Supervisor approval hand-off',
            'priority' => 'high',
            'created_by' => $admin->id,
            'assigned_to' => $technician->id,
            'status' => 'in_progress',
        ]);
        $closedWorkflow = Task::create([
            'task_no' => 'TSK000100',
            'task_type' => 'workflow',
            'title' => 'Already completed approval',
            'priority' => 'normal',
            'created_by' => $admin->id,
            'assigned_to' => $technician->id,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        Sanctum::actingAs($technician);

        $this->getJson('/api/v1/tasks?scope=open&type=workflow')
            ->assertOk()
            ->assertJsonFragment(['id' => $activeWorkflow->id, 'status' => 'in_progress'])
            ->assertJsonMissing(['task_no' => $closedWorkflow->task_no]);
    }
}
