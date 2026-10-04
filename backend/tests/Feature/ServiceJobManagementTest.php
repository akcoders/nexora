<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerBranch;
use App\Models\ServiceJob;
use App\Models\ServiceType;
use App\Models\User;
use App\Notifications\ServiceJobAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ServiceJobManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_creates_and_assigns_a_service_job(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $technician = User::where('email', 'technician@nexora.test')->firstOrFail();
        $customer = Customer::where('code', 'CUST00001')->firstOrFail();
        $serviceType = ServiceType::where('code', 'ST-GENERAL')->firstOrFail();
        Notification::fake([ServiceJobAssignedNotification::class]);

        $response = $this->actingAs($admin)->post('/service-jobs', [
            'customer_id' => $customer->id,
            'customer_equipment_id' => $customer->equipments()->value('id'),
            'service_type_id' => $serviceType->id,
            'origin' => 'complaint',
            'customer_phone' => '9876543210',
            'service_address' => '101 Business Park, Mumbai',
            'complaint' => 'AC is not cooling.',
            'priority' => 'high',
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'assigned_to' => $technician->id,
        ]);

        $serviceJob = ServiceJob::firstOrFail();
        $response->assertRedirect(route('service-jobs.show', $serviceJob));
        $this->assertSame('SRV-000001', $serviceJob->job_no);
        $this->assertDatabaseHas('service_job_status_histories', [
            'service_job_id' => $serviceJob->id,
            'to_status' => 'assigned',
            'changed_by' => $admin->id,
        ]);
        Notification::assertSentTo($technician, ServiceJobAssignedNotification::class);
    }

    public function test_rejects_a_branch_that_does_not_belong_to_the_customer(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $technician = User::where('email', 'technician@nexora.test')->firstOrFail();
        $customer = Customer::where('code', 'CUST00001')->firstOrFail();
        $otherCustomer = Customer::factory()->create();
        $otherBranch = CustomerBranch::create(['customer_id' => $otherCustomer->id, 'code' => 'OTHER-B01', 'name' => 'Other Branch']);

        $this->actingAs($admin)->post('/service-jobs', [
            'customer_id' => $customer->id,
            'customer_branch_id' => $otherBranch->id,
            'origin' => 'manual',
            'customer_phone' => '9876543210',
            'service_address' => 'Mumbai',
            'complaint' => 'Cooling issue.',
            'priority' => 'normal',
            'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'assigned_to' => $technician->id,
        ])->assertSessionHasErrors('customer_branch_id');

        $this->assertDatabaseCount('service_jobs', 0);
    }

    public function test_technician_cannot_create_an_admin_service_job(): void
    {
        $this->seed();
        $technician = User::where('email', 'technician@nexora.test')->firstOrFail();

        $this->actingAs($technician)->post('/service-jobs', [])->assertForbidden();
    }

    public function test_administrator_manages_service_master_data(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();

        $this->actingAs($admin)->get(route('service-masters.index'))
            ->assertOk()
            ->assertSee('HVAC service configuration');

        $this->actingAs($admin)->post(route('service-masters.catalog.store'), [
            'code' => 'svc-install',
            'name' => 'Split AC Installation',
            'category' => 'Installation',
            'equipment_type' => 'Split AC',
            'standard_price' => 2500,
            'tax_percent' => 18,
            'estimated_minutes' => 120,
        ])->assertRedirect();

        $this->assertDatabaseHas('service_catalog_items', [
            'code' => 'SVC-INSTALL',
            'name' => 'Split AC Installation',
            'active' => true,
        ]);
    }

    public function test_administrator_reassigns_and_reschedules_a_service_job(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $technician = User::where('email', 'technician@nexora.test')->firstOrFail();
        $replacement = User::factory()->create(['status' => 'active']);
        $replacement->assignRole('Technician');
        $customer = Customer::where('code', 'CUST00001')->firstOrFail();
        $serviceJob = ServiceJob::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => $technician->id,
            'created_by' => $admin->id,
            'status' => 'confirmed',
            'scheduled_at' => now()->addDay(),
        ]);
        Notification::fake([ServiceJobAssignedNotification::class]);

        $this->actingAs($admin)->put(route('service-jobs.update', $serviceJob), [
            'assigned_to' => $replacement->id,
            'scheduled_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'priority' => 'very_high',
            'notes' => 'Customer requested a later visit.',
        ])->assertRedirect();

        $this->assertDatabaseHas('service_jobs', [
            'id' => $serviceJob->id,
            'assigned_to' => $replacement->id,
            'status' => 'rescheduled',
            'priority' => 'very_high',
        ]);
        Notification::assertSentTo($replacement, ServiceJobAssignedNotification::class);
    }
}
