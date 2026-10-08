<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanySite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanySite>
 */
class CompanySiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'site_id' => fake()->unique()->bothify('SITE-####'),
            'code' => fake()->unique()->bothify('LOC-####'),
            'name' => fake()->company().' Site',
            'type' => 'office',
            'address' => ['city' => fake()->city(), 'country' => 'India'],
            'active' => true,
        ];
    }
}
