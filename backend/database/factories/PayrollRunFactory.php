<?php

namespace Database\Factories;

use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_month' => fake()->unique()->dateTimeBetween('-2 years', 'now')->format('Y-m'),
            'status' => 'draft',
            'generated_by' => User::factory(),
            'generated_at' => now(),
        ];
    }
}
