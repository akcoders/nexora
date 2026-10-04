<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerBranch;
use App\Models\CustomerFloorPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_open_customer_workspace_and_map_an_ac_unit(): void
    {
        Storage::fake('public');
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $customer = Customer::where('code', 'CUST00001')->firstOrFail();

        $this->actingAs($admin)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Customer equipment map')
            ->assertSee($customer->name);

        $this->actingAs($admin)->post(route('customers.floor-plans.store', $customer), [
            'name' => 'Head office layout',
            'floor_label' => 'First floor',
            'image' => UploadedFile::fake()->image('floor-plan.png', 1200, 800),
        ])->assertRedirect(route('customers.show', $customer));

        $floorPlan = CustomerFloorPlan::where('customer_id', $customer->id)->firstOrFail();
        Storage::disk('public')->assertExists($floorPlan->image_path);

        $this->actingAs($admin)->post(route('customers.equipment.store', $customer), [
            'equipment_type' => 'Split AC',
            'brand' => 'Daikin',
            'model' => 'FTKF50',
            'serial_no' => 'MAP-AC-001',
            'capacity' => '1.5 Ton',
            'location' => 'Conference Room',
            'customer_floor_plan_id' => $floorPlan->id,
            'plan_x' => 42.25,
            'plan_y' => 61.50,
        ])->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseHas('customer_equipments', [
            'customer_id' => $customer->id,
            'customer_floor_plan_id' => $floorPlan->id,
            'serial_no' => 'MAP-AC-001',
            'location' => 'Conference Room',
        ]);
    }

    public function test_customer_equipment_rejects_another_customers_branch_and_floor_plan(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $customer = Customer::where('code', 'CUST00001')->firstOrFail();
        $otherCustomer = Customer::factory()->create();
        $otherBranch = CustomerBranch::create([
            'customer_id' => $otherCustomer->id,
            'code' => 'OTHER-B01',
            'name' => 'Other branch',
        ]);
        $otherFloorPlan = CustomerFloorPlan::create([
            'customer_id' => $otherCustomer->id,
            'customer_branch_id' => $otherBranch->id,
            'name' => 'Other plan',
            'image_path' => 'floor-plans/other.png',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->from(route('customers.show', $customer))->post(route('customers.equipment.store', $customer), [
            'equipment_type' => 'Split AC',
            'location' => 'Server Room',
            'customer_branch_id' => $otherBranch->id,
            'customer_floor_plan_id' => $otherFloorPlan->id,
            'plan_x' => 50,
            'plan_y' => 50,
        ])->assertSessionHasErrors(['customer_branch_id', 'customer_floor_plan_id']);

        $this->assertDatabaseMissing('customer_equipments', [
            'customer_id' => $customer->id,
            'location' => 'Server Room',
        ]);
    }
}
