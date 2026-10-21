<?php

namespace Tests\Feature;

use App\Models\Premises;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeLanguageAndVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function employeePayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Neha', 'last_name' => 'Patil', 'work_email' => 'neha.patil@classic.test', 'phone' => '9876500011',
            'department' => 'HR', 'designation' => 'HR Executive', 'role' => 'Manager', 'user_status' => 'active',
            'premises' => [Premises::firstOrFail()->id], 'salary_currency' => 'INR', 'pay_frequency' => 'monthly',
        ], $overrides);
    }

    public function test_employee_wizard_stores_verification_statuses_and_language_preference(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();

        $this->actingAs($admin)->post(route('employees.store'), $this->employeePayload([
            'languages' => ['Hindi', 'Marathi'],
            'preferred_language' => 'Marathi',
            'verification' => [
                'police_verification' => 'verified',
                'police_verification_date' => now()->subDays(3)->toDateString(),
                'police_verification_reference' => 'PV/2026/1182',
                'background_check' => 'in_progress',
                'background_check_agency' => 'AuthBridge',
            ],
        ]))->assertRedirect();

        $employee = User::where('email', 'neha.patil@classic.test')->firstOrFail();
        $this->assertSame('Marathi', $employee->preferred_language);
        $this->assertSame(['Hindi', 'Marathi'], $employee->employeeProfile->languages);
        $this->assertSame('verified', $employee->employeeProfile->verification['police_verification']);
        $this->assertSame('in_progress', $employee->employeeProfile->verification['background_check']);
        $this->assertSame('AuthBridge', $employee->employeeProfile->verification['background_check_agency']);

        $this->actingAs($admin)->get(route('employees.show', $employee))
            ->assertOk()
            ->assertSee('Verified / Cleared')
            ->assertSee('In progress')
            ->assertSee('Marathi');

        $this->actingAs($admin)->get(route('employees.create'))->assertOk()->assertSee('Preferred app language');
        $this->actingAs($admin)->get(route('employees.edit', $employee))
            ->assertOk()
            ->assertSee('name="verification[police_verification]"', false)
            ->assertSee('name="verification[background_check]"', false)
            ->assertSee('PV/2026/1182');
    }

    public function test_preferred_language_defaults_to_first_known_language_and_is_always_known(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();

        $this->actingAs($admin)->post(route('employees.store'), $this->employeePayload([
            'languages' => ['Gujarati', 'English'],
        ]))->assertRedirect();
        $employee = User::where('email', 'neha.patil@classic.test')->firstOrFail();
        $this->assertSame('Gujarati', $employee->preferred_language);

        $this->actingAs($admin)->put(route('employees.update', $employee), $this->employeePayload([
            'employee_code' => $employee->employee_code,
            'languages' => ['Hindi'],
            'preferred_language' => 'Tamil',
        ]))->assertRedirect();
        $employee->refresh();
        $this->assertSame('Tamil', $employee->preferred_language);
        $this->assertSame(['Hindi', 'Tamil'], $employee->employeeProfile->languages);
    }

    public function test_verification_status_and_language_must_come_from_the_master_lists(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();

        $this->actingAs($admin)->from(route('employees.create'))->post(route('employees.store'), $this->employeePayload([
            'languages' => ['Klingon'],
            'preferred_language' => 'Elvish',
            'verification' => ['police_verification' => 'maybe', 'background_check_date' => now()->addDay()->toDateString()],
        ]))->assertRedirect(route('employees.create'))
            ->assertSessionHasErrors(['languages.0', 'preferred_language', 'verification.police_verification', 'verification.background_check_date']);
    }

    public function test_user_can_switch_interface_language_from_the_switcher_and_the_layout_exposes_known_languages(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $admin->employeeProfile->update(['languages' => ['Hindi', 'Kannada']]);

        $this->actingAs($admin)->patchJson(route('profile.language'), ['language' => 'Kannada'])
            ->assertOk()
            ->assertJson(['language' => 'Kannada', 'code' => 'kn']);
        $this->assertSame('Kannada', $admin->fresh()->preferred_language);

        $this->actingAs($admin)->patchJson(route('profile.language'), ['language' => 'French'])->assertUnprocessable();

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('"preferredCode":"kn"', false)
            ->assertSee('data-language-switcher', false)
            ->assertSee('"Hindi":"hi"', false)
            ->assertDontSee('"Tamil":"ta"', false);

        $this->actingAs($admin)->get(route('profile.edit'))->assertOk()->assertSee('Interface language');
    }

    public function test_api_login_returns_language_preferences_and_can_update_them(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@nexora.test')->firstOrFail();
        $admin->employeeProfile->update(['languages' => ['Hindi']]);
        $admin->forceFill(['preferred_language' => 'Hindi'])->save();

        $login = $this->postJson('/api/v1/auth/login', ['email' => 'admin@nexora.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.language.preferred', 'Hindi')
            ->assertJsonPath('user.language.preferred_code', 'hi')
            ->assertJsonPath('user.language.known.0.code', 'en')
            ->assertJsonPath('user.language.known.1.name', 'Hindi');

        $this->withToken($login->json('token'))->patchJson('/api/v1/auth/language', ['language' => 'English'])
            ->assertOk()
            ->assertJsonPath('user.language.preferred', 'English');
        $this->assertSame('English', $admin->fresh()->preferred_language);
    }

    public function test_reseeding_does_not_duplicate_national_holidays(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('holidays', 2);
    }
}
