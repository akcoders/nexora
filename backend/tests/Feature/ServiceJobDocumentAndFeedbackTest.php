<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ServiceFeedback;
use App\Models\ServiceJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ServiceJobDocumentAndFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_downloads_service_report_and_proforma_pdfs(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $technician = User::where('email', 'technician@nexora.test')->firstOrFail();
        $customer = Customer::where('code', 'CUST00001')->firstOrFail();
        $serviceJob = ServiceJob::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => $technician->id,
            'created_by' => $admin->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'final_amount' => 944,
            'completed_at' => now(),
        ]);

        $report = $this->actingAs($admin)->get(route('service-jobs.report', $serviceJob));
        $report->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $report->getContent());

        $proforma = $this->actingAs($admin)->get(route('service-jobs.proforma', $serviceJob));
        $proforma->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $proforma->getContent());
    }

    public function test_customer_submits_feedback_once_using_public_token(): void
    {
        $this->seed();
        $technician = User::where('email', 'technician@nexora.test')->firstOrFail();
        $customer = Customer::where('code', 'CUST00001')->firstOrFail();
        $serviceJob = ServiceJob::factory()->create([
            'customer_id' => $customer->id,
            'assigned_to' => $technician->id,
            'status' => 'completed',
        ]);
        $feedback = ServiceFeedback::create([
            'service_job_id' => $serviceJob->id,
            'customer_id' => $customer->id,
            'technician_id' => $technician->id,
            'token' => (string) Str::uuid(),
        ]);

        $this->get(route('service-feedback.show', $feedback->token))
            ->assertOk()
            ->assertSee($serviceJob->job_no);

        $this->post(route('service-feedback.store', $feedback->token), [
            'rating' => 4,
            'comments' => 'Good service.',
            'issue_still_exists' => '1',
            'request_callback' => '1',
        ])->assertRedirect(route('service-feedback.show', $feedback->token));

        $this->assertDatabaseHas('service_feedback', [
            'id' => $feedback->id,
            'rating' => 4,
            'comments' => 'Good service.',
            'issue_still_exists' => true,
            'request_callback' => true,
        ]);

        $this->post(route('service-feedback.store', $feedback->token), [
            'rating' => 1,
        ]);
        $this->assertDatabaseHas('service_feedback', ['id' => $feedback->id, 'rating' => 4]);
    }
}
