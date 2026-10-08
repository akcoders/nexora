<?php

namespace Database\Factories;

use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeProfile>
 */
class EmployeeProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_joining' => fake()->dateTimeBetween('-5 years', 'now'),
            'employment_type' => 'permanent',
            'salary_currency' => 'INR',
            'pay_frequency' => 'monthly',
            'basic_salary' => 25000,
            'gross_monthly_salary' => 35000,
        ];
    }
}
