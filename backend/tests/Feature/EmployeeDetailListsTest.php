<?php

namespace Tests\Feature;

use App\Models\EmployeeProfile;
use App\Models\Premises;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeDetailListsTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $this->seed();

        return User::where('email', 'admin@nexora.test')->sole();
    }

    private function payload(): array
    {
        return [
            'first_name' => 'Neha', 'last_name' => 'Patil', 'personal_email' => 'neha.details@example.test', 'phone' => '9876500011',
            'department' => 'HR', 'designation' => 'HR Executive', 'role' => 'Manager', 'user_status' => 'active',
            'premises' => [Premises::firstOrFail()->id], 'salary_currency' => 'INR', 'pay_frequency' => 'monthly',
            'emergency_family' => ['dependents_count' => 0, 'contacts' => [
                ['name' => 'Ravi Patil', 'relation' => 'Father', 'mobile' => '9876500022'],
                ['name' => 'Mira Patil', 'relation' => 'Mother', 'mobile' => '9876500033'],
                ['name' => 'Anil Patil', 'relation' => 'Brother', 'mobile' => '+91 98765 00044'],
            ]],
            'education_skills' => ['has_education' => 1, 'education_records' => [
                ['name' => 'ITI Electrician', 'institution' => 'Technical Institute'], ['name' => 'Diploma in HVAC'],
            ], 'has_certifications' => 1, 'certification_records' => [
                ['name' => 'HVAC Safety', 'issuer' => 'Safety Board'], ['name' => 'First Aid'],
            ], 'technical_skills' => 'Electrical maintenance'],
            'benefits_allowances' => ['has_policies' => 1, 'insurance_policies' => [
                ['name' => 'Health insurance', 'number' => 'POL001'], ['name' => 'Accident cover'],
            ]],
            'assets_access' => ['assets_assigned' => 1, 'assigned_assets' => [
                ['name' => 'Toolbox', 'items' => [['name' => 'Spanner'], ['name' => 'Tester']]],
                ['name' => 'Laptop', 'identifier' => 'LAP001'],
            ], 'erp_access' => 'Granted'],
        ];
    }

    public function test_multiple_contacts_qualifications_policies_and_nested_tools_are_saved_and_can_be_reviewed(): void
    {
        $admin = $this->administrator();
        $this->actingAs($admin)->post(route('employees.store'), $this->payload())->assertSessionHasNoErrors()->assertRedirect();
        $employee = User::where('email', 'neha.details@example.test')->sole();
        $profile = $employee->employeeProfile;
        $this->assertCount(3, $profile->emergencyContacts());
        $this->assertSame('9876500044', $profile->emergencyContacts()[2]['mobile']);
        $this->assertSame('Ravi Patil', $profile->emergency_family['emergency_name']);
        $this->assertSame('Mira Patil', $profile->emergency_family['secondary_name']);
        $this->assertCount(2, $profile->educationRecords());
        $this->assertCount(2, $profile->certificationRecords());
        $this->assertCount(2, $profile->insurancePolicies());
        $this->assertCount(2, $profile->assignedAssets());
        $this->assertSame(['Spanner', 'Tester'], array_column($profile->assignedAssets()[0]['items'], 'name'));
        $this->assertSame('LAP001', $profile->assignedAssets()[1]['identifier']);
        $this->get(route('employees.edit', $employee))->assertOk()->assertSee('Add more contacts')->assertSee('Add more education')->assertSee('Add more certifications')->assertSee('Add more policies')->assertSee('Add more assets')->assertSee('Add item name')->assertSee('Spanner');
        $this->get(route('employees.show', $employee))->assertOk()->assertSee('Anil Patil')->assertSee('Diploma in HVAC')->assertSee('First Aid')->assertSee('Accident cover')->assertSee('Spanner')->assertSee('Tester');
    }

    public function test_selecting_no_clears_previous_lists_and_ignores_disabled_row_payloads(): void
    {
        $admin = $this->administrator();
        $data = $this->payload();
        $this->actingAs($admin)->post(route('employees.store'), $data)->assertSessionHasNoErrors();
        $employee = User::where('email', 'neha.details@example.test')->sole();
        $data['education_skills']['has_education'] = 0;
        $data['education_skills']['has_certifications'] = 0;
        $data['benefits_allowances']['has_policies'] = 0;
        $data['assets_access']['assets_assigned'] = 0;
        $data['emergency_family']['contacts'] = [$data['emergency_family']['contacts'][2]];
        $this->put(route('employees.update', $employee), $data)->assertSessionHasNoErrors();
        $profile = $employee->fresh()->employeeProfile;
        $this->assertSame([], $profile->educationRecords());
        $this->assertSame([], $profile->certificationRecords());
        $this->assertSame([], $profile->insurancePolicies());
        $this->assertSame([], $profile->assignedAssets());
        $this->assertSame('Electrical maintenance', $profile->education_skills['technical_skills']);
        $this->assertSame('Granted', $profile->assets_access['erp_access']);
        $this->assertCount(1, $profile->emergencyContacts());
        $this->assertSame('Anil Patil', $profile->emergency_family['emergency_name']);
        $this->assertNull($profile->emergency_family['secondary_name']);
    }

    public function test_enabled_sections_require_at_least_one_record_and_record_names(): void
    {
        $admin = $this->administrator();
        $data = $this->payload();
        foreach (['emergency_family.contacts', 'education_skills.education_records', 'education_skills.certification_records', 'benefits_allowances.insurance_policies', 'assets_access.assigned_assets'] as $path) {
            data_set($data, $path, []);
        }
        $this->actingAs($admin)->postJson(route('employees.store'), $data)->assertUnprocessable()->assertJsonValidationErrors([
            'emergency_family.contacts', 'education_skills.education_records', 'education_skills.certification_records', 'benefits_allowances.insurance_policies', 'assets_access.assigned_assets',
        ]);
        $data = $this->payload();
        $data['assets_access']['assigned_assets'][0]['name'] = '';
        $data['assets_access']['assigned_assets'][0]['items'][0]['name'] = '';
        $data['education_skills']['education_records'][0]['name'] = '';
        $this->postJson(route('employees.store'), $data)->assertUnprocessable()->assertJsonValidationErrors([
            'assets_access.assigned_assets.0.name', 'assets_access.assigned_assets.0.items.0.name', 'education_skills.education_records.0.name',
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'neha.details@example.test']);
    }

    public function test_removing_rows_and_adding_new_assets_keeps_the_remaining_nested_names_on_edit(): void
    {
        $admin = $this->administrator();
        $data = $this->payload();
        $this->actingAs($admin)->post(route('employees.store'), $data)->assertSessionHasNoErrors();
        $employee = User::where('email', 'neha.details@example.test')->sole();
        $data['emergency_family']['contacts'] = [2 => $data['emergency_family']['contacts'][2]];
        $data['assets_access']['assigned_assets'] = [
            3 => ['name' => 'Toolbox', 'items' => [4 => ['name' => 'Pliers'], 8 => ['name' => 'Tester']]],
            7 => ['name' => 'Helmet'],
        ];
        $data['education_skills']['education_records'] = [5 => ['name' => 'Diploma']];
        $this->put(route('employees.update', $employee), $data)->assertSessionHasNoErrors();
        $profile = $employee->fresh()->employeeProfile;
        $this->assertSame([0, 1], array_keys($profile->assignedAssets()));
        $this->assertSame([['name' => 'Pliers'], ['name' => 'Tester']], $profile->assignedAssets()[0]['items']);
        $this->assertSame(['Toolbox', 'Helmet'], array_column($profile->assignedAssets(), 'name'));
        $this->assertSame([['name' => 'Diploma']], $profile->educationRecords());
        $this->get(route('employees.edit', $employee))->assertOk()->assertSee('Pliers')->assertSee('Helmet')->assertDontSee('value="LAP001"', false)->assertDontSee('value="Spanner"', false);
    }

    public function test_emergency_numbers_are_valid_distinct_and_different_from_the_employee_after_normalization(): void
    {
        $admin = $this->administrator();
        $data = $this->payload();
        $data['emergency_family']['contacts'][0]['mobile'] = '+91 98765 00011';
        $data['emergency_family']['contacts'][1]['mobile'] = '+91 98765 00044';
        $data['emergency_family']['contacts'][2]['mobile'] = '09876500044';
        $data['emergency_family']['contacts'][1]['relation'] = 'Unknown';
        $this->actingAs($admin)->postJson(route('employees.store'), $data)->assertUnprocessable()->assertJsonValidationErrors([
            'emergency_family.contacts.0.mobile', 'emergency_family.contacts.1.mobile', 'emergency_family.contacts.2.mobile', 'emergency_family.contacts.1.relation',
        ]);
        $data = $this->payload();
        $data['emergency_family']['contacts'][0]['mobile'] = '12345';
        $this->postJson(route('employees.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('emergency_family.contacts.0.mobile');
    }

    public function test_unknown_nested_keys_and_excessive_rows_are_rejected(): void
    {
        $admin = $this->administrator();
        $data = $this->payload();
        $data['assets_access']['assigned_assets'][0]['items'][0]['admin'] = true;
        $data['education_skills']['education_records'][0]['admin'] = true;
        $data['benefits_allowances']['insurance_policies'] = array_fill(0, 21, ['name' => 'Cover']);
        $this->actingAs($admin)->postJson(route('employees.store'), $data)->assertUnprocessable()->assertJsonValidationErrors([
            'assets_access.assigned_assets.0.items.0', 'education_skills.education_records.0', 'benefits_allowances.insurance_policies',
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'neha.details@example.test']);
    }

    public function test_legacy_details_remain_visible_in_the_new_form_and_profile(): void
    {
        $admin = $this->administrator();
        $employee = User::factory()->create(['status' => 'active']);
        EmployeeProfile::factory()->for($employee)->create([
            'emergency_family' => ['emergency_name' => 'Ravi Patil', 'relation' => 'Father', 'mobile' => '9876500022', 'secondary_name' => 'Mira Patil', 'secondary_relation' => 'Mother', 'secondary_mobile' => '9876500033', 'dependents_count' => 0],
            'education_skills' => ['highest_qualification' => 'Graduate', 'certifications' => 'Legacy HVAC certificate'],
            'benefits_allowances' => ['insurance_policy' => 'Legacy health cover'],
            'assets_access' => ['laptop' => 'OLD-LAP001', 'toolkit' => 'Issued', 'uniform' => 'Not issued'],
        ]);
        $this->actingAs($admin)->get(route('employees.edit', $employee))->assertOk()->assertSee('Graduate')->assertSee('Legacy HVAC certificate')->assertSee('Legacy health cover')->assertSee('OLD-LAP001')->assertSee('Mira Patil');
        $this->assertCount(2, $employee->employeeProfile->assignedAssets());
        $this->get(route('employees.show', $employee))->assertOk()->assertSee('Legacy health cover')->assertSee('OLD-LAP001');
    }

    public function test_employee_details_are_escaped_and_unauthorized_updates_are_refused(): void
    {
        $admin = $this->administrator();
        $data = $this->payload();
        $data['assets_access']['assigned_assets'][0]['items'][0]['name'] = '<script>alert(1)</script>';
        $this->actingAs($admin)->post(route('employees.store'), $data)->assertSessionHasNoErrors();
        $employee = User::where('email', 'neha.details@example.test')->sole();
        $this->get(route('employees.show', $employee))->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $outsider = User::factory()->create(['status' => 'active']);
        $outsider->assignRole('Technician');
        $this->actingAs($outsider)->putJson(route('employees.update', $employee), $data)->assertForbidden();
    }
}
