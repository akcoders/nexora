<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerEquipment;
use App\Models\CustomerGroup;
use App\Models\InspectionCondition;
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
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $permissions = collect(['customers', 'products', 'checklists', 'users', 'attendance', 'tasks', 'reports', 'settings'])
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
        $managerRole->syncPermissions(Permission::whereIn('name', ['attendance.read', 'attendance.approve', 'attendance.export', 'tasks.read', 'tasks.assign', 'reports.read', 'reports.export', 'service_jobs.read', 'service_jobs.assign', 'service_jobs.approve', 'service_jobs.view_financials'])->get());
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
            'radius_meters' => 200,
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
            'equipment_type' => ['split_ac' => 'Split AC', 'window_ac' => 'Window AC', 'cassette_ac' => 'Cassette AC', 'ductable_ac' => 'Ductable AC', 'vrv_vrf' => 'VRV / VRF', 'chiller' => 'Chiller'],
            'reschedule_reason' => ['customer_request' => 'Customer Request', 'technician_unavailable' => 'Technician Unavailable', 'parts_unavailable' => 'Parts Unavailable', 'site_closed' => 'Site Closed'],
            'cancellation_reason' => ['customer_cancelled' => 'Customer Cancelled', 'duplicate_job' => 'Duplicate Job', 'out_of_scope' => 'Out of Scope', 'other' => 'Other'],
            'unit' => ['piece' => 'Piece', 'meter' => 'Meter', 'kilogram' => 'Kilogram', 'liter' => 'Liter', 'service' => 'Service'],
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
            'company_name' => 'Nexora HVAC Services',
            'default_geofence_radius' => '200',
            'attendance_check_in_time' => '09:00',
            'attendance_check_in_grace_minutes' => '15',
            'attendance_checkout_time' => '18:00',
            'attendance_checkout_grace_minutes' => '15',
            'auto_checkout_time' => '23:59',
            'require_attendance_for_tasks' => '1',
        ])->each(fn ($value, $key) => Setting::firstOrCreate(['key' => $key], ['value' => $value, 'group' => str_starts_with($key, 'company_') ? 'company' : 'operations']));

        if ($technician->notifications()->doesntExist()) {
            $technician->notify(new TaskAssignedNotification($task));
        }
    }
}
