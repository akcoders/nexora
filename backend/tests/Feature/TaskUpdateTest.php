<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskCompletedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class TaskUpdateTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{User, User, Task} */
    private function workflow(array $attributes = []): array
    {
        $this->seed();
        Setting::updateOrCreate(['key' => 'require_attendance_for_tasks'], ['value' => '0']);
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $owner = User::where('email', 'technician@nexora.test')->firstOrFail();
        $task = Task::create(array_merge([
            'task_no' => 'WFLUPDATE001', 'task_type' => Task::TYPE_WORKFLOW, 'title' => 'Track material delivery',
            'created_by' => $admin->id, 'assigned_to' => $owner->id, 'priority' => 'normal', 'status' => 'pending',
            'due_at' => '2026-11-05 17:00:00', 'created_at' => '2026-10-30 09:00:00', 'updated_at' => '2026-10-30 09:00:00',
        ], $attributes));

        return [$admin, $owner, $task];
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_saving_a_remark_keeps_the_workflow_pending_with_the_same_owner_and_due_date(bool $api): void
    {
        [$admin, $owner, $task] = $this->workflow(['attachment_required_on_completion' => true]);
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(10, 15));
        Notification::fake([TaskAssignedNotification::class, TaskCompletedNotification::class]);
        Sanctum::actingAs($owner);
        $this->actingAs($owner);
        $url = $api ? '/api/v1/tasks/'.$task->id.'/action' : route('tasks.action', $task);

        $this->postJson($url, [
            'action' => 'update', 'status' => 'pending', 'remark' => 'Supplier confirmed dispatch.',
            'reassign' => '0', 'change_due_date' => '0', 'assigned_to' => $admin->id,
        ])->assertOk();

        $task->refresh();
        $this->assertSame('pending', $task->status);
        $this->assertSame($owner->id, $task->assigned_to);
        $this->assertSame('2026-11-05 17:00:00', $task->due_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-01 10:15:00', $task->updated_at->format('Y-m-d H:i:s'));
        $this->assertNull($task->closed_at);
        $action = $task->actions()->sole();
        $this->assertSame('update', $action->action);
        $this->assertSame('Supplier confirmed dispatch.', $action->remark);
        $this->assertSame('2026-11-01 10:15:00', $action->created_at->format('Y-m-d H:i:s'));
        Notification::assertNothingSent();
    }

    public function test_legacy_in_progress_updates_remain_pending_and_record_the_remark_in_the_parent_timeline(): void
    {
        [$admin, $owner, $parent] = $this->workflow();
        $child = Task::create([
            'task_no' => 'WFLUPDATE002', 'task_type' => Task::TYPE_WORKFLOW, 'title' => 'Confirm dispatch',
            'created_by' => $admin->id, 'assigned_to' => $owner->id, 'priority' => 'normal', 'status' => 'pending', 'parent_task_id' => $parent->id,
        ]);

        $this->actingAs($owner)->postJson(route('tasks.action', $child), ['action' => 'update', 'status' => 'in_progress', 'remark' => 'Checking the dispatch schedule.'])->assertOk();

        $this->assertDatabaseHas('tasks', ['id' => $child->id, 'status' => 'pending', 'assigned_to' => $owner->id]);
        $this->assertDatabaseHas('task_actions', ['task_id' => $parent->id, 'related_task_id' => $child->id, 'action' => 'update']);
        $this->assertSame(1, $child->actions()->count());
        $this->assertDatabaseMissing('task_actions', ['task_id' => $child->id, 'action' => 'status']);
    }

    public function test_reassigning_requires_another_owner_and_revised_dates_require_a_delay_reason_before_any_update_is_saved(): void
    {
        [$admin, $owner, $task] = $this->workflow();
        $this->actingAs($owner);
        $url = route('tasks.action', $task);
        $data = ['action' => 'update', 'status' => 'pending', 'remark' => 'Delivery delayed.', 'reassign' => 1];

        $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
        $this->postJson($url, array_merge($data, ['assigned_to' => $owner->id]))->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
        $data = array_merge($data, ['assigned_to' => $admin->id, 'next_follow_up_at' => '2026-11-07 17:00:00']);
        $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('delay_reason')
            ->assertJsonPath('errors.delay_reason.0', 'Please specify the reason for the delay.');
        $this->postJson($url, array_merge($data, ['next_follow_up_at' => 'invalid', 'delay_reason' => 'Delivery delayed.']))->assertUnprocessable()->assertJsonValidationErrors('next_follow_up_at');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'assigned_to' => $owner->id, 'due_at' => '2026-11-05 17:00:00']);
        $this->assertSame(0, $task->actions()->count());
    }

    public function test_one_save_can_handover_set_a_follow_up_and_record_the_remark(): void
    {
        [$admin, $owner, $task] = $this->workflow();
        Notification::fake([TaskAssignedNotification::class]);
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/tasks/'.$task->id.'/action', [
            'action' => 'update', 'status' => 'in_progress', 'remark' => 'Please coordinate delivery.',
            'reassign' => 1, 'assigned_to' => $admin->id,
            'next_follow_up_at' => '2026-11-07 17:00:00', 'delay_reason' => 'Supplier requested two more days.',
        ])->assertOk();

        $task->refresh();
        $this->assertSame($admin->id, $task->assigned_to);
        $this->assertSame('pending', $task->status);
        $this->assertSame('2026-11-05 17:00:00', $task->due_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-07 17:00:00', $task->revised_due_at->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('task_actions', ['task_id' => $task->id, 'action' => 'assign', 'remark' => 'Please coordinate delivery.']);
        $this->assertSame('Supplier requested two more days.', $task->actions()->whereNotNull('revised_due_at')->sole()->delay_reason);
        Notification::assertSentTo($admin, TaskAssignedNotification::class);
    }

    public function test_selecting_completed_requires_evidence_and_preserves_the_due_date(): void
    {
        [$admin, $owner, $task] = $this->workflow(['attachment_required_on_completion' => true]);
        Storage::fake('local');
        $this->actingAs($owner);
        $url = route('tasks.action', $task);
        $data = ['action' => 'update', 'status' => 'closed', 'remark' => 'Delivery confirmed.'];

        $this->postJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('attachments');
        $this->post($url, array_merge($data, ['attachments' => [UploadedFile::fake()->create('delivery.pdf', 10, 'application/pdf')]]), ['Accept' => 'application/json'])->assertOk();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'closed', 'assigned_to' => $owner->id, 'due_at' => '2026-11-05 17:00:00']);
        $completion = $task->actions()->where('action', 'close')->sole();
        Storage::disk('local')->assertExists($completion->attachments[0]['path']);
    }

    public function test_invalid_status_completion_with_handover_and_missing_remark_are_refused(): void
    {
        [$admin, $owner, $task] = $this->workflow();
        $this->actingAs($owner);
        $url = route('tasks.action', $task);

        $this->postJson($url, ['action' => 'update', 'status' => 'pending'])->assertJsonValidationErrors('remark');
        $this->postJson($url, ['action' => 'update', 'status' => 'invalid', 'remark' => 'Progress.'])->assertJsonValidationErrors('status');
        $this->postJson($url, ['action' => 'update', 'status' => 'closed', 'remark' => 'Done.', 'reassign' => 1, 'assigned_to' => $admin->id])->assertJsonValidationErrors('reassign');
        $this->assertSame(0, $task->actions()->count());
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'pending', 'assigned_to' => $owner->id]);
    }

    public function test_the_workflow_page_has_one_update_form_with_optional_fields_hidden_by_default(): void
    {
        [$admin, $owner, $task] = $this->workflow();

        $response = $this->actingAs($owner)->get(route('tasks.show', $task))->assertOk()
            ->assertSee('Task update')->assertSee('Save update')
            ->assertSee('Assign to someone else?')->assertSee('Next follow-up date')->assertDontSee('Change due date?')
            ->assertDontSee('Move workflow')->assertDontSee('id="taskAction"', false);
        $document = new \DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//aside[@id="workflowUpdateCard"]//form')->length);
        $this->assertSame(1, $xpath->query('//fieldset[@id="workflowAssigneeFields" and @disabled and @hidden]')->length);
        $this->assertSame(1, $xpath->query('//fieldset[@data-revision-fields and @disabled and @hidden]')->length);
    }

    public function test_an_unrelated_user_cannot_save_a_workflow_update(): void
    {
        [$admin, $owner, $task] = $this->workflow();
        $outsider = User::factory()->create(['status' => 'active']);
        $outsider->assignRole('Technician');

        $this->actingAs($outsider)->postJson(route('tasks.action', $task), ['action' => 'update', 'status' => 'pending', 'remark' => 'Unwanted update.'])->assertForbidden();
        $this->assertSame(0, $task->actions()->count());
    }

    #[TestWith(['2026-11-07 17:00:00'])]
    #[TestWith([null])]
    public function test_a_follow_up_can_be_set_or_cleared_without_changing_the_due_date_or_owner(?string $dueAt): void
    {
        [$admin, $owner, $task] = $this->workflow();

        $this->actingAs($owner)->postJson(route('tasks.action', $task), [
            'action' => 'update', 'status' => 'pending', 'remark' => 'Waiting for the revised schedule.',
            'next_follow_up_at' => $dueAt, 'delay_reason' => 'Supplier has revised the delivery schedule.',
        ])->assertOk();

        $task->refresh();
        $this->assertSame('2026-11-05 17:00:00', $task->due_at?->format('Y-m-d H:i:s'));
        $this->assertSame($dueAt, $task->next_follow_up_at?->format('Y-m-d H:i:s'));
        $this->assertSame($owner->id, $task->assigned_to);
        $this->assertSame('pending', $task->status);
        $this->assertDatabaseHas('task_actions', ['task_id' => $task->id, 'action' => 'update', 'remark' => 'Waiting for the revised schedule.']);
        $this->assertSame($dueAt ? 1 : 0, $task->actions()->whereNotNull('revised_due_at')->count());
    }
}
