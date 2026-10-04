<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\InspectionCondition;
use App\Models\ServiceCatalogItem;
use App\Models\ServiceInspection;
use App\Models\ServiceJob;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TechnicianServiceJobWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_technician_completes_the_service_job_happy_path(): void
    {
        $this->seed();
        Storage::fake('public');
        $technician = User::where('email', 'technician@nexora.test')->firstOrFail();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $customer = Customer::where('code', 'CUST00001')->firstOrFail();
        $serviceJob = ServiceJob::create([
            'job_no' => 'SRV-000001',
            'customer_id' => $customer->id,
            'customer_equipment_id' => $customer->equipments()->value('id'),
            'service_type_id' => ServiceType::where('code', 'ST-GENERAL')->value('id'),
            'origin' => 'complaint',
            'customer_phone' => '9876543210',
            'service_address' => 'Demo Corporate Customer, Mumbai',
            'latitude' => 19.0760,
            'longitude' => 72.8777,
            'complaint' => 'AC is not cooling.',
            'priority' => 'high',
            'scheduled_at' => now()->addHour(),
            'assigned_to' => $technician->id,
            'created_by' => $admin->id,
            'status' => 'assigned',
        ]);
        Sanctum::actingAs($technician);

        $this->postJson("/api/v1/service-jobs/{$serviceJob->id}/contact", ['outcome' => 'confirm'])
            ->assertOk()
            ->assertJsonPath('job.status', 'confirmed');
        $this->postJson("/api/v1/service-jobs/{$serviceJob->id}/journey")
            ->assertOk()
            ->assertJsonPath('job.status', 'on_the_way');
        $this->postJson("/api/v1/service-jobs/{$serviceJob->id}/arrival", [
            'latitude' => 19.0761,
            'longitude' => 72.8778,
            'arrival_method' => 'manual',
        ])->assertOk()->assertJsonPath('job.status', 'arrived');
        $this->postJson("/api/v1/service-jobs/{$serviceJob->id}/inspection")
            ->assertOk()
            ->assertJsonPath('job.status', 'inspection_in_progress');

        $preInspection = ServiceInspection::whereBelongsTo($serviceJob)->where('phase', 'pre')->with('items')->firstOrFail();
        $condition = InspectionCondition::where('code', 'OK')->firstOrFail();
        foreach ($preInspection->items as $item) {
            $this->putJson("/api/v1/service-jobs/{$serviceJob->id}/inspection-items/{$item->id}", [
                'inspection_condition_id' => $condition->id,
            ])->assertOk();
        }
        $this->post("/api/v1/service-jobs/{$serviceJob->id}/photos", [
            'category' => 'before',
            'service_inspection_id' => $preInspection->id,
            'photo' => UploadedFile::fake()->image('before.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->postJson("/api/v1/service-jobs/{$serviceJob->id}/inspection/complete")
            ->assertOk()
            ->assertJsonPath('job.status', 'inspection_completed');

        $catalogService = ServiceCatalogItem::where('code', 'SVC-GENERAL')->firstOrFail();
        $estimateResponse = $this->postJson("/api/v1/service-jobs/{$serviceJob->id}/estimates", [
            'items' => [[
                'service_catalog_item_id' => $catalogService->id,
                'type' => 'service',
                'quantity' => 1,
            ]],
        ])->assertCreated()->assertJsonPath('estimate.final_amount', '944.00');
        $estimateId = $estimateResponse->json('estimate.id');
        $this->post("/api/v1/service-jobs/{$serviceJob->id}/estimates/{$estimateId}/decision", [
            'decision' => 'accept',
            'customer_name' => 'Demo Customer',
            'signature' => UploadedFile::fake()->image('approval.png'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('job.status', 'customer_approved');
        $this->postJson("/api/v1/service-jobs/{$serviceJob->id}/service/start")
            ->assertOk()
            ->assertJsonPath('job.status', 'service_in_progress');
        $this->postJson("/api/v1/service-jobs/{$serviceJob->id}/services", [
            'service_catalog_item_id' => $catalogService->id,
            'quantity' => 1,
        ])->assertCreated();
        $this->postJson("/api/v1/service-jobs/{$serviceJob->id}/materials", [
            'name' => 'Drain pipe',
            'quantity' => 1,
            'unit' => 'piece',
            'unit_rate' => 100,
        ])->assertCreated();
        $this->post("/api/v1/service-jobs/{$serviceJob->id}/photos", [
            'category' => 'during',
            'photo' => UploadedFile::fake()->image('during.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->postJson("/api/v1/service-jobs/{$serviceJob->id}/post-inspection")->assertOk();
        $postInspection = ServiceInspection::whereBelongsTo($serviceJob)->where('phase', 'post')->with('items')->firstOrFail();
        $yesCondition = InspectionCondition::where('code', 'YES')->firstOrFail();
        foreach ($postInspection->items as $item) {
            $this->putJson("/api/v1/service-jobs/{$serviceJob->id}/inspection-items/{$item->id}", [
                'inspection_condition_id' => $yesCondition->id,
            ])->assertOk();
        }
        $this->postJson("/api/v1/service-jobs/{$serviceJob->id}/post-inspection/complete")->assertOk();
        $this->post("/api/v1/service-jobs/{$serviceJob->id}/photos", [
            'category' => 'after',
            'photo' => UploadedFile::fake()->image('after.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->post("/api/v1/service-jobs/{$serviceJob->id}/signatures", [
            'type' => 'completion',
            'signer_name' => 'Demo Customer',
            'signature' => UploadedFile::fake()->image('completion.png'),
        ], ['Accept' => 'application/json'])->assertCreated();
        $this->postJson("/api/v1/service-jobs/{$serviceJob->id}/service/complete")
            ->assertOk()
            ->assertJsonPath('job.status', 'payment_pending')
            ->assertJsonPath('job.final_amount', '1044.00');
        $this->postJson("/api/v1/service-jobs/{$serviceJob->id}/payments", [
            'amount' => 1044,
            'method' => 'upi',
            'transaction_reference' => 'UPI-TEST-001',
        ])->assertCreated()->assertJsonPath('job.status', 'paid');
        $this->postJson("/api/v1/service-jobs/{$serviceJob->id}/complete")
            ->assertOk()
            ->assertJsonPath('job.status', 'completed')
            ->assertJsonStructure(['job' => ['feedback_url']]);

        $this->assertDatabaseHas('service_feedback', ['service_job_id' => $serviceJob->id]);
        $this->assertDatabaseHas('service_job_status_histories', ['service_job_id' => $serviceJob->id, 'to_status' => 'completed']);
    }

    public function test_prevents_invalid_status_jump(): void
    {
        $this->seed();
        $technician = User::where('email', 'technician@nexora.test')->firstOrFail();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $customer = Customer::where('code', 'CUST00001')->firstOrFail();
        $serviceJob = ServiceJob::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => $technician->id,
            'created_by' => $admin->id,
            'status' => 'assigned',
        ]);
        Sanctum::actingAs($technician);

        $this->postJson("/api/v1/service-jobs/{$serviceJob->id}/service/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('service_jobs', ['id' => $serviceJob->id, 'status' => 'assigned']);
    }

    public function test_credit_payment_allows_technician_to_complete_job(): void
    {
        $this->seed();
        $technician = User::where('email', 'technician@nexora.test')->firstOrFail();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $customer = Customer::where('code', 'CUST00001')->firstOrFail();
        $serviceJob = ServiceJob::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => $technician->id,
            'created_by' => $admin->id,
            'status' => 'payment_pending',
            'payment_status' => 'pending',
            'final_amount' => 1250,
        ]);
        $serviceJob->signatures()->create([
            'type' => 'completion',
            'signer_name' => 'Customer',
            'path' => 'service-jobs/test/signature.png',
            'signed_at' => now(),
        ]);
        Sanctum::actingAs($technician);

        $this->postJson("/api/v1/service-jobs/{$serviceJob->id}/payments", [
            'amount' => 0,
            'method' => 'credit',
            'notes' => 'Approved for payment later.',
        ])->assertCreated()
            ->assertJsonPath('job.status', 'payment_pending')
            ->assertJsonPath('job.primary_action.key', 'complete');

        $this->postJson("/api/v1/service-jobs/{$serviceJob->id}/complete")
            ->assertOk()
            ->assertJsonPath('job.status', 'completed');
    }

    public function test_technician_cannot_open_another_technicians_service_job(): void
    {
        $this->seed();
        $technician = User::where('email', 'technician@nexora.test')->firstOrFail();
        $otherTechnician = User::factory()->create(['status' => 'active']);
        $otherTechnician->assignRole('Technician');
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $customer = Customer::where('code', 'CUST00001')->firstOrFail();
        $serviceJob = ServiceJob::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => $otherTechnician->id,
            'created_by' => $admin->id,
        ]);
        Sanctum::actingAs($technician);

        $this->getJson("/api/v1/service-jobs/{$serviceJob->id}")->assertForbidden();
    }
}
