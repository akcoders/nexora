<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_ticket_requires_customer_and_internal_workflow_rejects_customer(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@nexora.test')->firstOrFail();
        $technician = User::query()->where('email', 'technician@nexora.test')->firstOrFail();
        $customer = Customer::query()->firstOrFail();

        $this->actingAs($admin)->post(route('tasks.store'), [
            'title' => 'Customer cooling complaint',
            'task_type' => Task::TYPE_TICKET,
            'priority' => 'high',
            'assigned_to' => $technician->id,
        ])->assertSessionHasErrors('customer_id');

        $this->actingAs($admin)->post(route('tasks.store'), [
            'title' => 'Internal approval chain',
            'task_type' => Task::TYPE_WORKFLOW,
            'customer_id' => $customer->id,
            'priority' => 'normal',
            'assigned_to' => $technician->id,
        ])->assertSessionHasErrors('customer_id');

        $this->assertDatabaseMissing('tasks', ['title' => 'Customer cooling complaint']);
        $this->assertDatabaseMissing('tasks', ['title' => 'Internal approval chain']);
    }

    public function test_admin_creates_customer_ticket_that_assignee_can_view_until_closed(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@nexora.test')->firstOrFail();
        $technician = User::query()->where('email', 'technician@nexora.test')->firstOrFail();
        $customer = Customer::query()->firstOrFail();
        Setting::updateOrCreate(['key' => 'require_attendance_for_tasks'], ['value' => '0']);

        $this->actingAs($admin)->post(route('tasks.store'), [
            'title' => 'Customer ticket needs inspection',
            'task_type' => Task::TYPE_TICKET,
            'customer_id' => $customer->id,
            'priority' => 'very_high',
            'category' => 'Complaint',
            'assigned_to' => $technician->id,
        ])->assertRedirect();

        $ticket = Task::query()->where('title', 'Customer ticket needs inspection')->firstOrFail();
        $this->assertSame(Task::TYPE_TICKET, $ticket->task_type);
        $this->assertSame($customer->id, $ticket->customer_id);
        $this->assertStringStartsWith('TKT', $ticket->task_no);

        Sanctum::actingAs($technician);
        $this->getJson('/api/v1/tasks?scope=open&type=ticket')
            ->assertOk()
            ->assertJsonFragment(['task_no' => $ticket->task_no, 'task_type' => Task::TYPE_TICKET]);

        $this->postJson('/api/v1/tasks/'.$ticket->id.'/action', [
            'action' => 'close',
            'remark' => 'Customer issue resolved and confirmed.',
        ])->assertOk();

        $this->getJson('/api/v1/tasks?scope=open&type=ticket')
            ->assertOk()
            ->assertJsonMissing(['task_no' => $ticket->task_no]);
        $this->assertDatabaseHas('tasks', ['id' => $ticket->id, 'status' => 'closed']);
    }

    public function test_internal_workflow_is_created_without_customer_reference(): void
    {
        $this->seed();
        $admin = User::query()->where('email', 'admin@nexora.test')->firstOrFail();
        $technician = User::query()->where('email', 'technician@nexora.test')->firstOrFail();

        $this->actingAs($admin)->post(route('tasks.store'), [
            'title' => 'Internal stock approval',
            'task_type' => Task::TYPE_WORKFLOW,
            'priority' => 'normal',
            'assigned_to' => $technician->id,
        ])->assertRedirect();

        $workflow = Task::query()->where('title', 'Internal stock approval')->firstOrFail();
        $this->assertNull($workflow->customer_id);
        $this->assertStringStartsWith('WFL', $workflow->task_no);
    }
}
