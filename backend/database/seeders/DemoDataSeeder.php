<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\Customer;
use App\Models\CustomerBranch;
use App\Models\CustomerEquipment;
use App\Models\CustomerGroup;
use App\Models\DemoDataRecord;
use App\Models\EmployeeProfile;
use App\Models\ExpenseVoucher;
use App\Models\InspectionCondition;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Premises;
use App\Models\Product;
use App\Models\ServiceCatalogItem;
use App\Models\ServiceChecklist;
use App\Models\ServiceJob;
use App\Models\ServiceType;
use App\Models\Task;
use App\Models\User;
use App\Services\ComplaintOptions;
use App\Services\ComplaintService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $primaryTechnician = User::where('email', 'technician@nexora.test')->firstOrFail();
        $premises = Premises::where('name', 'Nexora Head Office')->firstOrFail();
        $product = Product::where('code', 'PRD00001')->firstOrFail();
        $serviceType = ServiceType::where('code', 'ST-GENERAL')->firstOrFail();
        $catalogItem = ServiceCatalogItem::where('code', 'SVC-GENERAL')->firstOrFail();
        $checklist = ServiceChecklist::where('phase', 'pre')->with('items')->firstOrFail();
        $condition = InspectionCondition::where('code', 'OK')->firstOrFail();
        $leaveType = LeaveType::where('code', 'CL')->firstOrFail();

        if (is_file(public_path('images/ac-unit-card.png'))) {
            Storage::disk('public')->put('demo/ac-unit-card.png', file_get_contents(public_path('images/ac-unit-card.png')));
        }

        $names = ['Demo Corporate Customer', 'Apex Hospitals', 'BlueSky Hotels', 'Crescent Mall', 'Delta Manufacturing', 'Evergreen School', 'Fusion Coworking', 'Grand Residency', 'Horizon Data Center', 'Indus Pharma'];
        $cities = ['Mumbai', 'Pune', 'Ahmedabad', 'Delhi', 'Nashik', 'Jaipur', 'Bengaluru', 'Hyderabad', 'Chennai', 'Kolkata'];
        $states = ['Maharashtra', 'Maharashtra', 'Gujarat', 'Delhi', 'Maharashtra', 'Rajasthan', 'Karnataka', 'Telangana', 'Tamil Nadu', 'West Bengal'];
        $languages = ['Hindi', 'Marathi', 'Gujarati', 'Punjabi', 'Marathi', 'Hindi', 'Kannada', 'Telugu', 'Tamil', 'Bengali'];
        $groupNames = ['Enterprise Customers', 'Healthcare Network', 'Hospitality Accounts', 'Retail & Malls', 'Industrial Clients', 'Education Campuses', 'Corporate Offices', 'Residential Premium', 'Critical Infrastructure', 'Pharma & Labs'];
        $complaints = ['Quarterly preventive maintenance', 'ICU AC not cooling', 'Guest room AC noisy', 'Food court cassette AC leakage', 'Plant chiller temperature high', 'Classroom AC airflow low', 'Meeting room AC remote issue', 'Lobby AC drain blockage', 'Server room precision cooling alert', 'Clean-room temperature fluctuation'];
        $statuses = ['assigned', 'confirmed', 'on_the_way', 'arrived', 'inspection_in_progress', 'inspection_completed', 'estimate_pending_approval', 'customer_approved', 'service_in_progress', 'completed'];
        $historyStages = ['assigned', 'confirmed', 'on_the_way', 'arrived', 'inspection_in_progress', 'inspection_completed', 'estimate_pending_approval', 'customer_approved', 'service_in_progress', 'completed'];

        foreach (range(1, 10) as $case) {
            $scenarioKey = 'hvac-demo-'.str_pad((string) $case, 2, '0', STR_PAD_LEFT);
            $group = CustomerGroup::withTrashed()->updateOrCreate(
                ['code' => $case === 1 ? 'GRP0001' : 'DEMO-GRP-'.str_pad((string) $case, 2, '0', STR_PAD_LEFT)],
                ['name' => $groupNames[$case - 1], 'description' => "Demo group for {$groupNames[$case - 1]}", 'status' => 'active', 'deleted_at' => null],
            );
            $this->register($group, $scenarioKey);

            $technician = User::updateOrCreate(['email' => 'demo.tech'.str_pad((string) $case, 2, '0', STR_PAD_LEFT).'@nexora.test'], [
                'name' => 'Demo Technician '.str_pad((string) $case, 2, '0', STR_PAD_LEFT),
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'employee_code' => 'NX-DEMO-TECH-'.str_pad((string) $case, 2, '0', STR_PAD_LEFT),
                'phone' => '900000'.str_pad((string) $case, 4, '0', STR_PAD_LEFT),
                'department' => 'Service',
                'designation' => 'HVAC Technician',
                'status' => 'active',
            ]);
            $technician->syncRoles(['Technician']);
            $technician->premises()->syncWithoutDetaching([$premises->id]);
            $this->register($technician, $scenarioKey);

            EmployeeProfile::updateOrCreate(['user_id' => $technician->id], [
                'first_name' => 'Demo',
                'last_name' => 'Technician '.str_pad((string) $case, 2, '0', STR_PAD_LEFT),
                'date_of_joining' => now()->subMonths(6 + $case)->toDateString(),
                'employment_type' => $case % 3 === 0 ? 'probation' : 'permanent',
                'salary_currency' => 'INR',
                'pay_frequency' => 'monthly',
                'basic_salary' => 20000 + ($case * 1000),
                'hra' => 8000,
                'transport_allowance' => 2000,
                'gross_monthly_salary' => 30000 + ($case * 1000),
                'bank_details' => ['bank_name' => 'Demo Bank', 'account_number' => 'DEMO'.str_pad((string) $case, 8, '0', STR_PAD_LEFT), 'ifsc' => 'DEMO0000001'],
                'created_by' => $admin->id,
            ]);
            LeaveBalance::updateOrCreate(
                ['user_id' => $technician->id, 'leave_type_id' => $leaveType->id, 'year' => now()->year],
                ['allocated' => 12, 'used' => $case % 4, 'pending' => 1],
            );
            LeaveType::query()->where('id', '!=', $leaveType->id)->where('active', true)->each(fn (LeaveType $type) => LeaveBalance::updateOrCreate(
                ['user_id' => $technician->id, 'leave_type_id' => $type->id, 'year' => now()->year],
                ['allocated' => $type->annual_quota, 'used' => 0, 'pending' => 0],
            ));
            LeaveRequest::updateOrCreate(
                ['user_id' => $technician->id, 'leave_type_id' => $leaveType->id, 'from_date' => today()->addDays(20 + $case)->toDateString()],
                ['to_date' => today()->addDays(20 + $case)->toDateString(), 'total_days' => 1, 'reason' => 'Demo leave request '.$case, 'status' => 'pending'],
            );
            ExpenseVoucher::updateOrCreate(['voucher_no' => 'DEMO-EXP-'.str_pad((string) $case, 3, '0', STR_PAD_LEFT)], [
                'user_id' => $technician->id,
                'expense_date' => today()->subDays($case)->toDateString(),
                'category' => $case % 2 === 0 ? 'Travel' : 'Material',
                'amount' => 250 + ($case * 100),
                'description' => 'Demo field expense voucher '.$case,
                'receipt_path' => 'demo/ac-unit-card.png',
                'status' => $case % 3 === 0 ? 'approved' : 'pending',
            ]);

            $customerCode = $case === 1 ? 'CUST00001' : 'CUST'.str_pad((string) $case, 5, '0', STR_PAD_LEFT);
            $customer = Customer::withTrashed()->updateOrCreate(['code' => $customerCode], [
                'customer_group_id' => $group->id,
                'name' => $names[$case - 1],
                'mother_tongue' => $languages[$case - 1],
                'types' => ['corporate'],
                'segments' => [$case === 2 ? 'healthcare' : ($case === 6 ? 'education' : 'office')],
                'priority' => $case % 4 === 0 ? 'very_high' : ($case % 2 === 0 ? 'high' : 'normal'),
                'status' => 'active',
                'branch_type' => 'multi',
                'classification' => $case <= 3 ? 'strategic' : 'regular',
                'address_line_1' => (100 + $case).' Demo Business Park',
                'area' => 'Central '.$cities[$case - 1],
                'city' => $cities[$case - 1],
                'district' => $cities[$case - 1],
                'state' => $states[$case - 1],
                'pin_code' => str_pad((string) (400000 + $case), 6, '0', STR_PAD_LEFT),
                'country' => 'India',
                'latitude' => 19.0760 + ($case / 1000),
                'longitude' => 72.8777 + ($case / 1000),
                'contact_no_1' => '910000'.str_pad((string) $case, 4, '0', STR_PAD_LEFT),
                'email_1' => 'facility'.$case.'@demo.nexora.test',
                'gstin' => str_pad((string) (20 + $case), 2, '0', STR_PAD_LEFT).'ABCDE1234F1Z5',
                'pan' => 'ABCDE'.str_pad((string) $case, 4, '0', STR_PAD_LEFT).'F',
                'gst_registration_type' => 'regular',
                'created_by' => $admin->id,
                'deleted_at' => null,
            ]);
            $this->register($customer, $scenarioKey);

            $branch = $customer->branches()->updateOrCreate(['code' => $customer->code.'-B01'], [
                'name' => $cities[$case - 1].' Service Site',
                'address_line_1' => (20 + $case).' Service Avenue',
                'area' => 'Central '.$cities[$case - 1],
                'city' => $cities[$case - 1],
                'district' => $cities[$case - 1],
                'state' => $states[$case - 1],
                'pin_code' => str_pad((string) (400100 + $case), 6, '0', STR_PAD_LEFT),
                'country' => 'India',
                'contact_no_1' => $customer->contact_no_1,
                'email_1' => $customer->email_1,
                'gstin' => $customer->gstin,
                'gst_registration_type' => 'regular',
                'active' => true,
            ]);

            $customer->contacts()->updateOrCreate(['email' => 'contact'.$case.'@demo.nexora.test'], [
                'customer_branch_id' => null,
                'name' => 'Demo Contact '.$case,
                'designation' => 'Facility Manager',
                'department' => 'Engineering',
                'phone' => $customer->contact_no_1,
                'whatsapp' => $customer->contact_no_1,
                'mother_tongue' => $languages[$case - 1],
                'is_primary' => true,
                'is_service' => true,
                'is_billing' => $case % 2 === 0,
            ]);
            $customer->contacts()->updateOrCreate(['email' => 'branch'.$case.'@demo.nexora.test'], [
                'customer_branch_id' => $branch->id,
                'name' => 'Branch Contact '.$case,
                'designation' => 'Site Supervisor',
                'department' => 'Maintenance',
                'phone' => '920000'.str_pad((string) $case, 4, '0', STR_PAD_LEFT),
                'mother_tongue' => $languages[$case - 1],
                'is_service' => true,
            ]);
            $customer->creditTerms()->updateOrCreate([], [
                'credit_limit' => 50000 * $case,
                'credit_days' => 15 + $case,
                'payment_mode' => $case % 2 === 0 ? 'credit' : 'online',
                'po_mandatory' => $case % 3 === 0,
            ]);

            $equipment = CustomerEquipment::updateOrCreate([
                'customer_id' => $customer->id,
                'serial_no' => 'DEMO-AC-'.str_pad((string) $case, 3, '0', STR_PAD_LEFT),
            ], [
                'customer_branch_id' => $branch->id,
                'product_id' => $product->id,
                'equipment_type' => 'Split AC',
                'brand' => ['Daikin', 'Blue Star', 'Carrier', 'Voltas', 'Hitachi'][$case % 5],
                'model' => 'NX-DEMO-'.$case,
                'capacity' => ($case % 3 + 1).'.0 Ton',
                'location' => ['Conference Room', 'Server Room', 'Lobby', 'Office Floor'][$case % 4],
                'installed_at' => now()->subMonths($case * 2)->toDateString(),
                'warranty_ends_at' => now()->addMonths(24 - $case)->toDateString(),
                'active' => true,
            ]);

            $taskAssignee = $case === 1 ? $primaryTechnician : $technician;
            $task = Task::withTrashed()->updateOrCreate(['task_no' => 'WFLDEMO'.str_pad((string) $case, 4, '0', STR_PAD_LEFT)], [
                'task_type' => Task::TYPE_WORKFLOW,
                'title' => 'Internal workflow case '.$case.' · '.$complaints[$case - 1],
                'description' => 'Complete linked demo work with ownership, due date and activity history.',
                'customer_id' => null,
                'category' => 'Internal',
                'priority' => $case % 3 === 0 ? 'very_high' : 'high',
                'due_at' => now()->addDays($case - 4),
                'created_by' => $admin->id,
                'assigned_to' => $taskAssignee->id,
                'status' => in_array($case, [4, 8], true) ? 'closed' : 'pending',
                'closed_at' => in_array($case, [4, 8], true) ? now()->subHours($case) : null,
                'deleted_at' => null,
            ]);
            $task->actions()->firstOrCreate(['action' => 'created'], ['user_id' => $admin->id, 'assigned_to' => $taskAssignee->id, 'remark' => 'Demo workflow created and assigned.']);
            if ($task->status === 'closed') {
                $task->actions()->firstOrCreate(['action' => 'close'], ['user_id' => $taskAssignee->id, 'remark' => 'Demo workflow completed successfully.']);
            }
            $this->register($task, $scenarioKey);

            $this->register($this->seedComplaint($case, $customer, $branch, $equipment, $taskAssignee, $admin, $complaints[$case - 1]), $scenarioKey);

            $attendance = Attendance::updateOrCreate([
                'user_id' => $technician->id,
                'attendance_date' => today(),
            ], [
                'premises_id' => $premises->id,
                'checked_in_at' => today()->setTime(9, min($case, 30)),
                'checked_out_at' => today()->setTime(18, max(0, 20 - $case)),
                'check_in_latitude' => $premises->latitude,
                'check_in_longitude' => $premises->longitude,
                'check_out_latitude' => $premises->latitude,
                'check_out_longitude' => $premises->longitude,
                'distance_meters' => 10 + $case,
                'inside_premises' => true,
                'selfie_path' => 'demo/ac-unit-card.png',
                'status' => 'approved',
                'day_status' => $case % 4 === 0 ? 'half_day' : 'full_day',
            ]);
            $this->register($attendance, $scenarioKey);

            $status = $statuses[$case - 1];
            $serviceJob = ServiceJob::withTrashed()->updateOrCreate(['job_no' => 'DEMO-SRV-'.str_pad((string) $case, 3, '0', STR_PAD_LEFT)], [
                'customer_id' => $customer->id,
                'customer_branch_id' => $branch->id,
                'customer_equipment_id' => $equipment->id,
                'service_type_id' => $serviceType->id,
                'origin' => 'demo',
                'customer_phone' => $customer->contact_no_1,
                'service_address' => $branch->address_line_1.', '.$branch->city.', '.$branch->state,
                'latitude' => $customer->latitude,
                'longitude' => $customer->longitude,
                'complaint' => $complaints[$case - 1],
                'priority' => $case % 3 === 0 ? 'emergency' : ($case % 2 === 0 ? 'high' : 'normal'),
                'preferred_visit_date' => today()->addDays($case - 5),
                'preferred_visit_time' => '10:00:00',
                'scheduled_at' => today()->addDays($case - 5)->setTime(10, 0),
                'assigned_to' => $technician->id,
                'created_by' => $admin->id,
                'notes' => 'Generated demo scenario '.$case.' of 10.',
                'status' => $status,
                'confirmed_at' => $case >= 2 ? now()->subHours(4) : null,
                'journey_started_at' => $case >= 3 ? now()->subHours(3) : null,
                'arrived_at' => $case >= 4 ? now()->subHours(2) : null,
                'inspection_completed_at' => $case >= 6 ? now()->subMinutes(90) : null,
                'service_started_at' => $case >= 9 ? now()->subHours(2) : null,
                'service_completed_at' => $case >= 10 ? now()->subHour() : null,
                'completed_at' => $case >= 10 ? now() : null,
                'final_amount' => $case >= 7 ? 1180 : 0,
                'payment_status' => $case >= 10 ? 'paid' : 'pending',
                'deleted_at' => null,
            ]);
            $this->register($serviceJob, $scenarioKey);

            $previousStatus = null;
            foreach (array_slice($historyStages, 0, $case) as $stageIndex => $stage) {
                $serviceJob->statusHistory()->updateOrCreate(['to_status' => $stage], [
                    'from_status' => $previousStatus,
                    'changed_by' => $stageIndex === 0 ? $admin->id : $technician->id,
                    'remark' => 'Demo progression: '.str($stage)->headline(),
                    'changed_at' => now()->subMinutes(($case - $stageIndex) * 20),
                ]);
                $previousStatus = $stage;
            }

            if ($case >= 2) {
                $serviceJob->visits()->updateOrCreate(['type' => 'contact', 'outcome' => 'confirmed'], ['technician_id' => $technician->id, 'scheduled_at' => $serviceJob->scheduled_at, 'occurred_at' => now()->subHours(3), 'remark' => 'Customer confirmed demo visit.']);
            }
            if ($case >= 4) {
                $serviceJob->visits()->updateOrCreate(['type' => 'arrival', 'outcome' => 'arrived'], ['technician_id' => $technician->id, 'occurred_at' => now()->subHours(2), 'latitude' => $customer->latitude, 'longitude' => $customer->longitude, 'distance_meters' => 28, 'arrival_method' => 'manual']);
            }
            if ($case >= 5) {
                $inspection = $serviceJob->inspections()->updateOrCreate(['phase' => 'pre'], [
                    'technician_id' => $technician->id,
                    'service_checklist_id' => $checklist->id,
                    'status' => $case >= 6 ? 'completed' : 'in_progress',
                    'started_at' => now()->subHours(2),
                    'completed_at' => $case >= 6 ? now()->subMinutes(80) : null,
                ]);
                foreach ($checklist->items->take(5) as $item) {
                    $inspection->items()->updateOrCreate(['checklist_item_id' => $item->id], ['inspection_condition_id' => $condition->id, 'label' => $item->label, 'response_value' => 'OK', 'remark' => 'Demo reading verified.', 'completed_at' => $case >= 6 ? now()->subMinutes(80) : null]);
                }
                $serviceJob->photos()->updateOrCreate(['category' => 'before', 'path' => 'demo/ac-unit-card.png'], ['service_inspection_id' => $inspection->id, 'caption' => 'Demo before-service equipment photo', 'uploaded_by' => $technician->id]);
            }
            if ($case >= 7) {
                $estimate = $serviceJob->estimates()->updateOrCreate(['version' => 1], [
                    'status' => $case >= 8 ? 'accepted' : 'pending_approval',
                    'subtotal' => 1000,
                    'discount_amount' => 0,
                    'tax_amount' => 180,
                    'final_amount' => 1180,
                    'notes' => 'Demo service estimate.',
                    'created_by' => $technician->id,
                    'customer_name' => 'Demo Contact '.$case,
                    'decided_at' => $case >= 8 ? now()->subHour() : null,
                ]);
                $estimate->items()->updateOrCreate(['sort_order' => 0], ['service_catalog_item_id' => $catalogItem->id, 'type' => 'service', 'description' => $catalogItem->name, 'quantity' => 1, 'unit' => 'service', 'unit_rate' => 1000, 'tax_percent' => 18, 'line_subtotal' => 1000, 'tax_amount' => 180, 'line_total' => 1180]);
            }
            if ($case >= 9) {
                $serviceJob->performedServices()->updateOrCreate(['service_catalog_item_id' => $catalogItem->id], ['name' => $catalogItem->name, 'quantity' => 1, 'unit_rate' => 1000, 'tax_percent' => 18, 'line_total' => 1180, 'remark' => 'Demo work performed.', 'performed_by' => $technician->id, 'performed_at' => now()->subMinutes(40)]);
                $serviceJob->materials()->updateOrCreate(['name' => 'Demo filter consumable'], ['is_inventory' => false, 'quantity' => 1, 'unit' => 'piece', 'unit_rate' => 150, 'line_total' => 150, 'remark' => 'Demo material usage.', 'added_by' => $technician->id]);
                $serviceJob->photos()->updateOrCreate(['category' => 'after', 'path' => 'demo/ac-unit-card.png'], ['caption' => 'Demo after-service equipment photo', 'uploaded_by' => $technician->id]);
            }
            if ($case >= 10) {
                $serviceJob->payments()->updateOrCreate(['transaction_reference' => 'DEMO-UPI-0010'], ['amount' => 1180, 'method' => 'upi', 'status' => 'received', 'received_by' => $technician->id, 'paid_at' => now(), 'notes' => 'Demo payment record.']);
                $serviceJob->signatures()->updateOrCreate(['type' => 'completion'], ['signer_name' => 'Demo Contact '.$case, 'path' => 'demo/ac-unit-card.png', 'signed_at' => now()]);
                $serviceJob->feedback()->updateOrCreate([], ['customer_id' => $customer->id, 'technician_id' => $technician->id, 'token' => Str::uuid(), 'rating' => 5, 'comments' => 'Excellent demo service experience.', 'issue_still_exists' => false, 'request_callback' => false, 'submitted_at' => now()]);
            }
        }
    }

    /**
     * One demo complaint per scenario, each at a different stage of the complaint process.
     */
    private function seedComplaint(int $case, Customer $customer, CustomerBranch $branch, CustomerEquipment $equipment, User $technician, User $admin, string $nature): Complaint
    {
        $stages = [
            1 => ['status' => 'open', 'type' => 'preventive', 'priority' => 'medium', 'days' => 0, 'visits' => 0],
            2 => ['status' => 'scheduled', 'type' => 'breakdown', 'priority' => 'critical', 'days' => 0, 'visits' => 1],
            3 => ['status' => 'in_progress', 'type' => 'site_visit', 'priority' => 'high', 'days' => 1, 'visits' => 1],
            4 => ['status' => 'pending', 'type' => 'breakdown', 'priority' => 'high', 'days' => 6, 'visits' => 1, 'pending' => ['materials', 'Spare parts required', 'Compressor replacement', 'purchase', 'Arrange compressor from OEM', 0, 'material_shortage']],
            5 => ['status' => 'pending', 'type' => 'breakdown', 'priority' => 'medium', 'days' => 12, 'visits' => 1, 'pending' => ['customer', 'Customer approval pending', null, 'customer', 'Follow up on quotation approval', -1, 'approval_delay']],
            6 => ['status' => 'under_observation', 'type' => 'breakdown', 'priority' => 'medium', 'days' => 4, 'visits' => 2],
            7 => ['status' => 'completed', 'type' => 'installation', 'priority' => 'low', 'days' => 3, 'visits' => 1],
            8 => ['status' => 'closed', 'type' => 'breakdown', 'priority' => 'high', 'days' => 9, 'visits' => 2],
            9 => ['status' => 'pending', 'type' => 'breakdown', 'priority' => 'critical', 'days' => 17, 'visits' => 2, 'pending' => ['technical', 'OEM support required', null, 'oem', 'Escalate to OEM service head', 1, 'technical_complexity']],
            10 => ['status' => 'closed', 'type' => 'preventive', 'priority' => 'medium', 'days' => 20, 'visits' => 1],
        ];
        $stage = $stages[$case];
        $closed = $stage['status'] === 'closed';
        $registeredAt = now()->subDays($stage['days'])->setTime(10, $case);
        $sla = ComplaintOptions::slaFor($stage['priority']);
        $existing = Complaint::withTrashed()->where('description', 'like', 'Demo complaint case '.$case.' %')->first();
        [$financialYear, $sequence, $number] = $existing
            ? [$existing->financial_year, $existing->sequence, $existing->complaint_no]
            : app(ComplaintService::class)->nextNumber($registeredAt);
        $pending = $stage['pending'] ?? null;
        $contract = in_array($case % 5, [1, 3], true);

        $complaint = Complaint::withTrashed()->updateOrCreate(['complaint_no' => $number], [
            'financial_year' => $financialYear, 'sequence' => $sequence, 'registered_at' => $registeredAt,
            'customer_id' => $customer->id, 'customer_branch_id' => $branch->id, 'site_location' => $branch->name,
            'address' => collect([$customer->address_line_1, $customer->city, $customer->state])->filter()->join(', '),
            'contact_person' => 'Demo Contact '.$case, 'contact_no' => '920000'.str_pad((string) $case, 4, '0', STR_PAD_LEFT),
            'complaint_given_by' => 'Demo Contact '.$case, 'call_taken_by' => $admin->id,
            'call_mode' => ['phone', 'whatsapp', 'email', 'portal', 'amc_visit'][$case % 5],
            'complaint_source' => ['customer', 'amc_pm', 'technician', 'architect', 'internal'][$case % 5],
            'call_type' => $stage['type'], 'nature_of_complaint' => $nature, 'description' => 'Demo complaint case '.$case.' · '.$nature,
            'priority' => $stage['priority'], 'sla_response_hours' => $sla['response'], 'sla_resolution_hours' => $sla['resolution'],
            'response_due_at' => $registeredAt->copy()->addHours($sla['response']), 'resolution_due_at' => $registeredAt->copy()->addHours($sla['resolution']),
            'first_response_at' => $stage['visits'] > 0 && $stage['status'] !== 'scheduled' ? $registeredAt->copy()->addHours(2) : null,
            'unit_location' => $equipment->location, 'customer_equipment_id' => $equipment->id, 'equipment_tag' => $equipment->serial_no,
            'equipment_type' => 'split', 'equipment_criticality' => $case % 4 === 0 ? 'critical' : 'normal', 'make' => $equipment->brand,
            'model' => $equipment->model, 'capacity' => $equipment->capacity, 'serial_no' => $equipment->serial_no, 'refrigerant' => 'R32',
            'compressor_type' => 'rotary', 'technology' => $case % 2 ? 'inverter' : 'non_inverter', 'power_supply' => 'single_phase',
            'installation_date' => $equipment->installed_at, 'service_category' => ['warranty', 'amc', 'paid', 'company_amc', 'gsc'][$case % 5],
            'contract_no' => $contract ? 'AMC-26/27-'.str_pad((string) $case, 3, '0', STR_PAD_LEFT) : null,
            'contract_expiry' => $contract ? now()->addMonths(6)->toDateString() : null,
            'status' => $stage['status'], 'assigned_to' => $stage['visits'] > 0 ? $technician->id : null,
            'pending_group' => $pending[0] ?? null, 'pending_reason' => $pending[1] ?? null, 'pending_sub_reason' => $pending[2] ?? null,
            'pending_owner' => $pending[3] ?? null, 'responsible_user_id' => $pending ? $admin->id : null,
            'next_action' => $pending[4] ?? ($stage['status'] === 'under_observation' ? 'Check temperature log after 48 hours' : null),
            'follow_up_date' => $pending ? now()->addDays($pending[5])->toDateString() : ($stage['status'] === 'under_observation' ? today()->toDateString() : null),
            'target_date' => $pending ? now()->addDays(5)->toDateString() : null, 'delay_reason' => $pending[6] ?? null,
            'pending_since' => $pending || $stage['status'] === 'under_observation' ? $registeredAt->copy()->addDay() : null,
            'visit_charges' => $closed ? 500 : 0, 'labour_charges' => $closed ? 800 : 0, 'gst_percent' => 18,
            'gst_amount' => $closed ? 234 : 0, 'total_amount' => $closed ? 1534 : 0, 'payment_status' => $closed ? 'paid' : 'not_applicable',
            'fsr_checked' => $closed, 'fsr_checked_by' => $closed ? $admin->id : null, 'happy_call_required' => true,
            'happy_call_at' => $closed ? now()->subDay() : null, 'happy_call_by' => $closed ? $admin->id : null,
            'customer_feedback' => $closed ? 'Unit cooling well, technician was polite.' : null,
            'customer_rating' => $closed ? 5 : null, 'team_rating' => $closed ? 4 : null,
            'root_cause' => in_array($stage['status'], ['closed', 'completed'], true) ? 'Lack of maintenance' : null,
            'complaint_category' => in_array($stage['status'], ['closed', 'completed'], true) ? 'Mechanical' : null,
            'closed_at' => $closed ? now()->subDay() : null, 'closed_by' => $closed ? $admin->id : null, 'closure_approved_by' => $closed ? $admin->id : null,
            'created_by' => $admin->id, 'updated_by' => $admin->id, 'deleted_at' => null,
        ]);

        $complaint->activities()->firstOrCreate(['type' => 'created'], ['user_id' => $admin->id, 'title' => 'Complaint registered', 'status_to' => 'open', 'created_at' => $registeredAt]);
        for ($visitNo = 1; $visitNo <= $stage['visits']; $visitNo++) {
            $isLast = $visitNo === $stage['visits'];
            $visitStatus = match (true) {
                ! $isLast => 'completed',
                $stage['status'] === 'scheduled' => 'scheduled',
                $stage['status'] === 'in_progress' => 'on_site',
                default => 'completed',
            };
            $outcome = $visitStatus !== 'completed' ? null : (! $isLast ? 'pending' : match ($stage['status']) {
                'pending' => 'pending',
                'under_observation' => 'observation',
                default => 'resolved',
            });
            $started = $visitStatus !== 'scheduled';
            $done = $visitStatus === 'completed';
            $at = $registeredAt->copy()->addDays($visitNo - 1)->addHours(3);
            $visit = $complaint->visits()->updateOrCreate(['visit_no' => $visitNo], [
                'status' => $visitStatus, 'reason_for_visit' => $visitNo === 1 ? 'Diagnosis' : 'Part replacement and testing',
                'technician_id' => $technician->id, 'assigned_by' => $admin->id, 'assigned_at' => $at->copy()->subHours(2), 'assignment_method' => 'whatsapp',
                'appointment_at' => $stage['status'] === 'scheduled' ? now()->addDay()->setTime(14, 0) : $at,
                'special_instructions' => 'Call the customer 30 minutes before reaching site.',
                'accepted_at' => $started ? $at->copy()->subHour() : null, 'en_route_at' => $started ? $at->copy()->subMinutes(40) : null,
                'arrived_at' => $started ? $at : null, 'started_at' => $started ? $at->copy()->addMinutes(10) : null,
                'ended_at' => $done ? $at->copy()->addHours(2) : null, 'travel_minutes' => $started ? 40 : null, 'labour_hours' => $done ? 1.83 : null,
                'checks' => $done ? ['unit_checked_before' => 'yes', 'photo_taken' => 'yes', 'informed_customer' => 'yes', 'informed_back_office' => 'yes', 'unit_checked_after' => 'yes', 'equipment_working_normally' => $outcome === 'resolved' ? 'yes' : 'no', 'customer_confirmation' => 'yes'] : null,
                'diagnosis' => $started ? ['suction_pressure' => 65 + $case, 'discharge_pressure' => 280 + $case, 'voltage' => 230, 'current' => 6.4, 'ambient_temp' => 34, 'idu_supply_temp' => 14, 'idu_return_temp' => 26, 'delta_t' => 12, 'compressor_condition' => $outcome === 'pending' ? 'faulty' : 'good', 'filter_condition' => 'poor'] : null,
                'observation' => 'Unit checked as per standard procedure.',
                'fault_category' => $outcome === 'pending' ? 'Refrigerant circuit' : 'Airflow',
                'root_cause' => $outcome === 'resolved' ? 'Lack of maintenance' : ($outcome === 'pending' ? 'Compressor failure' : null),
                'problem_diagnosis' => $done ? ($outcome === 'pending' ? 'Compressor drawing high current; needs replacement.' : 'Choked filters and low airflow.') : null,
                'work_performed' => $done ? ($outcome === 'pending' ? 'Diagnosed fault, informed customer and back office.' : 'Cleaned filters and coils, tested cooling.') : null,
                'final_result' => $done ? (in_array($outcome, ['resolved', 'observation'], true) ? 'working' : 'not_working') : null,
                'outcome' => $outcome, 'customer_signed_name' => $done ? 'Demo Contact '.$case : null,
                'customer_rating' => $done ? 4 : null, 'created_by' => $admin->id,
            ]);
            $complaint->activities()->firstOrCreate(['type' => 'visit_assigned', 'complaint_visit_id' => $visit->id], ['user_id' => $admin->id, 'title' => $visit->label().' assigned to '.$technician->name, 'created_at' => $at->copy()->subHours(2)]);
            if ($outcome === 'pending' && $isLast) {
                $complaint->parts()->updateOrCreate(['complaint_visit_id' => $visit->id, 'kind' => 'recommended'], [
                    'part_name' => 'Rotary compressor', 'part_code' => 'CMP-R32-15', 'category' => 'Compressor', 'make' => $equipment->brand,
                    'quantity' => 1, 'uom' => 'nos', 'part_type' => 'warranty', 'estimated_cost' => 9500, 'billable_rate' => 12500,
                    'recommended_action' => 'Replace compressor', 'customer_approval_required' => true, 'customer_approval_status' => 'pending',
                    'approval_status' => 'pending', 'purchase_status' => 'requested', 'estimated_days' => 4,
                ]);
            }
        }

        return $complaint;
    }

    private function register(Model $model, string $scenarioKey): void
    {
        DemoDataRecord::updateOrCreate([
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
        ], ['scenario_key' => $scenarioKey]);
    }
}
