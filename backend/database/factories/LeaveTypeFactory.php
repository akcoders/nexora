<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('LV##'),
            'name' => fake()->unique()->word().' Leave',
            'annual_quota' => 12,
            'color' => 'primary',
            'paid' => true,
            'requires_document' => false,
            'active' => true,
        ];
    }
}
