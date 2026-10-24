<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomerCreationTest extends TestCase
{
    use RefreshDatabase;

    private CustomerGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::findOrCreate('customers.create', 'web');
        Permission::findOrCreate('customers.update', 'web');
        $this->group = CustomerGroup::create([
            'code' => 'GRP-TEST',
            'name' => 'Test Customers',
            'status' => 'active',
        ]);
    }

    public function test_authenticated_user_can_create_a_single_branch_customer(): void
    {
        $user = User::factory()->create()->givePermissionTo('customers.create');

        $response = $this->actingAs($user)->post('/customers', [
            'name' => 'Acme Cooling Limited',
            'customer_group_id' => $this->group->id,
            'mother_tongue' => 'Hindi',
            'types' => ['corporate'],
            'priority' => 'high',
            'status' => 'active',
            'branch_type' => 'single',
            'address_line_1' => '101 Business Park',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'district' => 'Mumbai City',
            'pin_code' => '400001',
            'contact_no_1' => '9876543210',
            'email_1' => 'facility@acme.test',
            'country' => 'India',
            'contacts' => [['name' => 'Priya Shah', 'email' => 'priya@acme.test', 'mother_tongue' => 'Marathi', 'is_primary' => '1']],
            'credit' => ['credit_days' => 30, 'credit_limit' => 100000],
        ]);

        $response->assertRedirect(route('customers.index'));
        $customer = Customer::where('name', 'Acme Cooling Limited')->firstOrFail();
        $this->assertSame('CUST00001', $customer->code);
        $this->assertCount(1, $customer->contacts);
        $this->assertSame('Hindi', $customer->mother_tongue);
        $this->assertSame('Marathi', $customer->contacts->first()->mother_tongue);
        $this->assertSame(30, $customer->creditTerms->credit_days);
    }

    public function test_multi_branch_customer_requires_a_branch(): void
    {
        $user = User::factory()->create()->givePermissionTo('customers.create');

        $this->actingAs($user)->post('/customers', [
            'name' => 'Branchless Limited',
            'customer_group_id' => $this->group->id,
            'types' => ['corporate'],
            'priority' => 'normal',
            'status' => 'active',
            'branch_type' => 'multi',
            'address_line_1' => '12 Corporate Avenue',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'district' => 'Pune',
            'pin_code' => '411001',
            'country' => 'India',
            'contact_no_1' => '9876543210',
            'email_1' => 'office@branchless.test',
            'contacts' => [['name' => 'Contact']],
        ])->assertSessionHasErrors('branches');
    }

    public function test_multi_branch_customer_stores_branch_address_gst_and_contact_person(): void
    {
        $user = User::factory()->create()->givePermissionTo('customers.create');

        $response = $this->actingAs($user)->post('/customers', [
            'name' => 'Northstar Facilities',
            'customer_group_id' => $this->group->id,
            'types' => ['corporate'],
            'priority' => 'normal',
            'status' => 'active',
            'branch_type' => 'multi',
            'address_line_1' => '1 Head Office Road',
            'city' => 'Delhi',
            'state' => 'Delhi',
            'district' => 'New Delhi',
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

    public function test_authorized_user_can_edit_customer_account_and_credit_details(): void
    {
        $user = User::factory()->create()->givePermissionTo('customers.update');
        $customer = Customer::factory()->create([
            'customer_group_id' => $this->group->id,
            'name' => 'Old Customer Name',
        ]);

        $this->actingAs($user)->get(route('customers.edit', $customer))
            ->assertOk()
            ->assertSee('Edit customer');

        $this->actingAs($user)->put(route('customers.update', $customer), [
            'name' => 'Updated Customer Name',
            'customer_group_id' => $this->group->id,
            'types' => ['corporate'],
            'segments' => ['office'],
            'priority' => 'high',
            'status' => 'active',
            'address_line_1' => '88 Business Park',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'district' => 'Mumbai City',
            'pin_code' => '400001',
            'country' => 'India',
            'contact_no_1' => '9876543210',
            'email_1' => 'updated@example.test',
            'credit' => ['credit_limit' => 250000, 'credit_days' => 45, 'po_mandatory' => 1],
        ])->assertRedirect(route('customers.show', $customer));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Updated Customer Name',
            'email_1' => 'updated@example.test',
        ]);
        $this->assertDatabaseHas('customer_credit_terms', [
            'customer_id' => $customer->id,
            'credit_days' => 45,
            'po_mandatory' => 1,
        ]);
    }
}
