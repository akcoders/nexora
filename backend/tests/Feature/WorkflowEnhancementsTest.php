<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class WorkflowEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, User} */
    private function users(): array
    {
        $this->seed();
        Setting::updateOrCreate(['key' => 'require_attendance_for_tasks'], ['value' => '0']);

        return [User::where('email', 'admin@nexora.test')->firstOrFail(), User::where('email', 'technician@nexora.test')->firstOrFail()];
    }

    /** @param array<string, mixed> $attributes */
    private function workflow(User $creator, User $owner, array $attributes = []): Task
    {
        return Task::create(array_merge([
            'task_no' => 'WFLTEST'.((Task::withTrashed()->max('id') ?? 0) + 1),
            'task_type' => Task::TYPE_WORKFLOW, 'title' => 'Internal approval', 'priority' => 'normal',
            'created_by' => $creator->id, 'assigned_to' => $owner->id, 'status' => 'pending',
        ], $attributes));
    }

    public function test_creation_saves_the_completion_attachment_option(): void
    {
        [$admin, $owner] = $this->users();

        $this->actingAs($admin)->post(route('workflows.store'), [
            'title' => 'Evidence required workflow', 'priority' => 'high', 'assigned_to' => $owner->id,
            'attachment_required_on_completion' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('tasks', ['title' => 'Evidence required workflow', 'task_type' => Task::TYPE_WORKFLOW, 'attachment_required_on_completion' => true]);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_completion_requires_an_actual_attachment_on_web_and_api(bool $api): void
    {
        [$admin, $owner] = $this->users();
        $task = $this->workflow($admin, $owner, ['attachment_required_on_completion' => true]);
        Storage::fake('local');
        Sanctum::actingAs($owner);
        $this->actingAs($owner);
        $url = $api ? '/api/v1/tasks/'.$task->id.'/action' : route('tasks.action', $task);

        $this->postJson($url, ['action' => 'close', 'remark' => 'Finished.'])
            ->assertUnprocessable()->assertJsonValidationErrors('attachments')
            ->assertJsonPath('errors.attachments.0', 'Attach at least one file before completing this workflow.');
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'pending']);
        $this->assertDatabaseMissing('task_actions', ['task_id' => $task->id, 'action' => 'close']);

        $this->postJson($url, ['action' => 'close', 'remark' => 'Finished.', 'attachments' => ['fabricated-path.pdf']])
            ->assertUnprocessable()->assertJsonValidationErrors('attachments.0');
        $this->post($url, ['action' => 'close', 'remark' => 'Evidence uploaded.', 'attachments' => [UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf')]], ['Accept' => 'application/json'])
            ->assertOk();

        $action = $task->actions()->where('action', 'close')->firstOrFail();
        Storage::disk('local')->assertExists($action->attachments[0]['path']);
        $this->assertSame('evidence.pdf', $action->attachments[0]['name']);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'closed']);
        $this->get(route('tasks.attachments.show', [$task, $action, 0]))->assertDownload('evidence.pdf');
    }

    public function test_assignment_does_not_require_completion_evidence_and_executable_uploads_are_refused(): void
    {
        [$admin, $owner] = $this->users();
        $task = $this->workflow($admin, $owner, ['attachment_required_on_completion' => true]);

        $this->actingAs($admin)->postJson(route('tasks.action', $task), ['action' => 'assign', 'assigned_to' => $admin->id, 'remark' => 'Review next.'])->assertOk();
        $this->post(route('tasks.action', $task), ['action' => 'close', 'remark' => 'Done.', 'attachments' => [UploadedFile::fake()->create('script.php', 10, 'application/x-php')]], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('attachments.0');
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'pending', 'assigned_to' => $admin->id]);
    }

    public function test_due_date_changes_require_a_reason_and_record_both_dates_in_parent_history(): void
    {
        [$admin, $owner] = $this->users();
        $parent = $this->workflow($admin, $owner);
        $task = $this->workflow($admin, $owner, ['parent_task_id' => $parent->id, 'due_at' => '2026-11-05 17:00:00']);
        $this->actingAs($owner);
        $url = route('tasks.due-date', $task);

        $this->postJson($url, ['due_at' => '2026-11-07 17:00:00'])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->assertSame('2026-11-05 17:00:00', $task->fresh()->due_at->format('Y-m-d H:i:s'));
        $this->postJson($url, ['due_at' => 'invalid', 'reason' => 'Awaiting parts.'])->assertUnprocessable()->assertJsonValidationErrors('due_at');
        $this->postJson($url, ['due_at' => '2026-11-07 17:00:00', 'reason' => 'Awaiting parts.'])->assertOk();

        $this->assertSame('2026-11-07 17:00:00', $task->fresh()->due_at->format('Y-m-d H:i:s'));
        $remark = $task->actions()->where('action', 'due_date')->firstOrFail()->remark;
        $this->assertStringContainsString('05 Nov 2026', $remark);
        $this->assertStringContainsString('07 Nov 2026', $remark);
        $this->assertStringContainsString('Awaiting parts.', $remark);
        $this->assertDatabaseHas('task_actions', ['task_id' => $parent->id, 'related_task_id' => $task->id, 'action' => 'due_date']);

        $this->postJson($url, ['due_at' => null])->assertJsonValidationErrors('reason');
        $this->postJson($url, ['due_at' => null, 'reason' => 'Schedule to be confirmed.'])->assertOk();
        $this->assertNull($task->fresh()->due_at);
    }

    public function test_subtask_creation_assigns_parent_preserves_visibility_and_announces_completion(): void
    {
        [$admin, $owner] = $this->users();
        $nextOwner = User::factory()->create(['status' => 'active']);
        $nextOwner->assignRole('Technician');
        $observer = User::factory()->create(['status' => 'active']);
        $observer->assignRole('Technician');
        $parent = $this->workflow($admin, $owner);
        $parent->members()->create(['user_id' => $observer->id, 'role' => 'observer']);
        Notification::fake([TaskCompletedNotification::class]);

        $this->actingAs($owner)->postJson(route('tasks.subtasks.store', $parent), [
            'title' => 'Confirm material availability', 'priority' => 'normal', 'assigned_to' => $nextOwner->id,
        ])->assertCreated()->assertJsonPath('task.task_type', Task::TYPE_WORKFLOW);

        $child = Task::where('parent_task_id', $parent->id)->firstOrFail();
        $this->assertDatabaseHas('tasks', ['id' => $parent->id, 'assigned_to' => $nextOwner->id]);
        $this->assertDatabaseHas('tasks', ['id' => $child->id, 'assigned_to' => $nextOwner->id, 'customer_id' => null]);
        $this->assertDatabaseHas('task_actions', ['task_id' => $parent->id, 'related_task_id' => $child->id, 'action' => 'created']);
        $this->get(route('tasks.show', $parent))->assertOk()->assertSee('Confirm material availability');
        $this->actingAs($observer)->get(route('tasks.show', $child))->assertOk();
        $this->getJson(route('workflows.board'))->assertJsonFragment(['task_no' => $child->task_no]);
        $this->actingAs($nextOwner)->postJson(route('tasks.action', $parent), ['action' => 'close', 'remark' => 'Done.'])
            ->assertUnprocessable()->assertJsonValidationErrors('action');

        $this->postJson(route('tasks.action', $child), ['action' => 'close', 'remark' => 'Materials confirmed.'])->assertOk();
        $this->assertDatabaseHas('task_actions', ['task_id' => $parent->id, 'related_task_id' => $child->id, 'action' => 'close']);
        Notification::assertSentTo([$admin, $owner, $observer, $nextOwner], TaskCompletedNotification::class);
        $this->get(route('tasks.show', $parent))->assertSee('1/1 completed')->assertSee('Materials confirmed.');
        $this->actingAs($owner)->postJson(route('tasks.action', $parent), ['action' => 'close', 'remark' => 'Workflow finished.'])->assertOk();
    }

    public function test_complaints_and_internal_workflows_have_separate_lists_counts_boards_and_creation(): void
    {
        [$admin, $owner] = $this->users();
        $internal = $this->workflow($admin, $owner, ['title' => 'INTERNAL-ONLY-ITEM']);
        $complaint = $this->workflow($admin, $owner, ['title' => 'COMPLAINT-ONLY-ITEM', 'task_type' => Task::TYPE_TICKET, 'customer_id' => Customer::firstOrFail()->id]);

        $this->actingAs($admin)->get(route('workflows.index', ['type' => 'ticket']))->assertOk()->assertSee($internal->title)->assertDontSee($complaint->title)->assertDontSee('name="customer_id"', false)->assertViewHas('workType', Task::TYPE_WORKFLOW);
        $this->get(route('complaints.index', ['type' => 'workflow']))->assertOk()->assertSee($complaint->title)->assertDontSee($internal->title)->assertSee('name="customer_id"', false);
        $this->getJson(route('workflows.board'))->assertJsonFragment(['task_no' => $internal->task_no])->assertJsonMissing(['task_no' => $complaint->task_no]);
        $this->getJson(route('complaints.board'))->assertJsonFragment(['task_no' => $complaint->task_no])->assertJsonMissing(['task_no' => $internal->task_no]);
        $this->post(route('complaints.store'), ['title' => 'Missing customer', 'task_type' => 'workflow', 'priority' => 'normal', 'assigned_to' => $owner->id])->assertSessionHasErrors('customer_id');
    }

    public function test_closed_workflows_and_customer_complaints_cannot_receive_subtasks(): void
    {
        [$admin, $owner] = $this->users();
        $closed = $this->workflow($admin, $owner, ['status' => 'closed']);
        $complaint = $this->workflow($admin, $owner, ['task_type' => Task::TYPE_TICKET]);
        $data = ['title' => 'Invalid subtask', 'priority' => 'normal', 'assigned_to' => $owner->id];

        $this->actingAs($admin)->postJson(route('tasks.subtasks.store', $closed), $data)->assertUnprocessable();
        $this->postJson(route('tasks.subtasks.store', $complaint), $data)->assertUnprocessable();
        $this->assertDatabaseMissing('tasks', ['title' => 'Invalid subtask']);
        $this->postJson(route('tasks.due-date', $closed), ['due_at' => '2026-11-06', 'reason' => 'Reschedule.'])->assertUnprocessable();
    }

    public function test_unrelated_users_cannot_change_or_read_workflow_data_or_attachments(): void
    {
        [$admin, $owner] = $this->users();
        $outsider = User::factory()->create(['status' => 'active']);
        $outsider->assignRole('Technician');
        $task = $this->workflow($admin, $owner);
        $action = $task->actions()->create(['user_id' => $admin->id, 'action' => 'created', 'remark' => 'Created.']);

        $this->actingAs($outsider)->get(route('tasks.show', $task))->assertForbidden();
        $this->postJson(route('tasks.subtasks.store', $task), ['title' => 'Unwanted', 'priority' => 'normal', 'assigned_to' => $outsider->id])->assertForbidden();
        $this->postJson(route('tasks.due-date', $task), ['due_at' => null, 'reason' => 'Remove.'])->assertForbidden();
        $this->get(route('tasks.attachments.show', [$task, $action, 0]))->assertForbidden();
        $this->assertDatabaseMissing('tasks', ['title' => 'Unwanted']);
        $this->assertDatabaseMissing('task_actions', ['task_id' => $task->id, 'action' => 'due_date']);
    }

    public function test_unauthenticated_api_users_cannot_create_subtasks_or_change_dates(): void
    {
        $this->postJson('/api/v1/tasks/1/subtasks', [])->assertUnauthorized();
        $this->postJson('/api/v1/tasks/1/due-date', [])->assertUnauthorized();
    }

    public function test_mobile_subtask_actions_and_evidence_are_available_on_the_parent_timeline(): void
    {
        [$admin, $owner] = $this->users();
        $parent = $this->workflow($admin, $owner);
        Storage::fake('local');
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/tasks/'.$parent->id.'/subtasks', [
            'title' => 'Parts <script>alert(1)</script>', 'priority' => 'normal', 'assigned_to' => $owner->id,
            'attachment_required_on_completion' => true, 'due_at' => '2026-11-05 17:00:00',
        ])->assertCreated();
        $child = $parent->subtasks()->firstOrFail();
        $this->postJson('/api/v1/tasks/'.$child->id.'/due-date', ['due_at' => '2026-11-06 17:00:00', 'reason' => 'Delivery delayed.'])->assertOk();
        $this->postJson('/api/v1/tasks/'.$child->id.'/action', ['action' => 'assign', 'assigned_to' => $admin->id, 'remark' => 'Please approve.'])->assertOk();
        $this->actingAs($admin)->postJson(route('tasks.priority', $child), ['priority' => 'high'])->assertOk();
        Sanctum::actingAs($admin);
        $this->post('/api/v1/tasks/'.$child->id.'/action', [
            'action' => 'close', 'remark' => 'Approved with evidence.',
            'attachments' => [UploadedFile::fake()->create('approval.pdf', 10, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertOk();

        foreach (['created', 'due_date', 'assign', 'priority', 'close'] as $action) {
            $this->assertDatabaseHas('task_actions', ['task_id' => $parent->id, 'related_task_id' => $child->id, 'action' => $action]);
        }
        $completion = $parent->actions()->where('action', 'close')->firstOrFail();
        Storage::disk('local')->assertExists($completion->attachments[0]['path']);
        $this->get(route('tasks.attachments.show', [$parent, $completion, 0]))->assertDownload('approval.pdf');
        $this->get(route('tasks.attachments.show', [$child, $completion, 0]))->assertNotFound();
        $this->get(route('tasks.show', $parent))->assertSee($child->title)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_a_subtask_cannot_be_reopened_beneath_a_closed_workflow(): void
    {
        [$admin, $owner] = $this->users();
        $parent = $this->workflow($admin, $owner, ['status' => 'closed']);
        $child = $this->workflow($admin, $owner, ['parent_task_id' => $parent->id, 'status' => 'closed']);

        $this->actingAs($admin)->postJson(route('tasks.action', $child), ['action' => 'assign', 'assigned_to' => $owner->id, 'remark' => 'Needs another review.'])
            ->assertUnprocessable()->assertJsonValidationErrors('action');

        $this->assertDatabaseHas('tasks', ['id' => $child->id, 'status' => 'closed']);
        $this->assertDatabaseMissing('task_actions', ['task_id' => $child->id, 'action' => 'assign']);
    }
}
