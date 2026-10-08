<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerEquipment;
use App\Models\CustomerGroup;
use App\Models\EmployeeProfile;
use App\Models\Holiday;
use App\Models\InspectionCondition;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Premises;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ServiceCatalogItem;
use App\Models\ServiceChecklist;
use App\Models\ServiceMasterOption;
use App\Models\ServiceType;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $permissions = collect(['customers', 'products', 'checklists', 'users', 'attendance', 'tasks', 'reports', 'settings', 'employees', 'companies', 'roles', 'hr', 'leaves', 'vouchers', 'payroll'])
            ->flatMap(fn (string $module) => collect(['create', 'read', 'update', 'delete', 'approve', 'assign', 'export'])
                ->map(fn (string $action) => "$module.$action"));

        $permissions = $permissions->merge(
            collect(['create', 'read', 'update', 'delete', 'approve', 'assign', 'export', 'cancel', 'inspect', 'create_estimate', 'approve_override', 'perform', 'payment', 'complete', 'view_financials', 'reopen'])
                ->map(fn (string $action) => "service_jobs.$action")
        );

        $permissions->each(fn (string $permission) => Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $technicianRole = Role::firstOrCreate(['name' => 'Technician', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Viewer', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());
        $adminRole->syncPermissions(Permission::where('name', 'not like', 'settings.%')->get());
        $managerRole->syncPermissions(Permission::whereIn('name', ['attendance.read', 'attendance.approve', 'attendance.export', 'tasks.read', 'tasks.assign', 'reports.read', 'reports.export', 'service_jobs.read', 'service_jobs.assign', 'service_jobs.approve', 'service_jobs.view_financials', 'employees.read', 'hr.read', 'leaves.read', 'leaves.approve', 'vouchers.read', 'vouchers.approve'])->get());
        $technicianRole->syncPermissions(Permission::whereIn('name', ['service_jobs.read', 'service_jobs.inspect', 'service_jobs.create_estimate', 'service_jobs.perform', 'service_jobs.payment', 'service_jobs.complete'])->get());

        $admin = User::updateOrCreate(['email' => 'admin@nexora.test'], [
            'name' => 'Nexora Admin',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'employee_code' => 'NX-ADMIN-001',
            'department' => 'Administration',
            'designation' => 'Administrator',
        ]);
        $admin->syncRoles([$superAdmin]);

        $technician = User::updateOrCreate(['email' => 'technician@nexora.test'], [
            'name' => 'Aarav Sharma',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'employee_code' => 'NX-TECH-001',
            'phone' => '9876543210',
            'department' => 'Service',
            'designation' => 'HVAC Technician',
        ]);
        $technician->syncRoles([$technicianRole]);

        $premises = Premises::firstOrCreate(['name' => 'Nexora Head Office'], [
            'address' => 'Mumbai, Maharashtra',
            'latitude' => 19.0760,
            'longitude' => 72.8777,
            'radius_meters' => 5,
            'shift_start' => '09:00',
            'shift_end' => '18:00',
        ]);
        $premises->users()->syncWithoutDetaching([$technician->id]);

        $group = CustomerGroup::firstOrCreate(['code' => 'GRP0001'], ['name' => 'Enterprise Customers', 'description' => 'Strategic enterprise accounts']);
        $customer = Customer::firstOrCreate(['code' => 'CUST00001'], [
            'customer_group_id' => $group->id,
            'name' => 'Demo Corporate Customer',
            'types' => ['corporate'],
            'segments' => ['office'],
            'priority' => 'high',
            'status' => 'active',
            'branch_type' => 'single',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'country' => 'India',
            'pin_code' => '400001',
            'contact_no_1' => '02240000000',
            'email_1' => 'facility@example.com',
            'created_by' => $admin->id,
        ]);

        $task = Task::firstOrCreate(['task_no' => 'TSK000001'], [
            'task_type' => 'job',
            'title' => 'Quarterly HVAC preventive maintenance',
            'description' => 'Complete the standard preventive-maintenance checklist and upload readings.',
            'customer_id' => $customer->id,
            'category' => 'Preventive Maintenance',
            'priority' => 'high',
            'due_at' => now()->addDay(),
            'created_by' => $admin->id,
            'assigned_to' => $technician->id,
            'status' => 'pending',
        ]);

        $category = ProductCategory::firstOrCreate(['name' => 'Split Air Conditioner']);
        $checklist = ServiceChecklist::firstOrCreate(['name' => 'Quarterly Split AC Service'], [
            'product_category_id' => $category->id,
            'frequency' => 'Quarterly',
            'estimated_minutes' => 60,
            'required_skill' => 'HVAC Technician',
            'safety_notes' => 'Isolate electrical supply before opening panels.',
        ]);
        if ($checklist->items()->doesntExist()) {
            collect(['Inspect and clean air filters', 'Measure inlet and outlet temperature', 'Check refrigerant pressure', 'Inspect electrical terminals', 'Clean condenser coil'])
                ->each(fn ($label, $index) => $checklist->items()->create(['label' => $label, 'required' => true, 'sort_order' => $index]));
        }
        Product::firstOrCreate(['code' => 'PRD00001'], [
            'name' => 'Inverter Split AC',
            'product_category_id' => $category->id,
            'service_checklist_id' => $checklist->id,
            'brand' => 'Daikin',
            'model' => 'FTKF50',
            'warranty_months' => 12,
        ]);

        $generalService = ServiceType::updateOrCreate(['code' => 'ST-GENERAL'], [
            'name' => 'General AC Service',
            'description' => 'Standard inspection, cleaning and performance check.',
            'default_price' => 800,
            'tax_percent' => 18,
            'estimated_minutes' => 60,
            'active' => true,
        ]);
        ServiceType::updateOrCreate(['code' => 'ST-REPAIR'], ['name' => 'AC Repair', 'default_price' => 500, 'tax_percent' => 18, 'estimated_minutes' => 90, 'active' => true]);
        ServiceType::updateOrCreate(['code' => 'ST-PM'], ['name' => 'Preventive Maintenance', 'default_price' => 1000, 'tax_percent' => 18, 'estimated_minutes' => 90, 'active' => true]);

        collect([
            ['OK', 'OK', 'success', false],
            ['CLEANING_REQUIRED', 'Cleaning Required', 'warning', true],
            ['REPAIR_REQUIRED', 'Repair Required', 'danger', true],
            ['REPLACE_REQUIRED', 'Replace Required', 'danger', true],
            ['NOT_APPLICABLE', 'Not Applicable', 'secondary', false],
            ['YES', 'Yes', 'success', false],
            ['NO', 'No', 'danger', true],
        ])->each(fn (array $condition, int $index) => InspectionCondition::updateOrCreate(['code' => $condition[0]], [
            'name' => $condition[1],
            'color' => $condition[2],
            'is_issue' => $condition[3],
            'active' => true,
            'sort_order' => $index,
        ]));

        collect([
            ['SVC-GENERAL', 'General AC Service', 'Service', 800, 18, 60],
            ['SVC-FILTER', 'Filter Cleaning', 'Cleaning', 250, 18, 20],
            ['SVC-COIL', 'Cooling Coil Cleaning', 'Cleaning', 400, 18, 30],
            ['SVC-BLOWER', 'Blower Cleaning', 'Cleaning', 350, 18, 30],
            ['SVC-OUTDOOR', 'Outdoor Unit Cleaning', 'Cleaning', 450, 18, 35],
            ['SVC-DRAIN', 'Drain Cleaning', 'Cleaning', 300, 18, 25],
            ['SVC-GAS', 'Gas Charging', 'Repair', 1200, 18, 45],
            ['SVC-ELECTRICAL', 'Electrical Repair', 'Repair', 600, 18, 45],
            ['SVC-CAPACITOR', 'Capacitor Replacement', 'Replacement', 650, 18, 30],
            ['SVC-LEAK', 'Leakage Repair', 'Repair', 900, 18, 60],
        ])->each(fn (array $service) => ServiceCatalogItem::updateOrCreate(['code' => $service[0]], [
            'name' => $service[1],
            'category' => $service[2],
            'equipment_type' => 'Split AC',
            'standard_price' => $service[3],
            'tax_percent' => $service[4],
            'estimated_minutes' => $service[5],
            'active' => true,
        ]));

        collect([
            'payment_method' => ['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'online' => 'Online', 'credit' => 'Credit / Pay Later'],
            'equipment_type' => collect(['Air Cooled Chiller', 'Water Cooled Chiller', 'Screw Chiller', 'Centrifuge Chiller', 'Recip Chiller', 'Ammonia Chiller', 'Modular Chiller', 'VRF', 'VRV', 'ODU', 'IDU', 'Indoor Unit', 'Outdoor Unit', 'Condenser Unit', 'Window AC', 'High Wall Split AC', '4 Way Cassette AC', '1 Way Cassette AC', 'Ductable AC', 'Concealed AC', 'Tower AC', 'VRF 1 Way Cassette IDU', 'VRF 4 Way Cassette IDU', 'VRF Compact Cassette IDU', 'VRF Highwall Split IDU', 'VRF LS/MS/HS Ductable IDU', 'VRF Tower AC IDU', 'IDU/ODU Refnet', 'Single/Double Skin AHU DX/CHW'])->mapWithKeys(fn (string $label) => [Str::slug($label, '_') => $label])->all(),
            'ac_capacity' => collect(['0.6', '0.8', '1', '1.3', '1.5', '1.6', '1.8', '2', '2.1', '2.2', '2.3', '2.5', '2.6', '2.9', '3', '3.3', '3.5', '3.8', '4', '4.2', '4.5', '4.8', '5', '5.5'])->mapWithKeys(fn (string $capacity) => [str_replace('.', '_', $capacity).'_tr' => $capacity.' TR'])->all(),
            'reschedule_reason' => ['customer_request' => 'Customer Request', 'technician_unavailable' => 'Technician Unavailable', 'parts_unavailable' => 'Parts Unavailable', 'site_closed' => 'Site Closed'],
            'cancellation_reason' => ['customer_cancelled' => 'Customer Cancelled', 'duplicate_job' => 'Duplicate Job', 'out_of_scope' => 'Out of Scope', 'other' => 'Other'],
            'unit' => ['tr' => 'TR', 'hp' => 'HP', 'kw' => 'kW', 'kg' => 'kg', 'sq_ft' => 'sq.ft', 'sq_mt' => 'sq.mt', 'rmt' => 'rmt', 'nos' => 'nos', 'lump_sum' => 'lump sum', 'roll' => 'roll', 'feet' => 'feet', 'metre' => 'metre'],
        ])->each(function (array $options, string $type): void {
            collect($options)->each(fn (string $label, string $code) => ServiceMasterOption::updateOrCreate(
                ['type' => $type, 'code' => $code],
                ['label' => $label, 'active' => true],
            ));
        });

        $checklist->update(['phase' => 'pre', 'equipment_type' => 'Split AC', 'description' => 'Pre-service inspection checklist.']);
        $checklist->items()->update(['response_type' => 'condition', 'photo_required' => false, 'remark_allowed' => true, 'active' => true]);
        $postChecklist = ServiceChecklist::updateOrCreate(['name' => 'Standard Post-Service Verification'], [
            'phase' => 'post',
            'service_type_id' => null,
            'equipment_type' => null,
            'description' => 'Final safety, quality and housekeeping verification.',
            'frequency' => 'Every Service',
            'estimated_minutes' => 10,
            'required_skill' => 'HVAC Technician',
            'active' => true,
        ]);
        if ($postChecklist->items()->doesntExist()) {
            collect(['AC Tested', 'Cooling Checked', 'Airflow Checked', 'Filter Cleaned', 'Drain Checked', 'No Water Leakage', 'Electrical Connection Checked', 'Noise / Vibration Checked', 'Work Area Cleaned'])
                ->each(fn (string $label, int $index) => $postChecklist->items()->create([
                    'label' => $label,
                    'response_type' => 'condition',
                    'required' => true,
                    'photo_required' => false,
                    'remark_allowed' => true,
                    'active' => true,
                    'sort_order' => $index,
                ]));
        }

        CustomerEquipment::firstOrCreate(['customer_id' => $customer->id, 'serial_no' => 'DEMO-AC-001'], [
            'product_id' => Product::where('code', 'PRD00001')->value('id'),
            'equipment_type' => 'Split AC',
            'brand' => 'Daikin',
            'model' => 'FTKF50',
            'capacity' => '1.5 Ton',
            'location' => 'Conference Room',
            'active' => true,
        ]);

        collect([
            'company_name' => 'Classic Cooling Systems Pvt. Ltd.',
            'company_short_name' => 'Classic Field Service',
            'default_geofence_radius' => '5',
            'attendance_check_in_time' => '09:00',
            'attendance_check_in_grace_minutes' => '15',
            'attendance_checkout_time' => '18:00',
            'attendance_checkout_grace_minutes' => '15',
            'auto_checkout_time' => '23:59',
            'require_attendance_for_tasks' => '1',
        ])->each(fn ($value, $key) => Setting::firstOrCreate(['key' => $key], ['value' => $value, 'group' => str_starts_with($key, 'company_') ? 'company' : 'operations']));

        $company = Company::updateOrCreate(['organization_id' => 'CCSPL - HO -001'], [
            'code' => 'CCSPL-MUM',
            'short_name' => 'CLASSIC',
            'legal_name' => 'Classic Cooling Systems Pvt. Ltd.',
            'logo_path' => 'images/brand/classic-logo.jpeg',
            'company_type' => 'head_office',
            'organization_type' => 'private_limited',
            'industry' => 'HVAC',
            'business_models' => ['sales', 'service', 'amc', 'projects'],
            'status' => 'active',
            'address' => ['country' => 'India', 'state' => 'Maharashtra', 'city' => 'Mumbai'],
            'localization' => ['timezone' => 'Asia/Kolkata', 'currency' => 'INR', 'financial_year_start' => 'April'],
            'invoice_settings' => ['prefix' => 'CCSPL', 'e_invoice' => true, 'e_way_bill' => true],
            'created_by' => $admin->id,
        ]);
        $company->sites()->updateOrCreate(['site_id' => 'CCSPL-SITE-001'], [
            'code' => 'MUM-HO',
            'name' => 'Mumbai Head Office',
            'type' => 'office',
            'address' => ['country' => 'India', 'state' => 'Maharashtra', 'city' => 'Mumbai'],
            'active' => true,
        ]);

        EmployeeProfile::firstOrCreate(['user_id' => $admin->id], [
            'first_name' => 'Nexora',
            'last_name' => 'Admin',
            'date_of_joining' => now()->subYears(3)->toDateString(),
            'employment_type' => 'permanent',
            'salary_currency' => 'INR',
            'pay_frequency' => 'monthly',
            'created_by' => $admin->id,
        ]);
        EmployeeProfile::firstOrCreate(['user_id' => $technician->id], [
            'first_name' => 'Aarav',
            'last_name' => 'Sharma',
            'date_of_joining' => now()->subYear()->toDateString(),
            'employment_type' => 'permanent',
            'basic_salary' => 22000,
            'hra' => 8000,
            'transport_allowance' => 2000,
            'gross_monthly_salary' => 32000,
            'salary_currency' => 'INR',
            'pay_frequency' => 'monthly',
            'created_by' => $admin->id,
        ]);

        collect([
            ['CL', 'Casual Leave', 12, 'primary', true, false],
            ['SL', 'Sick Leave', 12, 'danger', true, true],
            ['EL', 'Earned Leave', 18, 'success', true, false],
            ['LWP', 'Leave Without Pay', 365, 'secondary', false, false],
        ])->each(fn (array $leave) => LeaveType::updateOrCreate(['code' => $leave[0]], [
            'name' => $leave[1], 'annual_quota' => $leave[2], 'color' => $leave[3], 'paid' => $leave[4], 'requires_document' => $leave[5], 'active' => true,
        ]));
        User::query()->each(function (User $user): void {
            LeaveType::where('active', true)->each(fn (LeaveType $type) => LeaveBalance::firstOrCreate(
                ['user_id' => $user->id, 'leave_type_id' => $type->id, 'year' => now()->year],
                ['allocated' => $type->annual_quota],
            ));
        });
        Holiday::firstOrCreate(['holiday_date' => now()->startOfYear()->addMonths(7)->day(15)->toDateString()], ['name' => 'Independence Day', 'type' => 'national']);
        Holiday::firstOrCreate(['holiday_date' => now()->startOfYear()->addMonths(9)->day(2)->toDateString()], ['name' => 'Gandhi Jayanti', 'type' => 'national']);

        if ($technician->notifications()->doesntExist()) {
            $technician->notify(new TaskAssignedNotification($task));
        }

        $this->call(DemoDataSeeder::class);
    }
}
