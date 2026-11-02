<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintVisit;
use App\Models\Customer;
use App\Models\CustomerEquipment;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ComplaintVisitAssignedNotification;
use App\Services\ComplaintOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ComplaintManagementTest extends TestCase
{
    use RefreshDatabase;

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

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
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
        ], $overrides);
    }

    private function register(array $overrides = []): Complaint
    {
        $this->actingAs($this->admin)->post(route('complaints.store'), $this->payload($overrides))->assertSessionHasNoErrors()->assertRedirect();

        return Complaint::query()->latest('id')->firstOrFail();
    }

    public function test_registration_numbers_the_complaint_per_financial_year_and_applies_the_priority_sla(): void
    {
        Setting::updateOrCreate(['key' => 'complaint_prefix'], ['value' => 'CCSPL']);
        Setting::updateOrCreate(['key' => 'complaint_branch_code'], ['value' => 'MUM']);
        $this->travelTo(now()->setDate(2026, 10, 10)->setTime(10, 0));

        $first = $this->register();
        $second = $this->register(['priority' => 'critical']);

        $this->assertSame('26-27', $first->financial_year);
        $this->assertSame('CCSPL/MUM/26-27/'.str_pad((string) $first->sequence, 3, '0', STR_PAD_LEFT), $first->complaint_no);
        $this->assertSame($first->sequence + 1, $second->sequence);
        $this->assertSame(4, $first->sla_response_hours);
        $this->assertSame(48, $first->sla_resolution_hours);
        $this->assertTrue($first->resolution_due_at->equalTo(now()->addHours(48)));
        $this->assertSame(2, $second->sla_response_hours);
        $this->assertSame('open', $first->status);
        $this->assertSame($this->admin->id, $first->call_taken_by);
        $this->assertDatabaseHas('complaint_activities', ['complaint_id' => $first->id, 'type' => 'created']);
    }

    public function test_a_new_customer_can_be_created_from_the_complaint_form_and_amc_needs_a_contract_number(): void
    {
        $this->actingAs($this->admin)->post(route('complaints.store'), $this->payload([
            'customer_mode' => 'new', 'customer_id' => null, 'service_category' => 'amc',
            'new_customer' => ['name' => '', 'phone' => '12'],
        ]))->assertSessionHasErrors(['new_customer.name', 'new_customer.phone', 'contract_no']);

        $complaint = $this->register([
            'customer_mode' => 'new', 'customer_id' => null,
            'new_customer' => ['name' => 'Walk-in Cafe', 'phone' => '9811122233', 'city' => 'Pune'],
        ]);

        $this->assertSame('Walk-in Cafe', $complaint->customer->name);
        $this->assertSame(['individual'], $complaint->customer->types);
        $this->assertStringStartsWith('CUST', $complaint->customer->code);
    }

    public function test_assigning_a_visit_notifies_the_technician_and_follow_up_visits_keep_the_same_complaint_number(): void
    {
        Notification::fake();
        $complaint = $this->register(['assign' => ['technician_id' => $this->technician->id, 'appointment_at' => now()->addDay()->format('Y-m-d H:i')]]);

        $this->assertSame('scheduled', $complaint->status);
        $this->assertSame($this->technician->id, $complaint->assigned_to);
        Notification::assertSentTo($this->technician, ComplaintVisitAssignedNotification::class);

        $visit = $complaint->visits()->firstOrFail();
        $this->assertSame('V01', $visit->label());
        $this->actingAs($this->admin)->post(route('complaints.visits.store', $complaint), ['technician_id' => $this->technician->id])
            ->assertSessionHasErrors('technician_id');

        $this->put(route('complaints.visits.update', [$complaint, $visit]), [
            'arrived_at' => now()->subMinutes(125)->format('Y-m-d H:i'), 'started_at' => now()->subMinutes(120)->format('Y-m-d H:i'), 'ended_at' => now()->subMinutes(5)->format('Y-m-d H:i'),
            'problem_diagnosis' => 'PCB defective', 'work_performed' => 'Diagnosed fault, informed customer', 'final_result' => 'not_working', 'outcome' => 'pending',
            'pending' => ['pending_group' => 'materials', 'pending_reason' => 'Spare parts required', 'pending_owner' => 'purchase', 'next_action' => 'Arrange PCB', 'follow_up_date' => now()->addDays(2)->toDateString(), 'target_date' => now()->addDays(4)->toDateString(), 'delay_reason' => 'material_shortage'],
            'recommended_parts' => [['part_name' => 'Inverter PCB', 'part_code' => 'PCB-01', 'quantity' => 1, 'billable_rate' => 8000, 'customer_approval_required' => 1, 'approval_status' => 'pending', 'purchase_status' => 'requested']],
        ])->assertSessionHasNoErrors();

        $complaint->refresh();
        $this->assertSame('pending', $complaint->status);
        $this->assertSame('Spare parts required', $complaint->pending_reason);
        $this->assertSame('completed', $visit->fresh()->status);
        $this->assertEqualsWithDelta(1.92, (float) $visit->fresh()->labour_hours, 0.01);
        $this->assertDatabaseHas('complaint_parts', ['complaint_id' => $complaint->id, 'kind' => 'recommended', 'part_name' => 'Inverter PCB']);

        $this->post(route('complaints.visits.store', $complaint), ['technician_id' => $this->technician->id, 'reason_for_visit' => 'PCB replacing'])->assertSessionHasNoErrors();
        $second = $complaint->visits()->where('visit_no', 2)->firstOrFail();
        $this->assertSame('V02', $second->label());
        $this->assertSame($complaint->complaint_no, $second->complaint->complaint_no);
        $this->assertSame('assigned', $complaint->fresh()->status);
    }

    public function test_a_complaint_cannot_be_pending_without_reason_owner_action_and_dates(): void
    {
        $complaint = $this->register();

        $this->actingAs($this->admin)->post(route('complaints.pending.store', $complaint), ['remarks' => 'Waiting'])
            ->assertSessionHasErrors(['pending_group', 'pending_reason', 'pending_owner', 'next_action', 'follow_up_date', 'target_date', 'delay_reason']);
        $this->assertSame('open', $complaint->fresh()->status);

        $visit = $this->assignVisit($complaint);
        $this->put(route('complaints.visits.update', [$complaint, $visit]), ['problem_diagnosis' => 'x', 'work_performed' => 'y', 'final_result' => 'not_working', 'outcome' => 'pending'])
            ->assertSessionHasErrors(['pending.pending_reason', 'pending.follow_up_date']);
        $this->put(route('complaints.visits.update', [$complaint, $visit]), ['problem_diagnosis' => 'x', 'work_performed' => 'y', 'final_result' => 'not_working', 'outcome' => 'resolved'])
            ->assertSessionHasErrors(['final_result']);
    }

    public function test_resolved_visit_then_closure_with_fsr_check_and_happy_call(): void
    {
        $complaint = $this->register();
        $visit = $this->assignVisit($complaint);

        $this->actingAs($this->admin)->put(route('complaints.visits.update', [$complaint, $visit]), [
            'problem_diagnosis' => 'Choked filters', 'work_performed' => 'Cleaned filters and coils', 'final_result' => 'working', 'outcome' => 'resolved',
            'root_cause' => 'Lack of maintenance', 'diagnosis' => ['idu_supply_temp' => 14, 'idu_return_temp' => 26, 'compressor_condition' => 'good'],
            'used_parts' => [['part_name' => 'Filter mesh', 'quantity' => 2, 'billable_rate' => 150]],
            'signature_data' => 'data:image/png;base64,'.base64_encode(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')),
            'customer_signed_name' => 'Ramesh Shah', 'customer_rating' => 5,
        ])->assertSessionHasNoErrors();

        $complaint->refresh();
        $this->assertSame('completed', $complaint->status);
        $this->assertSame('300.00', (string) $complaint->parts_charges);
        $this->assertSame('354.00', (string) $complaint->total_amount);
        $this->assertNotNull($visit->fresh()->customer_signature_path);

        $this->post(route('complaints.close', $complaint), ['root_cause' => 'Lack of maintenance', 'complaint_category' => 'Airflow', 'happy_call_required' => 1])
            ->assertSessionHasErrors(['fsr_checked', 'happy_call_at']);

        $this->post(route('complaints.close', $complaint), [
            'fsr_checked' => 1, 'root_cause' => 'Lack of maintenance', 'complaint_category' => 'Airflow',
            'happy_call_required' => 1, 'happy_call_at' => now()->subHour()->format('Y-m-d H:i'), 'customer_feedback' => 'Happy with service', 'customer_rating' => 5, 'team_rating' => 4,
        ])->assertSessionHasNoErrors();

        $complaint->refresh();
        $this->assertSame('closed', $complaint->status);
        $this->assertSame($this->admin->id, $complaint->closed_by);
        $this->assertSame('met', $complaint->slaState()['key']);

        $this->post(route('complaints.reopen', $complaint), ['reopen_reason' => 'Same issue again'])->assertSessionHasNoErrors();
        $this->assertSame('open', $complaint->fresh()->status);
    }

    public function test_screens_render_for_office_users(): void
    {
        $complaint = $this->register(['assign' => ['technician_id' => $this->technician->id]]);
        $visit = $complaint->visits()->firstOrFail();

        $this->actingAs($this->admin)->get(route('complaints.index'))->assertOk()->assertSee($complaint->complaint_no)->assertSee('data-datatable', false);
        $this->get(route('complaints.index', ['type' => 'breakdown', 'status' => 'all', 'flag' => 'sla_breached']))->assertOk();
        $this->get(route('complaints.create'))->assertOk()->assertSee('Register complaint')->assertSee('Not cooling');
        $this->get(route('complaints.show', $complaint))->assertOk()->assertSee($complaint->complaint_no)->assertSee('V01');
        $this->get(route('complaints.show', [$complaint, 'tab' => 'closure']))->assertOk();
        $this->get(route('complaints.visits.edit', [$complaint, $visit]))->assertOk()->assertSee('Suction pressure')->assertSee('data-signature-pad', false);
        $this->get(route('complaints.pending'))->assertOk()->assertSee('Pending management');
        $this->get(route('complaint-masters.index'))->assertOk()->assertSee('Spare parts required');
        $this->get(route('complaints.print', $complaint))->assertOk()->assertSee('Field Service Report');
        $this->getJson(route('complaints.customer-context', $this->customer))->assertOk()->assertJsonPath('customer.code', 'CUST00001');
    }

    public function test_customer_context_auto_fills_equipment_history(): void
    {
        $equipment = CustomerEquipment::create(['customer_id' => $this->customer->id, 'equipment_type' => 'Cassette AC', 'brand' => 'Daikin', 'model' => 'FCQ', 'serial_no' => 'SN-77', 'warranty_ends_at' => now()->addYear(), 'active' => true]);
        $complaint = $this->register(['customer_equipment_id' => $equipment->id, 'call_type' => 'preventive']);
        $visit = $this->assignVisit($complaint);
        $this->actingAs($this->admin)->put(route('complaints.visits.update', [$complaint, $visit]), ['ended_at' => now()->format('Y-m-d H:i'), 'problem_diagnosis' => 'PM', 'work_performed' => 'PM done', 'final_result' => 'working', 'outcome' => 'resolved'])->assertSessionHasNoErrors();

        $unit = collect($this->getJson(route('complaints.customer-context', $this->customer))->json('equipment'))->firstWhere('id', $equipment->id);
        $this->assertSame('cassette', $unit['type']);
        $this->assertTrue($unit['in_warranty']);
        $this->assertSame(now()->toDateString(), $unit['last_service_date']);
        $this->assertSame(now()->toDateString(), $unit['last_pm_date']);
    }

    public function test_technicians_only_see_and_report_their_own_visits(): void
    {
        $other = User::factory()->create(['status' => 'active']);
        $other->assignRole('Technician');
        $mine = $this->register(['assign' => ['technician_id' => $this->technician->id]]);
        $theirs = $this->register(['assign' => ['technician_id' => $other->id], 'nature_of_complaint' => 'Water leakage']);

        $this->actingAs($this->technician)->get(route('complaints.index'))->assertOk()
            ->assertSee('href="'.route('complaints.show', $mine).'"', false)
            ->assertDontSee('href="'.route('complaints.show', $theirs).'"', false);
        $this->get(route('complaints.show', $theirs))->assertForbidden();
        $this->get(route('complaints.visits.edit', [$mine, $mine->visits()->first()]))->assertOk();
        $this->put(route('complaints.visits.update', [$theirs, $theirs->visits()->first()]), ['outcome' => null])->assertForbidden();
        $this->post(route('complaints.close', $mine), ['fsr_checked' => 1, 'root_cause' => 'x', 'complaint_category' => 'y'])->assertForbidden();
        $this->get(route('complaints.create'))->assertForbidden();
        $this->get(route('complaints.visits.edit', [$theirs, $mine->visits()->first()]))->assertNotFound();
    }

    public function test_pending_board_groups_by_age_band_and_follow_up_due_today(): void
    {
        $complaint = $this->register();
        $this->actingAs($this->admin)->post(route('complaints.pending.store', $complaint), [
            'pending_group' => 'customer', 'pending_reason' => 'Customer approval pending', 'pending_owner' => 'customer', 'next_action' => 'Call for approval',
            'follow_up_date' => today()->toDateString(), 'target_date' => today()->addDays(3)->toDateString(), 'delay_reason' => 'approval_delay',
        ])->assertSessionHasNoErrors();
        $complaint->update(['pending_since' => now()->subDays(8)]);

        $this->assertSame('serious', $complaint->fresh()->pendingBucket()['key']);
        $this->get(route('complaints.pending', ['bucket' => 'serious']))->assertOk()->assertSee($complaint->complaint_no);
        $this->get(route('complaints.pending', ['bucket' => 'normal']))->assertOk()->assertDontSee($complaint->complaint_no);
        $this->get(route('complaints.pending', ['follow_up' => 'today']))->assertOk()->assertSee($complaint->complaint_no);
        $this->get(route('dashboard'))->assertOk()->assertSee($complaint->complaint_no);

        $this->post(route('complaints.follow-ups.store', $complaint), ['remarks' => 'Customer will confirm tomorrow', 'follow_up_date' => today()->addDay()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame(today()->addDay()->toDateString(), $complaint->fresh()->follow_up_date->toDateString());
        $this->assertDatabaseHas('complaint_activities', ['complaint_id' => $complaint->id, 'type' => 'follow_up', 'remarks' => 'Customer will confirm tomorrow']);
    }

    public function test_complaint_masters_can_be_extended_and_age_bands_match_the_process_sheet(): void
    {
        $this->actingAs($this->admin)->post(route('complaint-masters.store'), ['type' => 'pending_reason', 'label' => 'Gate pass awaited'])->assertSessionHasErrors('group');
        $this->post(route('complaint-masters.store'), ['type' => 'pending_reason', 'label' => 'Gate pass awaited', 'group' => 'site'])->assertSessionHasNoErrors();
        $this->assertContains('Gate pass awaited', array_column(ComplaintOptions::pendingReasonTree()['site'], 'label'));

        $this->assertSame(['normal', 'normal', 'attention', 'escalation', 'serious', 'serious', 'management', 'critical'], array_map(fn (int $days) => ComplaintOptions::ageBucket($days)['key'], [0, 2, 3, 5, 7, 9, 12, 16]));
    }

    private function assignVisit(Complaint $complaint): ComplaintVisit
    {
        $this->actingAs($this->admin)->post(route('complaints.visits.store', $complaint), ['technician_id' => $this->technician->id])->assertSessionHasNoErrors();

        return $complaint->visits()->latest('visit_no')->firstOrFail();
    }
}
