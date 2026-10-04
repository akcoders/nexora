<?php

namespace Database\Factories;

use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceType>
 */
class ServiceTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'ST-'.fake()->unique()->numerify('####'),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'default_price' => fake()->randomFloat(2, 500, 3000),
            'tax_percent' => 18,
            'estimated_minutes' => fake()->numberBetween(30, 180),
            'active' => true,
        ];
    }
}
