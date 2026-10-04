<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_single_branch_customer(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/customers', [
            'name' => 'Acme Cooling Limited',
            'types' => ['corporate'],
            'priority' => 'high',
            'status' => 'active',
            'branch_type' => 'single',
            'contact_no_1' => '9876543210',
            'email_1' => 'facility@acme.test',
            'country' => 'India',
            'contacts' => [['name' => 'Priya Shah', 'email' => 'priya@acme.test', 'is_primary' => '1']],
            'credit' => ['credit_days' => 30, 'credit_limit' => 100000],
        ]);

        $response->assertRedirect(route('customers.index'));
        $customer = Customer::where('name', 'Acme Cooling Limited')->firstOrFail();
        $this->assertSame('CUST00001', $customer->code);
        $this->assertCount(1, $customer->contacts);
        $this->assertSame(30, $customer->creditTerms->credit_days);
    }

    public function test_multi_branch_customer_requires_a_branch(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/customers', [
            'name' => 'Branchless Limited',
            'types' => ['corporate'],
            'priority' => 'normal',
            'status' => 'active',
            'branch_type' => 'multi',
            'contact_no_1' => '9876543210',
            'email_1' => 'office@branchless.test',
            'contacts' => [['name' => 'Contact']],
        ])->assertSessionHasErrors('branches');
    }
}
