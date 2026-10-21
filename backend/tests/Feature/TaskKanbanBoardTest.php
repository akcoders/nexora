<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskKanbanBoardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $this->technician = User::where('email', 'technician@nexora.test')->firstOrFail();
    }

    private function workflow(array $attributes = []): Task
    {
        return Task::create(array_merge([
            'task_no' => 'WFL'.str_pad((string) ((Task::withTrashed()->max('id') ?? 0) + 1), 6, '0', STR_PAD_LEFT),
            'task_type' => Task::TYPE_WORKFLOW,
            'title' => 'Approve compressor purchase',
            'priority' => 'normal',
            'created_by' => $this->admin->id,
            'assigned_to' => $this->technician->id,
            'status' => 'pending',
        ], $attributes));
    }

    public function test_board_page_renders_and_feed_returns_open_and_recently_closed_cards(): void
    {
        $open = $this->workflow(['due_at' => now()->subDay()]);
        $closed = $this->workflow(['title' => 'Finished hand-off', 'status' => 'closed', 'closed_at' => now()]);

        $this->actingAs($this->admin)->get(route('tasks.board'))
            ->assertOk()
            ->assertSee('data-kanban', false);

        $this->actingAs($this->admin)->getJson(route('tasks.board'))
            ->assertOk()
            ->assertJsonPath('me', $this->admin->id)
            ->assertJsonPath('can_reopen', true)
            ->assertJsonFragment(['id' => $open->id, 'status' => 'pending', 'overdue' => true, 'can_act' => true])
            ->assertJsonFragment(['id' => $closed->id, 'status' => 'closed']);
    }

    public function test_dropping_a_card_on_another_owner_hands_it_over_with_a_mandatory_remark(): void
    {
        $task = $this->workflow();

        $this->actingAs($this->admin)->postJson(route('tasks.action', $task), ['action' => 'assign', 'assigned_to' => $this->admin->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('remark');

        $this->actingAs($this->admin)->postJson(route('tasks.action', $task), [
            'action' => 'assign',
            'assigned_to' => $this->admin->id,
            'remark' => 'Needs admin sign-off.',
        ])->assertOk()->assertJsonPath('task.assignee.id', $this->admin->id);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'assigned_to' => $this->admin->id, 'status' => 'pending']);
        $this->assertDatabaseHas('task_actions', ['task_id' => $task->id, 'action' => 'assign', 'remark' => 'Needs admin sign-off.']);
    }

    public function test_dropping_a_card_on_closed_closes_it(): void
    {
        $task = $this->workflow();

        $this->actingAs($this->technician)->postJson(route('tasks.action', $task), ['action' => 'close', 'remark' => 'Purchase approved.'])
            ->assertOk()
            ->assertJsonPath('task.status', 'closed')
            ->assertJsonPath('task.can_act', false);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'closed']);
    }

    public function test_only_users_with_update_permission_can_reopen_a_closed_card(): void
    {
        $task = $this->workflow(['status' => 'closed', 'closed_at' => now()]);

        $this->actingAs($this->technician)->postJson(route('tasks.action', $task), ['action' => 'assign', 'assigned_to' => $this->technician->id, 'remark' => 'Reopening.'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This task is already closed.');

        $this->actingAs($this->admin)->postJson(route('tasks.action', $task), ['action' => 'assign', 'assigned_to' => $this->technician->id, 'remark' => 'Customer called back.'])
            ->assertOk()
            ->assertJsonPath('task.status', 'pending');
    }

    public function test_priority_lane_change_is_recorded_on_the_timeline(): void
    {
        $task = $this->workflow();

        $this->actingAs($this->admin)->postJson(route('tasks.priority', $task), ['priority' => 'very_high'])
            ->assertOk()
            ->assertJsonPath('task.priority', 'very_high');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'priority' => 'very_high']);
        $this->assertDatabaseHas('task_actions', ['task_id' => $task->id, 'action' => 'priority', 'remark' => 'Priority changed from Normal to Very High.']);

        $this->actingAs($this->admin)->postJson(route('tasks.priority', $task), ['priority' => 'urgent'])
            ->assertJsonValidationErrors('priority');
    }

    public function test_priority_change_is_refused_for_assignees_without_permission_and_for_closed_items(): void
    {
        $task = $this->workflow();
        $this->actingAs($this->technician)->postJson(route('tasks.priority', $task), ['priority' => 'high'])->assertForbidden();

        $task->update(['status' => 'closed', 'closed_at' => now()]);
        $this->actingAs($this->admin)->postJson(route('tasks.priority', $task), ['priority' => 'high'])->assertUnprocessable();

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'priority' => 'normal']);
    }

    public function test_item_created_from_the_board_returns_to_the_board(): void
    {
        $this->actingAs($this->admin)->post(route('tasks.store'), [
            'title' => 'Board created workflow',
            'task_type' => Task::TYPE_WORKFLOW,
            'priority' => 'high',
            'assigned_to' => $this->technician->id,
            'return_to' => 'board',
        ])->assertRedirect(route('tasks.board', ['type' => Task::TYPE_WORKFLOW]));
    }
}
