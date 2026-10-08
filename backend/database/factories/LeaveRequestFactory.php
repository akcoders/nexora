<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
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
            'leave_type_id' => LeaveType::factory(),
            'from_date' => now()->addDays(7)->toDateString(),
            'to_date' => now()->addDays(8)->toDateString(),
            'total_days' => 2,
            'reason' => fake()->sentence(),
            'status' => 'pending',
        ];
    }
}
