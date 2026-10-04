<?php

namespace Database\Factories;

use App\Models\ServiceCatalogItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceCatalogItem>
 */
class ServiceCatalogItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'SVC-'.fake()->unique()->numerify('####'),
            'name' => fake()->unique()->words(3, true),
            'category' => 'HVAC Service',
            'equipment_type' => 'Split AC',
            'standard_price' => fake()->randomFloat(2, 300, 3000),
            'tax_percent' => 18,
            'estimated_minutes' => fake()->numberBetween(20, 180),
            'active' => true,
        ];
    }
}
