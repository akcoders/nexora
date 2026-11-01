<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerComplaintQrTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_workspace_offers_a_branded_print_card_and_complaint_qr_preview(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->sole();
        $customer = Customer::factory()->create(['created_by' => $admin->id]);
        $url = route('customers.complaint-qr', $customer);
        $this->actingAs($admin)->get(route('customers.show', $customer))->assertOk()->assertSee('Complaint QR')->assertSee($url, false)->assertSee('nexora-complaint-qr.png');
        $this->get($url)->assertOk()->assertSee('NEXORA')->assertSee('Print QR card')->assertSee('window.print()', false)->assertSee($customer->name)->assertSee($customer->code)->assertSee(config('nexora.customer_complaint_url'));
        $image = getimagesize(public_path('images/brand/nexora-complaint-qr.png'));
        $this->assertSame(IMAGETYPE_PNG, $image[2]);
        $this->assertGreaterThanOrEqual(1000, $image[0]);
        $this->assertSame($image[0], $image[1]);
    }

    public function test_print_cards_follow_customer_visibility_and_require_authentication(): void
    {
        $this->seed();
        $viewer = User::factory()->create(['status' => 'active']);
        $viewer->givePermissionTo('customers.view_own');
        $other = User::factory()->create();
        $own = Customer::factory()->create(['created_by' => $viewer->id]);
        $hidden = Customer::factory()->create(['created_by' => $other->id]);
        $this->get(route('customers.complaint-qr', $own))->assertRedirect(route('login'));
        $this->actingAs($viewer)->get(route('customers.complaint-qr', $own))->assertOk();
        $this->get(route('customers.complaint-qr', $hidden))->assertForbidden();
    }

    public function test_the_qr_destination_is_public_and_reserves_the_form_without_exposing_customer_data(): void
    {
        $customer = Customer::factory()->create(['name' => 'Private customer name', 'email_1' => 'private@example.test']);
        $path = parse_url(config('nexora.customer_complaint_url'), PHP_URL_PATH);
        $this->assertSame(route('public-complaints.create', [], false), $path);
        $this->get($path)->assertOk()->assertSee('NEXORA')->assertSee('Form coming soon')->assertDontSee('<form', false)->assertDontSee($customer->name)->assertDontSee($customer->email_1);
    }

    public function test_customer_names_are_escaped_on_the_print_card(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->sole();
        $customer = Customer::factory()->create(['name' => '<script>alert(1)</script>', 'created_by' => $admin->id]);
        $this->actingAs($admin)->get(route('customers.complaint-qr', $customer))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
