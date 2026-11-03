<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintVisit;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComplaintVisitApiTest extends TestCase
{
    use RefreshDatabase;

    private const SIGNATURE_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private User $admin;

    private User $technician;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $this->technician = User::where('email', 'technician@nexora.test')->firstOrFail();
        $this->customer = Customer::where('code', 'CUST00001')->firstOrFail();
    }

    /** Registers a complaint from the web form with V01 assigned to the seeded technician. */
    private function register(array $overrides = []): Complaint
    {
        $this->actingAs($this->admin, 'web')->post(route('complaints.store'), array_replace_recursive([
            'customer_mode' => 'existing',
            'customer_id' => $this->customer->id,
            'contact_person' => 'Ramesh Shah',
            'contact_no' => '9876543210',
            'call_mode' => 'phone',
            'complaint_source' => 'customer',
            'call_type' => 'breakdown',
            'nature_of_complaint' => 'Not cooling',
            'priority' => 'high',
            'equipment_type' => 'split',
            'assign' => ['technician_id' => $this->technician->id],
        ], $overrides))->assertSessionHasNoErrors()->assertRedirect();

        return Complaint::query()->latest('id')->firstOrFail();
    }

    private function newTechnician(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->assignRole('Technician');

        return $user;
    }

    private function reportUrl(Complaint $complaint, ComplaintVisit $visit): string
    {
        return "/api/v1/complaints/{$complaint->id}/visits/{$visit->id}/report";
    }

    public function test_visit_endpoints_reject_guests_with_401(): void
    {
        $this->getJson('/api/v1/complaint-visits')->assertUnauthorized();
        $this->getJson('/api/v1/complaint-visits/1')->assertUnauthorized();
        $this->postJson('/api/v1/complaints/1/visits/1/report', [])->assertUnauthorized();
    }

    public function test_list_shows_only_the_users_open_visits_soonest_first_and_assistants_see_them_as_assistant(): void
    {
        $assistant = $this->newTechnician();
        $other = $this->newTechnician();
        $unscheduled = $this->register();
        $scheduled = $this->register(['assign' => ['appointment_at' => now()->addDay()->format('Y-m-d H:i'), 'assistant_one_id' => $assistant->id]]);
        $this->register(['assign' => ['technician_id' => $other->id]]);
        $cancelledComplaint = $this->register();
        $cancelledComplaint->update(['status' => 'cancelled']);
        $done = $this->register();
        $doneVisit = $done->visits()->firstOrFail();
        $this->put(route('complaints.visits.update', [$done, $doneVisit]), ['problem_diagnosis' => 'Choked filters', 'work_performed' => 'Cleaned', 'final_result' => 'working', 'outcome' => 'resolved'])
            ->assertSessionHasNoErrors();
        $scheduledVisit = $scheduled->visits()->firstOrFail();

        Sanctum::actingAs($this->technician);
        $open = $this->getJson('/api/v1/complaint-visits')->assertOk()
            ->assertJsonPath('data.0.label', 'V01')
            ->assertJsonPath('data.0.role', 'technician')
            ->assertJsonPath('data.0.complaint.complaint_no', $scheduled->complaint_no)
            ->assertJsonPath('data.0.complaint.customer_name', $this->customer->name)
            ->assertJsonPath('data.0.complaint.equipment_label', 'Split AC');
        $this->assertSame([$scheduledVisit->id, $unscheduled->visits()->value('id')], array_column($open->json('data'), 'id'));

        $completed = $this->getJson('/api/v1/complaint-visits?scope=completed')->assertOk()->assertJsonPath('data.0.outcome', 'resolved');
        $this->assertSame([$doneVisit->id], array_column($completed->json('data'), 'id'));
        $this->getJson('/api/v1/complaint-visits?scope=all')->assertUnprocessable()->assertJsonValidationErrors('scope');

        Sanctum::actingAs($assistant);
        $assisted = $this->getJson('/api/v1/complaint-visits')->assertOk()->assertJsonPath('data.0.role', 'assistant');
        $this->assertSame([$scheduledVisit->id], array_column($assisted->json('data'), 'id'));
    }

    public function test_another_technician_gets_403_on_show_and_report(): void
    {
        $complaint = $this->register();
        $visit = $complaint->visits()->firstOrFail();

        Sanctum::actingAs($this->newTechnician());
        $this->getJson("/api/v1/complaint-visits/{$visit->id}")->assertForbidden();
        $this->postJson($this->reportUrl($complaint, $visit), ['accepted_at' => now()->format('Y-m-d\TH:i:s')])->assertForbidden();

        $this->assertNull($visit->fresh()->accepted_at);
    }

    public function test_show_returns_the_visit_previous_visits_and_report_form_lists(): void
    {
        $complaint = $this->register();
        $first = $complaint->visits()->firstOrFail();
        $this->put(route('complaints.visits.update', [$complaint, $first]), [
            'problem_diagnosis' => 'PCB defective', 'work_performed' => 'Diagnosed fault', 'final_result' => 'not_working', 'outcome' => 'pending',
            'pending' => ['pending_group' => 'materials', 'pending_reason' => 'Spare parts required', 'pending_owner' => 'purchase', 'next_action' => 'Arrange PCB', 'follow_up_date' => now()->addDays(2)->toDateString(), 'target_date' => now()->addDays(4)->toDateString(), 'delay_reason' => 'material_shortage'],
        ])->assertSessionHasNoErrors();
        $this->post(route('complaints.visits.store', $complaint), ['technician_id' => $this->technician->id, 'reason_for_visit' => 'PCB replacing'])->assertSessionHasNoErrors();
        $second = $complaint->visits()->where('visit_no', 2)->firstOrFail();

        Sanctum::actingAs($this->technician);
        $response = $this->getJson("/api/v1/complaint-visits/{$second->id}")->assertOk()
            ->assertJsonPath('visit.label', 'V02')
            ->assertJsonPath('visit.role', 'technician')
            ->assertJsonPath('visit.reason_for_visit', 'PCB replacing')
            ->assertJsonPath('visit.team', [$this->technician->name])
            ->assertJsonPath('complaint.customer.name', $this->customer->name)
            ->assertJsonPath('complaint.equipment_type_label', 'Split AC')
            ->assertJsonPath('previous_visits.0.label', 'V01')
            ->assertJsonPath('previous_visits.0.outcome_label', 'Pending - more work needed')
            ->assertJsonPath('previous_visits.0.problem_diagnosis', 'PCB defective')
            ->assertJsonPath('form.checks.0', ['key' => 'unit_checked_before', 'label' => 'Unit checked before service?', 'stage' => 'before'])
            ->assertJsonCount(7, 'form.checks')
            ->assertJsonPath('form.outcomes.0', ['value' => 'resolved', 'label' => 'Resolved - unit working OK'])
            ->assertJsonPath('can_report', true);

        $this->assertSame(['key' => 'suction_pressure', 'label' => 'Suction pressure', 'type' => 'number', 'unit' => 'psi'], collect($response->json('form.diagnosis_fields'))->firstWhere('key', 'suction_pressure'));
        $this->assertContains(['value' => 'Lack of maintenance', 'label' => 'Lack of maintenance'], $response->json('form.root_causes'));
        $this->assertContains(['value' => 'Refrigerant circuit', 'label' => 'Refrigerant circuit'], $response->json('form.fault_categories'));
        $spareParts = collect($response->json('form.pending_reasons.materials'))->firstWhere('label', 'Spare parts required');
        $this->assertSame('Spare parts required', $spareParts['value']);
        $this->assertContains('Gas leakage', $spareParts['subs']);
    }

    public function test_progress_stamp_with_only_a_time_keeps_the_report_saved_on_the_web(): void
    {
        $this->travelTo(Carbon::parse('2026-11-02 12:00:00'));
        $complaint = $this->register();
        $visit = $complaint->visits()->firstOrFail();
        $this->put(route('complaints.visits.update', [$complaint, $visit]), [
            'observation' => 'Coil choked', 'labour_hours' => 3, 'checks' => ['photo_taken' => 'yes'],
            'diagnosis' => ['suction_pressure' => 120, 'compressor_condition' => 'good'],
            'used_parts' => [['part_name' => 'Filter mesh', 'quantity' => 2, 'billable_rate' => 150]],
            'recommended_parts' => [['part_name' => 'Inverter PCB', 'part_code' => 'PCB-01', 'quantity' => 1, 'customer_approval_required' => 1, 'approval_status' => 'pending', 'purchase_status' => 'requested']],
        ])->assertSessionHasNoErrors();

        Sanctum::actingAs($this->technician);
        $this->postJson($this->reportUrl($complaint, $visit), ['accepted_at' => '2026-11-02T11:15:00'])->assertOk()
            ->assertJsonPath('message', 'V01 progress saved.')
            ->assertJsonPath('visit.status', 'accepted')
            ->assertJsonPath('visit.checks.photo_taken', 'yes')
            ->assertJsonPath('visit.diagnosis.suction_pressure', 120)
            ->assertJsonPath('complaint_status', 'in_progress');
        // A UTC time from the app is stored as app (IST) time; one check can be answered on its own.
        $this->postJson($this->reportUrl($complaint, $visit), ['en_route_at' => '2026-11-02T06:00:00Z', 'checks' => ['informed_customer' => 'yes']])->assertOk()
            ->assertJsonPath('visit.status', 'en_route');

        $visit->refresh();
        $this->assertSame('2026-11-02 11:15:00', $visit->accepted_at->toDateTimeString());
        $this->assertSame('2026-11-02 11:30:00', $visit->en_route_at->toDateTimeString());
        $this->assertSame(['photo_taken' => 'yes', 'informed_customer' => 'yes'], $visit->checks);
        $this->assertSame(['suction_pressure' => 120, 'compressor_condition' => 'good'], $visit->diagnosis);
        $this->assertSame('Coil choked', $visit->observation);
        $this->assertSame('3.00', $visit->labour_hours);
        $used = $visit->parts()->where('kind', 'used')->sole();
        $this->assertSame(['Filter mesh', '2.00', '150.00'], [$used->part_name, $used->quantity, $used->billable_rate]);
        $recommended = $visit->parts()->where('kind', 'recommended')->sole();
        $this->assertSame(['PCB-01', 'pending', 'requested', true], [$recommended->part_code, $recommended->approval_status, $recommended->purchase_status, $recommended->customer_approval_required]);
        $this->assertSame('300.00', $complaint->fresh()->parts_charges);
    }

    public function test_resolved_outcome_from_multipart_completes_the_visit_and_the_complaint(): void
    {
        Storage::fake('public');
        $complaint = $this->register();
        $visit = $complaint->visits()->firstOrFail();

        Sanctum::actingAs($this->technician);
        $this->post($this->reportUrl($complaint, $visit), [
            'problem_diagnosis' => 'Choked filters', 'work_performed' => 'Cleaned filters and coils', 'final_result' => 'working', 'outcome' => 'resolved',
            'root_cause' => 'Lack of maintenance', 'checks' => ['equipment_working_normally' => 'yes'],
            'attachments' => [UploadedFile::fake()->image('after.jpg')],
            'signature_data' => 'data:image/png;base64,'.self::SIGNATURE_PNG, 'customer_signed_name' => 'Ramesh Shah',
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('message', 'V01 completed. The complaint is ready for closure checks.')
            ->assertJsonPath('visit.status', 'completed')
            ->assertJsonPath('visit.outcome_label', 'Resolved - unit working OK')
            ->assertJsonPath('visit.has_signature', true)
            ->assertJsonPath('visit.attachments.0.name', 'after.jpg')
            ->assertJsonPath('complaint_status', 'completed')
            ->assertJsonPath('complaint_status_label', 'Work completed');

        $visit->refresh();
        $this->assertSame('resolved', $visit->outcome);
        Storage::disk('public')->assertExists([$visit->attachments[0]['path'], $visit->customer_signature_path]);
        $complaint->refresh();
        $this->assertSame('completed', $complaint->status);
        $this->assertSame('Lack of maintenance', $complaint->root_cause);
    }

    public function test_pending_outcome_needs_the_pending_details_and_saves_the_reason_label_like_the_web(): void
    {
        $complaint = $this->register();
        $visit = $complaint->visits()->firstOrFail();
        $result = ['problem_diagnosis' => 'PCB defective', 'work_performed' => 'Diagnosed fault', 'final_result' => 'not_working', 'outcome' => 'pending'];

        Sanctum::actingAs($this->technician);
        $this->postJson($this->reportUrl($complaint, $visit), $result)->assertUnprocessable()
            ->assertJsonValidationErrors(['pending.pending_reason' => 'This is required when the visit leaves the complaint pending.'])
            ->assertJsonValidationErrors(['pending.pending_group', 'pending.pending_owner', 'pending.next_action', 'pending.follow_up_date', 'pending.target_date', 'pending.delay_reason']);
        $this->assertSame('scheduled', $visit->fresh()->status);

        $reason = collect($this->getJson("/api/v1/complaint-visits/{$visit->id}")->json('form.pending_reasons.materials'))->firstWhere('label', 'Spare parts required');
        $this->postJson($this->reportUrl($complaint, $visit), $result + ['pending' => [
            'pending_group' => 'materials', 'pending_reason' => $reason['value'], 'pending_sub_reason' => $reason['subs'][0], 'pending_owner' => 'purchase',
            'next_action' => 'Arrange PCB', 'follow_up_date' => now()->addDays(2)->toDateString(), 'target_date' => now()->addDays(4)->toDateString(), 'delay_reason' => 'material_shortage',
        ]])->assertOk()
            ->assertJsonPath('message', 'V01 completed. Complaint is pending: Spare parts required.')
            ->assertJsonPath('complaint_status', 'pending');

        $complaint->refresh();
        $this->assertSame(['Spare parts required', 'Gas leakage'], [$complaint->pending_reason, $complaint->pending_sub_reason]);
    }

    public function test_report_on_a_completed_visit_is_rejected_with_422_before_validation(): void
    {
        $complaint = $this->register();
        $visit = $complaint->visits()->firstOrFail();

        Sanctum::actingAs($this->technician);
        $this->postJson($this->reportUrl($complaint, $visit), ['problem_diagnosis' => 'Choked filters', 'work_performed' => 'Cleaned', 'final_result' => 'working', 'outcome' => 'resolved'])->assertOk();

        $this->postJson($this->reportUrl($complaint, $visit), ['outcome' => 'pending'])->assertUnprocessable()
            ->assertJsonPath('message', 'This visit is already completed.')
            ->assertJsonMissingPath('errors');
        $this->getJson("/api/v1/complaint-visits/{$visit->id}")->assertOk()->assertJsonPath('can_report', false);
        $this->assertSame('resolved', $visit->fresh()->outcome);
    }

    public function test_assignment_notification_carries_the_complaint_visit_id(): void
    {
        $complaint = $this->register();
        $visit = $complaint->visits()->firstOrFail();

        Sanctum::actingAs($this->technician);
        $this->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonFragment(['type' => 'complaint_visit', 'complaint_id' => $complaint->id, 'complaint_visit_id' => $visit->id]);
    }
}
