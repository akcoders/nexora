<?php

namespace Tests\Feature;

use App\Models\Premises;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeOnboardingValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@nexora.test')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'first_name' => 'Neha', 'last_name' => 'Patil', 'work_email' => 'neha.patil@classic.test', 'phone' => '9876500011',
            'department' => 'HR', 'designation' => 'HR Executive', 'role' => 'Manager', 'user_status' => 'active',
            'premises' => [Premises::firstOrFail()->id], 'salary_currency' => 'INR', 'pay_frequency' => 'monthly',
        ], $overrides);
    }

    public function test_formats_and_cross_field_rules_are_enforced_on_the_server(): void
    {
        $this->actingAs($this->admin)->from(route('employees.create'))->post(route('employees.store'), $this->payload([
            'phone' => '12345',
            'date_of_birth' => now()->subYears(16)->toDateString(),
            'department' => 'Space Program',
            'identity_documents' => ['aadhaar' => '234567890123', 'pan' => 'ABCDE1234F', 'voter_id' => 'AB12'],
            'current_address' => ['pin' => '012345'],
            'emergency_family' => ['emergency_name' => 'Ravi Patil', 'mobile' => '12345'],
            'bank_details' => ['account_number' => '123456789012'],
            'verification' => ['uan' => '1234', 'police_verification' => 'verified'],
            'basic_salary' => 30000,
            'gross_monthly_salary' => 20000,
        ]))->assertRedirect(route('employees.create'))->assertSessionHasErrors([
            'phone', 'date_of_birth', 'department',
            'identity_documents.aadhaar', 'identity_documents.pan', 'identity_documents.voter_id',
            'current_address.pin', 'emergency_family.relation', 'emergency_family.mobile',
            'bank_details.bank_name', 'bank_details.ifsc', 'verification.uan', 'verification.police_verification_date',
            'gross_monthly_salary',
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'neha.patil@classic.test']);
    }

    public function test_unknown_nested_keys_and_values_outside_the_dropdowns_are_rejected(): void
    {
        $this->actingAs($this->admin)->post(route('employees.store'), $this->payload([
            'bank_details' => ['bank_name' => 'HDFC', 'account_number' => '123456789012', 'ifsc' => 'HDFC0001234', 'swift' => 'X'],
            'safety' => ['medical_fitness' => 'Probably fine'],
            'employment_type' => 'freelance',
        ]))->assertSessionHasErrors(['bank_details', 'safety.medical_fitness', 'employment_type']);
    }

    public function test_separators_are_stripped_and_codes_upper_cased_before_saving(): void
    {
        $this->actingAs($this->admin)->post(route('employees.store'), $this->payload([
            'work_email' => ' Neha.Patil@Classic.test ',
            'phone' => '+91 98765-00011',
            'identity_documents' => ['aadhaar' => '2345 6789 0124', 'pan' => 'abcpd 1234 e'],
            'bank_details' => ['bank_name' => 'HDFC Bank', 'account_number' => '1234 5678 9012', 'ifsc' => 'hdfc0001234'],
            'current_address' => ['pin' => '400 001'],
            'marital_status' => 'unmarried',
            'anniversary_date' => '2020-01-01',
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $profile = User::where('email', 'neha.patil@classic.test')->firstOrFail()->employeeProfile;
        $this->assertSame('234567890124', $profile->identity_documents['aadhaar']);
        $this->assertSame('ABCPD1234E', $profile->identity_documents['pan']);
        $this->assertSame('HDFC0001234', $profile->bank_details['ifsc']);
        $this->assertSame('123456789012', $profile->bank_details['account_number']);
        $this->assertSame('400001', $profile->current_address['pin']);
        $this->assertNull($profile->anniversary_date);
    }

    public function test_saved_values_no_longer_in_a_dropdown_stay_valid_but_an_employee_cannot_manage_themselves(): void
    {
        $this->actingAs($this->admin)->post(route('employees.store'), $this->payload())->assertSessionHasNoErrors();
        $employee = User::where('email', 'neha.patil@classic.test')->firstOrFail();
        $employee->update(['department' => 'Engineering', 'designation' => 'Facility Manager']);

        $this->actingAs($this->admin)->put(route('employees.update', $employee), $this->payload([
            'department' => 'Engineering', 'designation' => 'Facility Manager', 'reporting_manager_id' => $employee->id,
        ]))->assertSessionHasErrors(['reporting_manager_id'])->assertSessionDoesntHaveErrors(['department', 'designation']);

        $this->actingAs($this->admin)->put(route('employees.update', $employee), $this->payload([
            'department' => 'Engineering', 'designation' => 'Facility Manager', 'reporting_manager_id' => $this->admin->id,
        ]))->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post(route('employees.store'), $this->payload([
            'work_email' => 'another@classic.test', 'department' => 'Engineering',
        ]))->assertSessionHasErrors(['department']);
    }

    public function test_onboarding_form_offers_dropdowns_for_master_fields(): void
    {
        $this->actingAs($this->admin)->get(route('employees.create'))
            ->assertOk()
            ->assertSee('name="department" id="employeeDepartment"', false)
            ->assertSee('name="functional_manager_id"', false)
            ->assertSee('name="hr_manager_id"', false)
            ->assertSee('<option value="Fit with restrictions">', false)
            ->assertSee('<option value="Indian" selected>', false);
    }
}
