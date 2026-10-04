<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerGroupManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_group_master_creates_group_and_displays_badge(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();

        $this->actingAs($admin)->post(route('customer-groups.store'), [
            'name' => 'AMC Priority Accounts',
            'description' => 'Customers covered under priority AMC.',
            'status' => 'active',
        ])->assertRedirect();

        $group = CustomerGroup::where('name', 'AMC Priority Accounts')->firstOrFail();
        $customer = Customer::where('code', 'CUST00001')->firstOrFail();
        $customer->update(['customer_group_id' => $group->id, 'mother_tongue' => 'Hindi']);

        $this->actingAs($admin)->get(route('customers.index'))
            ->assertOk()
            ->assertSee('AMC Priority Accounts');
        $this->actingAs($admin)->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee('Mother tongue')
            ->assertSee('Hindi');
    }
}
