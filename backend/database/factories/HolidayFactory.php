<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word().' Holiday',
            'holiday_date' => fake()->unique()->dateTimeBetween('+1 day', '+1 year'),
            'type' => 'company',
            'optional' => false,
        ];
    }
}
