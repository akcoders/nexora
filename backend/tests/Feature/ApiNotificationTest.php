<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ServiceJob;
use App\Models\User;
use App\Notifications\ServiceJobAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_technician_reads_assigned_service_job_notification(): void
    {
        $this->seed();
        $technician = User::where('email', 'technician@nexora.test')->firstOrFail();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $customer = Customer::where('code', 'CUST00001')->firstOrFail();
        $serviceJob = ServiceJob::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => $technician->id,
            'created_by' => $admin->id,
        ]);
        $technician->unreadNotifications->markAsRead();
        $technician->notify(new ServiceJobAssignedNotification($serviceJob->load('customer')));
        Sanctum::actingAs($technician);

        $response = $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonFragment(['service_job_id' => $serviceJob->id, 'type' => 'service_job']);

        $notificationId = $technician->notifications()->where('type', ServiceJobAssignedNotification::class)->firstOrFail()->id;
        $this->postJson("/api/v1/notifications/{$notificationId}/read")->assertOk();
        $this->getJson('/api/v1/notifications')->assertJsonPath('unread_count', 0);
    }
}
