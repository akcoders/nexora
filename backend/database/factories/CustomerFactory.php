<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'CUST'.fake()->unique()->numerify('#####'),
            'name' => fake()->company(),
            'types' => ['corporate'],
            'segments' => ['office'],
            'priority' => 'normal',
            'status' => 'active',
            'branch_type' => 'single',
            'address_line_1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'pin_code' => fake()->numerify('######'),
            'country' => 'India',
            'contact_no_1' => fake()->numerify('9#########'),
            'email_1' => fake()->unique()->companyEmail(),
        ];
    }
}
