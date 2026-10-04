<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DemoDataRecord;
use App\Models\ServiceJob;
use App\Models\Task;
use App\Models\User;
use App\Services\DemoDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_ten_linked_demo_scenarios(): void
    {
        $this->seed();

        $this->assertSame(10, DemoDataRecord::distinct('scenario_key')->count('scenario_key'));
        $this->assertSame(10, DemoDataRecord::where('model_type', Customer::class)->count());
        $this->assertSame(10, DemoDataRecord::where('model_type', CustomerGroup::class)->count());
        $this->assertSame(10, DemoDataRecord::where('model_type', ServiceJob::class)->count());
        $this->assertSame(10, DemoDataRecord::where('model_type', Task::class)->count());
        $this->assertSame(10, DemoDataRecord::where('model_type', Attendance::class)->count());
        $this->assertSame(10, DemoDataRecord::where('model_type', User::class)->count());

        $completed = ServiceJob::where('job_no', 'DEMO-SRV-010')->firstOrFail();
        $this->assertSame('completed', $completed->status);
        $this->assertTrue($completed->payments()->exists());
        $this->assertTrue($completed->feedback()->exists());
        $this->assertTrue($completed->customer->contacts()->whereNotNull('mother_tongue')->exists());
    }

    public function test_super_admin_can_clear_only_registered_demo_data(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $realCustomer = Customer::factory()->create(['code' => 'REAL-CUSTOMER-01']);

        $this->actingAs($admin)->delete(route('settings.demo-data.destroy'))->assertRedirect();

        $this->assertDatabaseHas('customers', ['id' => $realCustomer->id]);
        $this->assertDatabaseMissing('service_jobs', ['job_no' => 'DEMO-SRV-010']);
        $this->assertSame(0, DemoDataRecord::count());
        $this->assertSame(0, app(DemoDataService::class)->stats()['scenarios']);
    }

    public function test_super_admin_can_reset_all_ten_demo_scenarios(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        app(DemoDataService::class)->clear();

        $this->actingAs($admin)->post(route('settings.demo-data.store'))->assertRedirect();

        $this->assertSame(10, DemoDataRecord::distinct('scenario_key')->count('scenario_key'));
        $this->assertDatabaseHas('service_jobs', ['job_no' => 'DEMO-SRV-010', 'status' => 'completed']);
    }
}
