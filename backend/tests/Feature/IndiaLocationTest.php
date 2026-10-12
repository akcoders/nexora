<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\District;
use App\Models\State;
use App\Models\User;
use Database\Seeders\IndiaLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IndiaLocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_seeds_every_state_with_its_districts(): void
    {
        $this->assertSame(36, State::count());
        $this->assertGreaterThan(750, District::count());
        $this->assertTrue(State::where('name', 'Maharashtra')->firstOrFail()->districts()->where('name', 'Pune')->exists());
        $this->assertSame('27', State::where('name', 'Maharashtra')->value('gst_code'));

        $this->seed(IndiaLocationSeeder::class);

        $this->assertSame(36, State::count(), 'Re-seeding must not duplicate states.');
    }

    public function test_locations_endpoint_requires_login_and_lists_districts_by_state(): void
    {
        $this->getJson(route('locations.india'))->assertUnauthorized();

        $response = $this->actingAs(User::factory()->create())->getJson(route('locations.india'))->assertOk();

        $this->assertSame('India', $response->json('country'));
        $kerala = collect($response->json('states'))->firstWhere('name', 'Kerala');
        $this->assertCount(14, $kerala['districts']);
        $this->assertContains('Ernakulam', $kerala['districts']);
    }

    public function test_customer_district_must_belong_to_the_selected_state_and_country_is_always_india(): void
    {
        $user = User::factory()->create();
        $group = CustomerGroup::create(['code' => 'GRP-LOC', 'name' => 'Location Customers', 'status' => 'active']);
        $payload = [
            'name' => 'Lakeside Clinics',
            'customer_group_id' => $group->id,
            'types' => ['corporate'],
            'priority' => 'normal',
            'status' => 'active',
            'branch_type' => 'single',
            'address_line_1' => '5 Lake Road',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'district' => 'Lucknow',
            'pin_code' => '411001',
            'country' => 'Nepal',
            'contact_no_1' => '9876543210',
            'email_1' => 'desk@lakeside.test',
            'contacts' => [['name' => 'Front Desk', 'is_primary' => '1']],
        ];

        $this->actingAs($user)->post(route('customers.store'), $payload)->assertSessionHasErrors('district');
        $this->actingAs($user)->post(route('customers.store'), ['state' => 'Atlantis'] + $payload)->assertSessionHasErrors('state');

        $this->actingAs($user)->post(route('customers.store'), ['district' => 'Pune'] + $payload)
            ->assertRedirect(route('customers.index'));

        $customer = Customer::where('name', 'Lakeside Clinics')->firstOrFail();
        $this->assertSame('Pune', $customer->district);
        $this->assertSame('India', $customer->country);
    }

    public function test_maintenance_actions_need_a_logged_in_user_with_settings_permission(): void
    {
        $this->post(route('settings.maintenance', 'migrate'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->post(route('settings.maintenance', 'migrate'))->assertForbidden();
    }

    public function test_run_all_maintenance_runs_migrations_seeders_and_cache_rebuild_in_order(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();

        Artisan::shouldReceive('call')->once()->ordered()->with('migrate', ['--force' => true])->andReturn(0);
        Artisan::shouldReceive('call')->once()->ordered()->with('db:seed', ['--force' => true])->andReturn(0);
        Artisan::shouldReceive('call')->once()->ordered()->with('optimize:clear', [])->andReturn(0);
        Artisan::shouldReceive('call')->once()->ordered()->with('optimize', [])->andReturn(0);
        Artisan::shouldReceive('output')->times(4)->andReturn('done');

        $this->actingAs($admin)->post(route('settings.maintenance', 'all'))
            ->assertRedirect(route('settings.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'event' => 'system.maintenance.all']);
    }

    public function test_reseeding_keeps_existing_demo_user_passwords(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $admin->forceFill(['password' => Hash::make('changed-secret')])->save();

        $this->seed();

        $this->assertTrue(Hash::check('changed-secret', $admin->fresh()->password));
    }
}
