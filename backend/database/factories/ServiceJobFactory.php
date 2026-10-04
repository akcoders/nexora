<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\ServiceJob;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceJob>
 */
class ServiceJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_no' => 'SRV-'.fake()->unique()->numerify('######'),
            'customer_id' => Customer::factory(),
            'service_type_id' => ServiceType::factory(),
            'origin' => 'manual',
            'customer_phone' => fake()->numerify('9#########'),
            'service_address' => fake()->address(),
            'complaint' => 'AC is not cooling properly.',
            'priority' => 'normal',
            'scheduled_at' => now()->addDay(),
            'assigned_to' => User::factory(),
            'created_by' => User::factory(),
            'status' => 'assigned',
            'payment_status' => 'pending',
        ];
    }
}
