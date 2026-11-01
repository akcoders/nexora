<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Task;
use App\Models\TaskMember;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SubtaskVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, User, User, Task} */
    private function workflow(): array
    {
        $this->seed();
        Setting::updateOrCreate(['key' => 'require_attendance_for_tasks'], ['value' => '0']);
        $admin = User::where('email', 'admin@nexora.test')->sole();
        $creator = User::where('email', 'technician@nexora.test')->sole();
        $worker = User::factory()->create(['status' => 'active']);
        $worker->assignRole('Technician');
        $parent = Task::create([
            'task_no' => 'WFLPRIVATE001', 'task_type' => Task::TYPE_WORKFLOW, 'title' => 'Confidential main workflow',
            'created_by' => $admin->id, 'assigned_to' => $creator->id, 'status' => 'pending', 'priority' => 'normal',
        ]);

        return [$admin, $creator, $worker, $parent];
    }

    private function createSubtask(Task $parent, User $creator, User $worker, string $title = 'Check delivery'): Task
    {
        $this->actingAs($creator)->postJson(route('tasks.subtasks.store', $parent), [
            'title' => $title, 'assigned_to' => $worker->id, 'priority' => 'normal',
        ])->assertCreated();

        return $parent->subtasks()->where('title', $title)->sole();
    }

    public function test_the_assignee_sees_only_the_subtask_in_web_api_board_and_dashboard(): void
    {
        [$admin, $creator, $worker, $parent] = $this->workflow();
        Notification::fake([TaskAssignedNotification::class]);
        $child = $this->createSubtask($parent, $creator, $worker);
        $this->assertSame($worker->id, $parent->fresh()->assigned_to);
        $this->actingAs($worker)->get(route('workflows.index'))->assertOk()->assertSee($child->title)->assertDontSee($parent->title);
        $this->get(route('dashboard'))->assertOk()->assertDontSee($parent->title);
        $this->get(route('tasks.show', $child))->assertOk()->assertDontSee($parent->title);
        $this->get(route('tasks.show', $parent))->assertForbidden();
        $cards = $this->getJson(route('workflows.board'))->assertOk()->json('tasks');
        $this->assertNotContains($parent->id, array_column($cards, 'id'));
        $this->assertNull(collect($cards)->firstWhere('id', $child->id)['parent']);
        $this->postJson(route('tasks.action', $parent), ['action' => 'update', 'status' => 'pending', 'remark' => 'Cannot act on the parent.'])->assertForbidden();
        $this->postJson(route('tasks.priority', $parent), ['priority' => 'high'])->assertForbidden();
        $this->get(route('tasks.attachments.show', [$parent, $parent->actions()->firstOrFail(), 0]))->assertForbidden();
        Notification::assertNotSentTo($worker, TaskAssignedNotification::class, fn ($notification): bool => $notification->task->id === $parent->id);
        Sanctum::actingAs($worker);
        $tasks = $this->getJson('/api/v1/tasks?type=workflow')->assertOk()->json('data');
        $this->assertNotContains($parent->id, array_column($tasks, 'id'));
        $this->assertNull(collect($tasks)->firstWhere('id', $child->id)['parent']);
        $this->getJson('/api/v1/tasks/'.$parent->id)->assertForbidden();
        $this->getJson('/api/v1/tasks/'.$child->id)->assertOk()->assertJsonPath('task.parent', null)->assertJsonPath('task.status', 'pending');
        $this->postJson('/api/v1/tasks/'.$child->id.'/action', ['action' => 'update', 'status' => 'pending', 'remark' => 'Delivery checked.'])->assertOk()->assertJsonPath('task.parent', null);
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/tasks/'.$parent->id)->assertOk();
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_completion_returns_ownership_to_the_subtask_creator_and_keeps_the_parent_hidden_from_the_worker(bool $api): void
    {
        [$admin, $creator, $worker, $parent] = $this->workflow();
        Notification::fake([TaskAssignedNotification::class, TaskCompletedNotification::class]);
        $child = $this->createSubtask($parent, $creator, $worker);
        $parent->members()->firstOrCreate(['user_id' => $creator->id, 'role' => TaskMember::ROLE_SUBTASK_ONLY]);
        $this->actingAs($worker);
        Sanctum::actingAs($worker);
        $url = $api ? '/api/v1/tasks/'.$child->id.'/action' : route('tasks.action', $child);
        $this->postJson($url, ['action' => 'update', 'status' => 'closed', 'remark' => 'Delivery completed.'])->assertOk();

        $this->assertDatabaseHas('tasks', ['id' => $child->id, 'status' => 'closed', 'assigned_to' => $creator->id]);
        $this->assertDatabaseHas('tasks', ['id' => $parent->id, 'status' => 'pending', 'assigned_to' => $creator->id]);
        $this->assertDatabaseMissing('task_members', ['task_id' => $parent->id, 'user_id' => $creator->id, 'role' => TaskMember::ROLE_SUBTASK_ONLY]);
        $this->get(route('tasks.show', $parent))->assertForbidden();
        $this->getJson('/api/v1/tasks/'.$parent->id)->assertForbidden();
        $this->getJson('/api/v1/tasks/'.$child->id)->assertOk()->assertJsonPath('task.parent', null)->assertJsonPath('task.status', 'closed');
        Notification::assertSentTo($worker, TaskCompletedNotification::class, fn ($notification): bool => $notification->toArray($worker)['task_id'] === $child->id);
        Notification::assertSentTo($creator, TaskCompletedNotification::class, fn ($notification): bool => $notification->toArray($creator)['task_id'] === $parent->id);
        Notification::assertSentTo($creator, TaskAssignedNotification::class, fn ($notification): bool => $notification->task->id === $parent->id && $notification->title === 'Workflow returned to you');
        Sanctum::actingAs($creator);
        $this->actingAs($creator)->get(route('tasks.show', $parent))->assertOk()->assertSee('Delivery completed.');
        $this->getJson('/api/v1/tasks/'.$parent->id)->assertOk();
    }

    public function test_reassigning_a_subtask_does_not_grant_either_worker_parent_or_sibling_access(): void
    {
        [$admin, $creator, $worker, $parent] = $this->workflow();
        $child = $this->createSubtask($parent, $creator, $worker);
        $next = User::factory()->create(['status' => 'active']);
        $next->assignRole('Technician');
        $sibling = $this->createSubtask($parent, $admin, $next, 'Separate private subtask');
        $this->actingAs($worker)->get(route('tasks.show', $sibling))->assertForbidden();
        $this->postJson(route('tasks.action', $child), ['action' => 'update', 'status' => 'pending', 'reassign' => 1, 'assigned_to' => $next->id, 'remark' => 'Please finish this.'])->assertOk();
        $this->get(route('tasks.show', $parent))->assertForbidden();
        $this->actingAs($next)->get(route('tasks.show', $child))->assertOk()->assertDontSee($parent->title);
        $this->get(route('tasks.show', $parent))->assertForbidden();
        $this->assertDatabaseHas('tasks', ['id' => $child->id, 'assigned_to' => $next->id, 'status' => 'pending']);
    }

    public function test_a_nested_subtask_hides_all_ancestors_until_completion_routes_them_to_its_creator(): void
    {
        [$admin, $creator, $worker, $parent] = $this->workflow();
        $child = $this->createSubtask($parent, $creator, $worker);
        $next = User::factory()->create(['status' => 'active']);
        $next->assignRole('Technician');
        $grandchild = $this->createSubtask($child, $worker, $next, 'Nested delivery check');
        $this->actingAs($next)->get(route('tasks.show', $grandchild))->assertOk()->assertDontSee($child->title)->assertDontSee($parent->title);
        $this->get(route('tasks.show', $child))->assertForbidden();
        $this->get(route('tasks.show', $parent))->assertForbidden();
        $this->postJson(route('tasks.action', $grandchild), ['action' => 'close', 'remark' => 'Nested check completed.'])->assertOk();
        $this->assertDatabaseHas('tasks', ['id' => $child->id, 'assigned_to' => $worker->id, 'status' => 'pending']);
        $this->assertDatabaseHas('tasks', ['id' => $parent->id, 'assigned_to' => $worker->id, 'status' => 'pending']);
        $this->actingAs($worker)->get(route('tasks.show', $child))->assertOk();
        $this->get(route('tasks.show', $parent))->assertOk();
    }

    public function test_creating_a_nested_subtask_for_yourself_keeps_existing_ancestor_restrictions(): void
    {
        [$admin, $creator, $worker, $parent] = $this->workflow();
        $child = $this->createSubtask($parent, $creator, $worker);
        $this->createSubtask($child, $worker, $worker, 'My nested check');
        $this->actingAs($worker)->get(route('tasks.show', $parent))->assertForbidden();
        $this->get(route('tasks.show', $child))->assertOk();
    }

    public function test_existing_in_progress_records_and_legacy_parent_grants_are_normalized_by_the_migration(): void
    {
        [$admin, $creator, $worker, $parent] = $this->workflow();
        $child = Task::create(['task_no' => 'WFLPRIVATE002', 'title' => 'Existing subtask', 'task_type' => 'workflow', 'parent_task_id' => $parent->id, 'created_by' => $creator->id, 'assigned_to' => $worker->id, 'status' => 'pending', 'priority' => 'normal']);
        DB::table('tasks')->whereIn('id', [$parent->id, $child->id])->update(['status' => 'in_progress', 'assigned_to' => $worker->id]);
        $parent->members()->create(['user_id' => $worker->id, 'role' => 'observer']);
        $formerWorker = User::factory()->create(['status' => 'active']);
        $formerWorker->assignRole('Technician');
        $completed = Task::create(['task_no' => 'WFLPRIVATE003', 'title' => 'Previously completed subtask', 'task_type' => 'workflow', 'parent_task_id' => $parent->id, 'created_by' => $creator->id, 'assigned_to' => $creator->id, 'status' => 'closed', 'priority' => 'normal']);
        $completed->members()->create(['user_id' => $formerWorker->id, 'role' => 'previous_assignee']);
        $parent->members()->create(['user_id' => $formerWorker->id, 'role' => 'observer']);
        $migration = require database_path('migrations/2026_11_01_104057_normalize_workflow_statuses_and_subtask_visibility.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseHas('tasks', ['id' => $parent->id, 'status' => 'pending']);
        $this->assertDatabaseHas('tasks', ['id' => $child->id, 'status' => 'pending']);
        $this->assertSame(1, $parent->subtaskOnlyMembers()->where('user_id', $worker->id)->count());
        $this->assertSame(1, $parent->subtaskOnlyMembers()->where('user_id', $formerWorker->id)->count());
        $this->actingAs($worker)->get(route('tasks.show', $parent))->assertForbidden();
        $this->get(route('tasks.show', $child))->assertOk()->assertDontSee($parent->title);
        $this->actingAs($formerWorker)->get(route('tasks.show', $parent))->assertForbidden();
        $this->get(route('tasks.show', $completed))->assertOk()->assertDontSee($parent->title);
    }

    public function test_a_dashboard_viewer_without_task_permissions_does_not_receive_task_data(): void
    {
        [$admin, $creator, $worker, $parent] = $this->workflow();
        $viewer = User::factory()->create(['status' => 'active']);
        $response = $this->actingAs($viewer)->get(route('dashboard'))->assertOk()->assertDontSee($parent->title);
        $this->assertCount(0, $response->viewData('recentTasks'));
        $this->assertSame(0, $response->viewData('metrics')['pendingTasks']);
    }

    public function test_only_pending_and_completed_are_offered_and_old_clients_save_in_progress_as_pending(): void
    {
        [$admin, $creator, $worker, $parent] = $this->workflow();
        $response = $this->actingAs($creator)->get(route('tasks.show', $parent))->assertOk()->assertDontSee('In progress');
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(2, $xpath->query('//select[@id="workflowStatus"]/option')->length);
        Sanctum::actingAs($creator);
        $this->postJson('/api/v1/tasks/'.$parent->id.'/action', ['action' => 'update', 'status' => 'in_progress', 'remark' => 'Still working.'])->assertOk()->assertJsonPath('task.status', 'pending');
        $this->assertDatabaseHas('tasks', ['id' => $parent->id, 'status' => 'pending']);
        $tasks = $this->getJson('/api/v1/tasks?status=in_progress&type=workflow')->assertOk()->json('data');
        $this->assertContains($parent->id, array_column($tasks, 'id'));
        $this->assertSame(['pending'], array_values(array_unique(array_column($tasks, 'status'))));
    }
}
