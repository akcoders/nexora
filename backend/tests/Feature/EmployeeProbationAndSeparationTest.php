<?php

namespace Tests\Feature;

use App\Models\Premises;
use App\Models\User;
use App\Notifications\ProbationEndedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmployeeProbationAndSeparationTest extends TestCase
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
            'first_name' => 'Neha', 'last_name' => 'Patil', 'personal_email' => 'neha.home@example.test', 'phone' => '9876500011',
            'emergency_family' => ['emergency_name' => 'Ravi Patil', 'relation' => 'Father', 'mobile' => '9876500022', 'dependents_count' => 0],
            'department' => 'HR', 'designation' => 'HR Executive', 'role' => 'Manager', 'user_status' => 'active',
            'premises' => [Premises::firstOrFail()->id], 'salary_currency' => 'INR', 'pay_frequency' => 'monthly',
        ], $overrides);
    }

    private function createEmployee(array $overrides = []): User
    {
        $this->actingAs($this->admin)->post(route('employees.store'), $this->payload($overrides))->assertSessionHasNoErrors()->assertRedirect();

        return User::where('email', $overrides['work_email'] ?? 'neha.home@example.test')->firstOrFail();
    }

    public function test_permanent_email_is_mandatory_and_becomes_the_login_when_no_work_email_is_given(): void
    {
        $this->actingAs($this->admin)->post(route('employees.store'), $this->payload(['personal_email' => '']))
            ->assertSessionHasErrors(['personal_email'])->assertSessionDoesntHaveErrors(['work_email']);

        $employee = $this->createEmployee();
        $this->assertSame('neha.home@example.test', $employee->email);
        $this->assertSame('neha.home@example.test', $employee->employeeProfile->personal_email);

        $this->actingAs($this->admin)->post(route('employees.store'), $this->payload(['personal_email' => 'neha.home@example.test', 'phone' => '9876500033']))
            ->assertSessionHasErrors(['personal_email']);

        $this->actingAs($this->admin)->put(route('employees.update', $employee), $this->payload(['work_email' => 'neha.patil@classic.test']))->assertSessionHasNoErrors();
        $this->assertSame('neha.patil@classic.test', $employee->fresh()->email);

        $this->actingAs($this->admin)->get(route('employees.edit', $employee))->assertOk()
            ->assertSee('name="work_email" value="neha.patil@classic.test"', false)
            ->assertSee('Permanent email *', false);
    }

    public function test_second_emergency_contact_and_dependents_are_validated_and_stored(): void
    {
        $this->actingAs($this->admin)->post(route('employees.store'), $this->payload([
            'emergency_family' => ['emergency_name' => '', 'dependents_count' => '', 'secondary_mobile' => '9876500022', 'dependents' => [['gender' => 'male', 'age' => 300]]],
        ]))->assertSessionHasErrors([
            'emergency_family.emergency_name', 'emergency_family.dependents_count', 'emergency_family.secondary_name',
            'emergency_family.secondary_mobile', 'emergency_family.dependents.0.name', 'emergency_family.dependents.0.age',
        ]);

        $employee = $this->createEmployee(['emergency_family' => [
            'secondary_name' => 'Sunita Patil', 'secondary_relation' => 'Mother', 'secondary_mobile' => '9876500044', 'dependents_count' => 2,
            'dependents' => [['name' => 'Aarav Patil', 'gender' => 'male', 'age' => '4'], ['name' => '', 'gender' => '', 'age' => '']],
        ]]);

        $family = $employee->employeeProfile->emergency_family;
        $this->assertSame('Sunita Patil', $family['secondary_name']);
        $this->assertSame(2, $family['dependents_count']);
        $this->assertSame([['age' => 4, 'name' => 'Aarav Patil', 'gender' => 'male']], $family['dependents']);
    }

    public function test_probation_end_date_is_calculated_and_hr_confirms_the_employee_as_permanent(): void
    {
        $this->actingAs($this->admin)->post(route('employees.store'), $this->payload(['employment_type' => 'probation']))
            ->assertSessionHasErrors(['probation_months', 'date_of_joining']);

        $employee = $this->createEmployee(['employment_type' => 'probation', 'date_of_joining' => '2026-01-31', 'probation_months' => 1]);
        $profile = $employee->employeeProfile;
        $this->assertSame('2026-02-28', $profile->probation_end_date->toDateString());
        $this->assertSame('Probation review due', $profile->employmentStatus()['label']);

        $this->actingAs($this->admin)->get(route('employees.show', $employee))->assertOk()
            ->assertSee('Confirm as permanent')->assertSee('28 Feb 2026');

        $this->actingAs($this->admin)->post(route('employees.confirm-probation', $employee), ['remarks' => 'Good performance'])
            ->assertRedirect(route('employees.show', $employee));

        $profile->refresh();
        $this->assertSame('permanent', $profile->employment_type);
        $this->assertNotNull($profile->probation_confirmed_at);
        $this->assertSame($this->admin->id, $profile->probation_confirmed_by);
        $this->assertSame('Good performance', $profile->performance_career['probation_review']);
        $this->assertSame('Permanent', $profile->employmentStatus()['label']);

        $this->actingAs($this->admin)->post(route('employees.confirm-probation', $employee))->assertSessionHas('error');
    }

    public function test_hr_is_emailed_and_notified_once_when_a_probation_period_ends(): void
    {
        Notification::fake();
        $employee = $this->createEmployee(['employment_type' => 'probation', 'date_of_joining' => now()->subMonths(3)->toDateString(), 'probation_months' => 3]);
        $this->createEmployee(['employment_type' => 'probation', 'date_of_joining' => now()->toDateString(), 'probation_months' => 6, 'personal_email' => 'raj@example.test', 'phone' => '9876500055', 'first_name' => 'Raj']);

        $this->artisan('employees:notify-probation-end')->assertSuccessful();

        Notification::assertSentTo($this->admin, ProbationEndedNotification::class, function (ProbationEndedNotification $notification, array $channels) use ($employee): bool {
            return $notification->profile->user_id === $employee->id
                && $channels === ['mail', 'database']
                && str_contains($notification->toMail($this->admin)->subject, $employee->name);
        });
        Notification::assertCount(1);
        $this->assertNotNull($employee->employeeProfile->fresh()->probation_notified_at);

        $this->artisan('employees:notify-probation-end')->assertSuccessful();
        Notification::assertCount(1);
    }

    public function test_internal_staff_hiring_source_requires_the_referring_staff_member(): void
    {
        $this->actingAs($this->admin)->post(route('employees.store'), $this->payload(['verification' => ['source_of_hiring' => 'internal_staff']]))
            ->assertSessionHasErrors(['verification.referred_by_id']);

        $this->actingAs($this->admin)->post(route('employees.store'), $this->payload(['verification' => ['source_of_hiring' => 'newspaper']]))
            ->assertSessionHasErrors(['verification.source_of_hiring']);

        $employee = $this->createEmployee(['verification' => ['source_of_hiring' => 'job_portal', 'referred_by_id' => $this->admin->id]]);
        $this->assertArrayNotHasKey('referred_by_id', $employee->employeeProfile->verification);

        $this->actingAs($this->admin)->put(route('employees.update', $employee), $this->payload(['verification' => ['source_of_hiring' => 'internal_staff', 'referred_by_id' => $this->admin->id]]))->assertSessionHasNoErrors();
        $this->assertSame($this->admin->id, (int) $employee->employeeProfile->fresh()->verification['referred_by_id']);
        $this->actingAs($this->admin)->get(route('employees.show', $employee))->assertSee('referred by '.$this->admin->name);
    }

    public function test_medical_fitness_dates_are_validated(): void
    {
        $this->actingAs($this->admin)->post(route('employees.store'), $this->payload([
            'safety' => ['medical_fitness' => 'Fit', 'medical_fitness_date' => now()->addDay()->toDateString(), 'medical_fitness_valid_till' => now()->subYear()->toDateString()],
        ]))->assertSessionHasErrors(['safety.medical_fitness_date', 'safety.medical_fitness_valid_till']);

        $employee = $this->createEmployee(['safety' => ['medical_fitness' => 'Fit', 'medical_fitness_date' => '2026-01-10', 'medical_fitness_valid_till' => '2027-01-09']]);
        $this->assertSame('2027-01-09', $employee->employeeProfile->safety['medical_fitness_valid_till']);
    }

    public function test_last_working_day_is_derived_from_the_notice_period_and_the_status_reflects_separation(): void
    {
        $employee = $this->createEmployee([
            'employment_details' => ['notice_period_days' => 30],
            'date_of_joining' => '2025-01-01',
            'exit_separation' => ['resignation_date' => now()->subDays(5)->toDateString()],
        ]);
        $profile = $employee->employeeProfile;
        $this->assertSame(now()->addDays(25)->toDateString(), $profile->exit_separation['last_working_date']);
        $this->assertSame('Serving notice', $profile->employmentStatus()['label']);
        $this->assertStringContainsString('25 days left', $profile->employmentStatus()['detail']);

        $this->actingAs($this->admin)->put(route('employees.update', $employee), $this->payload([
            'employment_details' => ['notice_period_days' => 30],
            'date_of_joining' => '2025-01-01',
            'exit_separation' => ['resignation_date' => '2026-01-01', 'last_working_date' => '2026-01-20'],
        ]))->assertSessionHasNoErrors();
        $profile->refresh();
        $this->assertSame('2026-01-20', $profile->exit_separation['last_working_date']);
        $this->assertSame('Exited', $profile->employmentStatus()['label']);
        $this->actingAs($this->admin)->get(route('employees.index'))->assertOk()->assertSee('Exited');
    }
}
