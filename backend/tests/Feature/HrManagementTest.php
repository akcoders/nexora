<?php

namespace Tests\Feature;

use App\Models\Premises;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_wizard_creates_login_profile_role_and_premises_assignment(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $premises = Premises::firstOrFail();

        $this->actingAs($admin)->post(route('employees.store'), [
            'first_name' => 'Neha', 'last_name' => 'Patil', 'work_email' => 'neha.patil@classic.test', 'phone' => '9876500011',
            'department' => 'HR', 'designation' => 'HR Executive', 'role' => 'Manager', 'user_status' => 'active', 'premises' => [$premises->id],
            'salary_currency' => 'INR', 'pay_frequency' => 'monthly', 'basic_salary' => 30000, 'hra' => 12000,
        ])->assertRedirect();

        $employee = User::where('email', 'neha.patil@classic.test')->firstOrFail();
        $this->assertTrue($employee->hasRole('Manager'));
        $this->assertNotNull($employee->employeeProfile);
        $this->assertTrue($employee->premises->contains($premises));
        $this->assertGreaterThan(0, $employee->leaveBalances()->count());
    }

    public function test_super_admin_can_generate_monthly_payroll(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $month = now()->subMonth()->format('Y-m');

        $this->actingAs($admin)->post(route('payroll.generate'), ['month' => $month])->assertRedirect();

        $this->assertDatabaseHas('payroll_runs', ['payroll_month' => $month, 'status' => 'draft']);
        $this->assertDatabaseCount('payroll_entries', 12);
    }
}
