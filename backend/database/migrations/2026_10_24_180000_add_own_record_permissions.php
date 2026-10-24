<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['customers', 'service_jobs', 'tasks'] as $module) {
            Permission::findOrCreate($module.'.view_own', 'web');
        }

        Role::where('name', 'Super Admin')->where('guard_name', 'web')->first()?->givePermissionTo(['customers.view_own', 'service_jobs.view_own', 'tasks.view_own']);

        if ($technician = Role::where('name', 'Technician')->where('guard_name', 'web')->first()) {
            $technician->givePermissionTo(['service_jobs.view_own', 'tasks.view_own']);
            if (Permission::where('name', 'service_jobs.read')->where('guard_name', 'web')->exists()) {
                $technician->revokePermissionTo('service_jobs.read');
            }
        }
    }

    public function down(): void
    {
        if (($technician = Role::where('name', 'Technician')->where('guard_name', 'web')->first())
            && Permission::where('name', 'service_jobs.read')->where('guard_name', 'web')->exists()) {
            $technician->givePermissionTo('service_jobs.read');
        }

        Permission::whereIn('name', ['customers.view_own', 'service_jobs.view_own', 'tasks.view_own'])->delete();
    }
};
