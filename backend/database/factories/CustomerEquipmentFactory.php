<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerEquipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerEquipment>
 */
class CustomerEquipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'equipment_type' => 'Split AC',
            'brand' => fake()->randomElement(['Daikin', 'Blue Star', 'Carrier', 'Voltas']),
            'model' => strtoupper(fake()->bothify('AC-##??')),
            'serial_no' => strtoupper(fake()->unique()->bothify('SN########')),
            'capacity' => fake()->randomElement(['1 Ton', '1.5 Ton', '2 Ton']),
            'location' => fake()->randomElement(['Reception', 'Office', 'Conference Room']),
            'active' => true,
        ];
    }
}
