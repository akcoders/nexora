<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Premises;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ServiceChecklist;
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

        $permissions->each(fn (string $permission) => Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));

        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $adminRole = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $managerRole = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
        $technicianRole = Role::firstOrCreate(['name' => 'Technician', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Viewer', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());
        $adminRole->syncPermissions(Permission::where('name', 'not like', 'settings.%')->get());
        $managerRole->syncPermissions(Permission::whereIn('name', ['attendance.read', 'attendance.approve', 'attendance.export', 'tasks.read', 'tasks.assign', 'reports.read', 'reports.export'])->get());

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
