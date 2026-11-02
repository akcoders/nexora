<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class WorkflowFollowUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(12, 0));
        $this->seed();
        Setting::updateOrCreate(['key' => 'require_attendance_for_tasks'], ['value' => '0']);
    }

    private function workflow(array $attributes = []): Task
    {
        return Task::create(array_merge([
            'task_no' => 'WFLFOLLOW'.((Task::max('id') ?? 0) + 1), 'task_type' => Task::TYPE_WORKFLOW,
            'title' => 'Follow up delivery', 'status' => 'pending', 'priority' => 'normal',
            'created_by' => User::where('email', 'admin@nexora.test')->sole()->id,
            'assigned_to' => User::where('email', 'technician@nexora.test')->sole()->id,
            'due_at' => '2026-11-05 17:00:00',
        ], $attributes));
    }

    private function followUp(Task $task, ?string $date, ?string $reason = null): array
    {
        return ['action' => 'update', 'status' => $task->status, 'remark' => 'Delivery update.', 'next_follow_up_at' => $date, 'delay_reason' => $reason];
    }

    public function test_each_revision_is_preserved_in_history_and_report_without_duplicate_parent_entries(): void
    {
        $parent = $this->workflow();
        $task = $this->workflow(['parent_task_id' => $parent->id]);
        $this->actingAs($task->assignee);
        $url = route('tasks.action', $task);
        $this->postJson($url, $this->followUp($task, '2026-11-07 17:00:00', 'Parts delayed.'))->assertOk();
        $this->postJson($url, $this->followUp($task, '2026-11-09 17:00:00', 'Courier rescheduled.'))->assertOk();
        $this->postJson($url, $this->followUp($task, '2026-11-09T11:30:00Z'))->assertOk();

        $task->refresh();
        $this->assertSame('2026-11-05 17:00:00', $task->due_at->format('Y-m-d H:i:s'));
        $this->assertTrue($task->revised_due_at->equalTo($task->next_follow_up_at));
        $this->assertSame('2026-11-09 17:00:00', $task->revised_due_at->format('Y-m-d H:i:s'));
        $this->assertSame(['Courier rescheduled.', 'Parts delayed.'], $task->actions()->whereNotNull('revised_due_at')->pluck('delay_reason')->all());
        $this->assertSame(2, $parent->actions()->whereNotNull('revised_due_at')->count());
        $this->get(route('tasks.show', $parent))->assertOk()->assertSee('Reason for delay: Parts delayed.')->assertSee('Courier rescheduled.');

        $report = $this->actingAs($task->creator)->get(route('reports.index'))->assertOk()
            ->assertSee('Parts delayed.')->assertSee('Courier rescheduled.')->assertSee('07 Nov 2026')->assertSee('09 Nov 2026');
        $this->assertSame(2, $report->viewData('revisions')->count());
    }

    #[TestWith(['2026-11-03 09:00:00'])]
    #[TestWith(['2026-11-05 17:00:00'])]
    public function test_follow_up_on_or_before_the_original_deadline_does_not_create_a_revision(string $date): void
    {
        $task = $this->workflow();
        $this->actingAs($task->assignee)->postJson(route('tasks.action', $task), $this->followUp($task, $date))->assertOk();
        $this->assertSame($date, $task->fresh()->next_follow_up_at->format('Y-m-d H:i:s'));
        $this->assertNull($task->fresh()->revised_due_at);
        $this->assertNull($task->actions()->sole()->delay_reason);
    }

    public function test_a_workflow_without_an_original_deadline_can_have_a_follow_up_without_a_revision(): void
    {
        $task = $this->workflow(['due_at' => null]);
        $this->actingAs($task->assignee)->postJson(route('tasks.action', $task), $this->followUp($task, '2026-11-07 17:00:00'))->assertOk();
        $this->assertNull($task->fresh()->due_at);
        $this->assertNull($task->fresh()->revised_due_at);
        $this->assertNotNull($task->fresh()->next_follow_up_at);
    }

    public function test_revisions_do_not_hide_overdue_workflows_from_web_api_or_board_filters(): void
    {
        $task = $this->workflow();
        $future = $this->workflow(['title' => 'Future deadline', 'due_at' => '2026-11-15 17:00:00']);
        $closed = $this->workflow(['title' => 'Already completed', 'status' => 'closed', 'closed_at' => now()]);
        $this->travelTo(now()->setDate(2026, 11, 6));
        $this->actingAs($task->assignee)->postJson(route('tasks.action', $task), $this->followUp($task, '2026-11-09 17:00:00', 'Awaiting delivery.'))->assertOk();

        $response = $this->get(route('workflows.index', ['status' => 'overdue']))->assertOk()->assertSee('Overdue')->assertSee('Follow-up:')
            ->assertDontSee('Future deadline')->assertDontSee('Already completed');
        $ids = $response->viewData('tasks')->modelKeys();
        $this->assertContains($task->id, $ids);
        $this->assertNotContains($future->id, $ids);
        $this->assertNotContains($closed->id, $ids);
        $this->assertTrue($task->fresh()->isOverdue());
        $cards = $this->getJson(route('workflows.board'))->assertOk()->json('tasks');
        $card = collect($cards)->firstWhere('id', $task->id);
        $this->assertTrue($card['overdue']);
        $this->assertSame('2026-11-05', substr($card['due_at'], 0, 10));
        $this->assertNotNull($card['next_follow_up_at']);
        Sanctum::actingAs($task->assignee);
        $api = $this->getJson('/api/v1/tasks?type=workflow&status=overdue')->assertOk();
        $ids = collect($api->json('data'))->pluck('id')->all();
        $this->assertContains($task->id, $ids);
        $this->assertNotContains($future->id, $ids);
        $this->assertNotContains($closed->id, $ids);
    }

    public function test_original_and_revised_dates_cannot_be_forged_and_legacy_due_date_updates_are_rejected(): void
    {
        $task = $this->workflow();
        $this->actingAs($task->assignee);
        $url = route('tasks.action', $task);
        $data = ['action' => 'update', 'status' => 'pending', 'remark' => 'Update.'];
        foreach (['due_at', 'revised_due_at', 'change_due_date'] as $field) {
            $this->postJson($url, $data + [$field => $field === 'change_due_date' ? 1 : '2026-11-09 17:00:00'])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->postJson(route('tasks.due-date', $task), ['due_at' => null, 'reason' => 'Remove.'])->assertUnprocessable()->assertJsonValidationErrors('due_at');
        Sanctum::actingAs($task->assignee);
        $this->postJson('/api/v1/tasks/'.$task->id.'/due-date', ['due_at' => '2026-11-09'])->assertUnprocessable()->assertJsonValidationErrors('due_at');
        $this->assertSame('2026-11-05 17:00:00', $task->fresh()->due_at->format('Y-m-d H:i:s'));
        $this->assertSame(0, $task->actions()->count());
    }

    public function test_an_existing_past_follow_up_is_preserved_on_a_remark_but_new_past_dates_are_rejected(): void
    {
        $task = $this->workflow(['next_follow_up_at' => '2026-11-03 17:00:00']);
        $this->travelTo(now()->setDate(2026, 11, 6));
        $this->actingAs($task->assignee);
        $url = route('tasks.action', $task);
        $this->postJson($url, $this->followUp($task, '2026-11-03 17:00:00'))->assertOk();
        $this->postJson($url, $this->followUp($task, '2026-11-04 17:00:00'))->assertUnprocessable()->assertJsonValidationErrors('next_follow_up_at');
        $this->assertSame('2026-11-03 17:00:00', $task->fresh()->next_follow_up_at->format('Y-m-d H:i:s'));
        $this->assertSame(1, $task->actions()->count());
    }

    public function test_delay_report_only_includes_workflows_the_viewer_can_access(): void
    {
        $own = $this->workflow();
        $unrelatedOwner = User::factory()->create(['status' => 'active']);
        $unrelatedOwner->assignRole('Technician');
        $other = $this->workflow(['created_by' => $unrelatedOwner->id, 'assigned_to' => $unrelatedOwner->id]);
        $this->actingAs($own->assignee)->postJson(route('tasks.action', $own), $this->followUp($own, '2026-11-07 17:00:00', 'Visible delay.'))->assertOk();
        $this->actingAs($unrelatedOwner)->postJson(route('tasks.action', $other), $this->followUp($other, '2026-11-08 17:00:00', 'Private delay.'))->assertOk();
        $own->assignee->givePermissionTo('reports.read');
        $this->actingAs($own->assignee)->get(route('reports.index'))->assertOk()->assertSee('Visible delay.')->assertDontSee('Private delay.');
        $reportOnly = User::factory()->create(['status' => 'active']);
        $reportOnly->givePermissionTo('reports.read');
        $this->actingAs($reportOnly)->get(route('reports.index'))->assertOk()->assertDontSee('Visible delay.')->assertDontSee('Private delay.');
    }
}
