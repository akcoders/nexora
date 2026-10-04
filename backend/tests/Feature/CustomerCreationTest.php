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
            'address_line_1' => '101 Business Park',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'pin_code' => '400001',
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
            'address_line_1' => '12 Corporate Avenue',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'pin_code' => '411001',
            'country' => 'India',
            'contact_no_1' => '9876543210',
            'email_1' => 'office@branchless.test',
            'contacts' => [['name' => 'Contact']],
        ])->assertSessionHasErrors('branches');
    }

    public function test_multi_branch_customer_stores_branch_address_gst_and_contact_person(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/customers', [
            'name' => 'Northstar Facilities',
            'types' => ['corporate'],
            'priority' => 'normal',
            'status' => 'active',
            'branch_type' => 'multi',
            'address_line_1' => '1 Head Office Road',
            'city' => 'Delhi',
            'state' => 'Delhi',
            'pin_code' => '110001',
            'country' => 'India',
            'contact_no_1' => '9876543210',
            'email_1' => 'office@northstar.test',
            'contacts' => [['name' => 'Head Office Contact', 'is_primary' => '1']],
            'branches' => [[
                'name' => 'Mumbai Branch',
                'gstin' => '27ABCDE1234F1Z5',
                'gst_registration_type' => 'regular',
                'address_line_1' => '22 Service Lane',
                'address_line_2' => 'Near Metro Station',
                'landmark' => 'City Mall',
                'area' => 'Andheri East',
                'city' => 'Mumbai',
                'district' => 'Mumbai Suburban',
                'state' => 'Maharashtra',
                'pin_code' => '400069',
                'country' => 'India',
                'contact_no_1' => '9123456780',
                'email_1' => 'mumbai@northstar.test',
                'contacts' => [[
                    'name' => 'Branch Manager',
                    'designation' => 'Facility Manager',
                    'phone' => '9988776655',
                    'email' => 'manager.mumbai@northstar.test',
                    'is_primary' => '1',
                ]],
            ]],
        ]);

        $response->assertRedirect(route('customers.index'));

        $customer = Customer::where('name', 'Northstar Facilities')->firstOrFail();
        $branch = $customer->branches()->firstOrFail();

        $this->assertSame('27ABCDE1234F1Z5', $branch->gstin);
        $this->assertSame('Andheri East', $branch->area);
        $this->assertSame('Mumbai Suburban', $branch->district);
        $this->assertSame('mumbai@northstar.test', $branch->email_1);
        $this->assertDatabaseHas('customer_contacts', [
            'customer_id' => $customer->id,
            'customer_branch_id' => $branch->id,
            'name' => 'Branch Manager',
            'email' => 'manager.mumbai@northstar.test',
        ]);
    }
}
