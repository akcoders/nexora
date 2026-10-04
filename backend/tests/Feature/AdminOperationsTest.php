<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_core_operational_records(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $technician = User::where('email', 'technician@nexora.test')->firstOrFail();

        $this->actingAs($admin)->post(route('product-categories.store'), ['name' => 'VRV System'])->assertRedirect();
        $category = ProductCategory::where('name', 'VRV System')->firstOrFail();

        $this->actingAs($admin)->post(route('checklists.store'), [
            'name' => 'VRV Commissioning',
            'product_category_id' => $category->id,
            'frequency' => 'On demand',
            'estimated_minutes' => 90,
            'items' => "Pressure test\nVacuum test\nCommission controller",
        ])->assertRedirect();
        $this->assertDatabaseHas('service_checklists', ['name' => 'VRV Commissioning']);
        $this->assertDatabaseCount('checklist_items', 8);

        $this->actingAs($admin)->post(route('products.store'), [
            'name' => 'VRV Outdoor Unit',
            'product_category_id' => $category->id,
            'brand' => 'Daikin',
            'model' => 'RXYQ',
            'warranty_months' => 24,
        ])->assertRedirect();
        $this->assertDatabaseHas('products', ['name' => 'VRV Outdoor Unit']);

        $this->actingAs($admin)->post(route('premises.store'), [
            'name' => 'Client Plant',
            'latitude' => 19.12,
            'longitude' => 72.91,
            'radius_meters' => 250,
            'shift_start' => '09:00',
            'shift_end' => '18:00',
            'technicians' => [$technician->id],
        ])->assertRedirect();
        $this->assertDatabaseHas('premises', ['name' => 'Client Plant']);

        $this->actingAs($admin)->post(route('tasks.store'), [
            'title' => 'Inspect VRV installation',
            'task_type' => 'job',
            'priority' => 'high',
            'assigned_to' => $technician->id,
            'category' => 'Inspection',
        ])->assertRedirect();
        $this->assertDatabaseHas('tasks', ['title' => 'Inspect VRV installation', 'assigned_to' => $technician->id]);

        $this->actingAs($admin)->put(route('settings.update'), [
            'company_name' => 'Nexora HVAC Services',
            'default_geofence_radius' => 300,
            'attendance_check_in_time' => '09:00',
            'attendance_check_in_grace_minutes' => 15,
            'attendance_checkout_time' => '18:00',
            'attendance_checkout_grace_minutes' => 15,
            'auto_checkout_time' => '23:59',
            'require_attendance_for_tasks' => 1,
        ])->assertRedirect();
        $this->assertDatabaseHas('settings', ['key' => 'default_geofence_radius', 'value' => '300']);
    }
}
