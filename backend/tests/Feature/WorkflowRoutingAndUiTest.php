<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class WorkflowRoutingAndUiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, User, User, Task, Task} */
    private function workflow(): array
    {
        $this->seed();
        Setting::updateOrCreate(['key' => 'require_attendance_for_tasks'], ['value' => '0']);
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $creator = User::where('email', 'technician@nexora.test')->firstOrFail();
        $worker = User::factory()->create(['status' => 'active']);
        $worker->assignRole('Technician');
        $parent = Task::create([
            'task_no' => 'WFLRETURN001', 'task_type' => Task::TYPE_WORKFLOW, 'title' => 'Parent approval',
            'created_by' => $admin->id, 'assigned_to' => $worker->id, 'status' => 'in_progress', 'priority' => 'normal', 'due_at' => '2026-11-15 17:00:00',
        ]);
        $child = Task::create([
            'task_no' => 'WFLRETURN002', 'task_type' => Task::TYPE_WORKFLOW, 'title' => 'Check delivery',
            'parent_task_id' => $parent->id, 'created_by' => $creator->id, 'assigned_to' => $worker->id, 'status' => 'pending', 'priority' => 'normal',
        ]);

        return [$admin, $creator, $worker, $parent, $child];
    }

    #[TestWith(['close'])]
    #[TestWith(['update'])]
    public function test_completing_a_subtask_returns_it_and_the_parent_to_the_subtask_creator(string $action): void
    {
        [$admin, $creator, $worker, $parent, $child] = $this->workflow();
        Notification::fake([TaskAssignedNotification::class, TaskCompletedNotification::class]);
        Sanctum::actingAs($worker);

        $this->postJson('/api/v1/tasks/'.$child->id.'/action', ['action' => $action, 'status' => 'closed', 'remark' => 'Delivery checked.'])
            ->assertOk()->assertJsonPath('task.assigned_to', $creator->id)->assertJsonPath('task.status', 'closed');

        $this->assertDatabaseHas('tasks', ['id' => $parent->id, 'assigned_to' => $creator->id, 'status' => 'pending', 'due_at' => '2026-11-15 17:00:00']);
        $this->assertNotNull($child->fresh()->closed_at);
        $this->assertDatabaseHas('task_members', ['task_id' => $child->id, 'user_id' => $worker->id, 'role' => 'previous_assignee']);
        $this->assertDatabaseHas('task_members', ['task_id' => $parent->id, 'user_id' => $worker->id, 'role' => 'previous_assignee']);
        $this->assertDatabaseHas('task_actions', ['task_id' => $parent->id, 'related_task_id' => $child->id, 'action' => 'returned', 'assigned_to' => $creator->id]);
        $this->assertDatabaseHas('task_actions', ['task_id' => $child->id, 'action' => 'close', 'user_id' => $worker->id]);
        Notification::assertSentTo($creator, TaskAssignedNotification::class, fn ($notification): bool => $notification->task->id === $parent->id && $notification->title === 'Workflow returned to you');
        Notification::assertSentTo([$creator, $worker, $admin], TaskCompletedNotification::class);
        Sanctum::actingAs($creator);
        $this->getJson('/api/v1/tasks/'.$parent->id)->assertOk();
        $this->postJson('/api/v1/tasks/'.$child->id.'/action', ['action' => 'close', 'remark' => 'Repeated completion.'])->assertUnprocessable();
        Notification::assertSentToTimes($creator, TaskAssignedNotification::class, 1);
    }

    public function test_nested_completion_returns_active_ancestors_without_reopening_closed_workflows(): void
    {
        [$admin, $creator, $worker, $parent, $child] = $this->workflow();
        $root = Task::create([
            'task_no' => 'WFLRETURN003', 'task_type' => Task::TYPE_WORKFLOW, 'title' => 'Completed root',
            'created_by' => $admin->id, 'assigned_to' => $admin->id, 'status' => 'closed', 'priority' => 'normal', 'closed_at' => now(),
        ]);
        $parent->update(['parent_task_id' => $root->id]);

        $this->actingAs($worker)->postJson(route('tasks.action', $child), ['action' => 'close', 'remark' => 'Verified.'])->assertOk();

        $this->assertDatabaseHas('tasks', ['id' => $parent->id, 'assigned_to' => $creator->id, 'status' => 'pending']);
        $this->assertDatabaseHas('tasks', ['id' => $root->id, 'assigned_to' => $admin->id, 'status' => 'closed']);
    }

    public function test_past_dates_are_refused_on_creation_and_follow_up_updates(): void
    {
        [$admin, $creator, $worker, $parent, $child] = $this->workflow();
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(12, 0));
        $this->actingAs($admin);
        $data = ['title' => 'Invalid past date', 'priority' => 'normal', 'assigned_to' => $worker->id, 'due_at' => '2026-10-31 17:00:00'];

        $this->postJson(route('workflows.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('due_at');
        $this->postJson(route('tasks.subtasks.store', $parent), $data)->assertUnprocessable()->assertJsonValidationErrors('due_at');
        $this->postJson(route('tasks.action', $parent), ['action' => 'update', 'status' => 'pending', 'remark' => 'Follow up.', 'next_follow_up_at' => $data['due_at']])
            ->assertUnprocessable()->assertJsonValidationErrors('next_follow_up_at');
        $this->postJson(route('tasks.due-date', $parent), ['due_at' => $data['due_at'], 'reason' => 'Waiting.'])->assertUnprocessable()->assertJsonValidationErrors('due_at');

        $this->assertDatabaseMissing('tasks', ['title' => 'Invalid past date']);
        $this->assertSame('2026-11-15 17:00:00', $parent->fresh()->due_at->format('Y-m-d H:i:s'));
        $this->assertSame(0, $parent->actions()->count());
        $this->postJson(route('tasks.action', $parent), ['action' => 'update', 'status' => 'pending', 'remark' => 'Today is available.', 'next_follow_up_at' => '2026-11-01 17:00:00'])->assertOk();
    }

    public function test_a_progress_remark_keeps_an_existing_overdue_date_unchanged(): void
    {
        [$admin, $creator, $worker, $parent, $child] = $this->workflow();
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(12, 0));
        $parent->update(['due_at' => '2026-10-31 17:00:00']);

        $this->actingAs($worker)->postJson(route('tasks.action', $parent), ['action' => 'update', 'status' => 'in_progress', 'remark' => 'Following up today.'])
            ->assertOk();

        $this->assertSame('2026-10-31 17:00:00', $parent->fresh()->due_at->format('Y-m-d H:i:s'));
    }

    public function test_observer_picker_avatars_and_date_controls_render_with_safe_names_and_current_date_limits(): void
    {
        [$admin, $creator, $worker, $parent, $child] = $this->workflow();
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(12, 0));
        $creator->update(['name' => 'Observer <script>alert(1)</script>']);
        $parent->members()->create(['user_id' => $creator->id, 'role' => 'observer']);
        $this->actingAs($admin);

        $response = $this->withSession(['_old_input' => ['observers' => [$creator->id]]])->get(route('workflows.index'))
            ->assertOk()->assertSee('data-observer-picker', false)->assertSee('Search people by name or role')
            ->assertSee('type="date"', false)->assertSee('min="2026-11-01"', false)->assertSee('type="time"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//input[@name="observers[]" and @value="'.$creator->id.'" and @checked]')->length);
        $this->assertSame(0, $xpath->query('//select[@name="observers[]"]')->length);
        $this->get(route('tasks.show', $parent))->assertSee('nx-person-avatar', false)->assertSee('nx-person-tooltip', false)->assertSee('Observer &lt;script&gt;', false);
        $this->getJson(route('workflows.board'))->assertOk()->assertJsonFragment(['id' => $creator->id, 'name' => $creator->name]);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_subtask_creation_inherits_parent_observers_and_records_one_combined_event(bool $api): void
    {
        [$admin, $creator, $worker, $parent, $child] = $this->workflow();
        $this->freezeTime();
        $observer = User::factory()->create(['status' => 'active']);
        $outsider = User::factory()->create(['status' => 'active']);
        $parent->members()->create(['user_id' => $creator->id, 'role' => 'observer']);
        $parent->members()->create(['user_id' => $observer->id, 'role' => 'observer']);
        Sanctum::actingAs($worker);
        $this->actingAs($worker);
        $url = $api ? '/api/v1/tasks/'.$parent->id.'/subtasks' : route('tasks.subtasks.store', $parent);

        $this->postJson($url, [
            'title' => 'Arrange delivery', 'priority' => 'high', 'assigned_to' => $admin->id,
            'due_at' => now()->addDays(3)->setTime(17, 0)->format('Y-m-d H:i:s'),
            'category' => 'Unexpected category', 'observers' => [$outsider->id],
        ])->assertCreated();

        $created = $parent->subtasks()->where('title', 'Arrange delivery')->sole();
        $this->assertSame('Internal', $created->category);
        $this->assertSame([$creator->id, $observer->id], $created->observers()->orderBy('user_id')->pluck('user_id')->all());
        $this->assertDatabaseMissing('task_members', ['task_id' => $created->id, 'user_id' => $outsider->id]);
        $this->assertDatabaseHas('tasks', ['id' => $parent->id, 'assigned_to' => $admin->id]);
        $this->assertSame(1, $created->actions()->count());
        $event = $parent->actions()->where('related_task_id', $created->id)->sole();
        $this->assertSame('created', $event->action);
        $this->assertSame($worker->id, $event->user_id);
        $this->assertSame($admin->id, $event->assigned_to);
        $this->assertStringContainsString('Responsible Person: '.$admin->name, $event->remark);
        $this->assertStringContainsString('Priority: High.', $event->remark);
        $this->assertStringContainsString('Observers inherited: 2.', $event->remark);
        $this->get(route('tasks.show', $parent))->assertSee('Subtask created and assigned')->assertDontSee('Workflow assigned to the owner of subtask');
    }

    public function test_subtask_form_has_read_only_inherited_observers_hidden_category_and_an_attachment_switch(): void
    {
        [$admin, $creator, $worker, $parent, $child] = $this->workflow();
        $parent->members()->create(['user_id' => $creator->id, 'role' => 'observer']);

        $response = $this->actingAs($worker)->get(route('tasks.show', $parent))->assertOk()
            ->assertSee('Inherited from '.$parent->task_no)->assertSee('Responsible Person')
            ->assertDontSee('Current assignee')->assertDontSee('data-observer-picker', false);

        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//form[@id="workItemForm"]//input[@name="observers[]"]')->length);
        $this->assertSame(1, $xpath->query('//form[@id="workItemForm"]//input[@type="hidden" and @name="category" and @value="Internal"]')->length);
        $this->assertSame(1, $xpath->query('//input[@id="completionAttachment" and @role="switch"]')->length);
    }
}
