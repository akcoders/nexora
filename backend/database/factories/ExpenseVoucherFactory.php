<?php

namespace Database\Factories;

use App\Models\ExpenseVoucher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseVoucher>
 */
class ExpenseVoucherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'voucher_no' => fake()->unique()->bothify('EXP########'),
            'user_id' => User::factory(),
            'expense_date' => now()->toDateString(),
            'category' => 'Travel',
            'amount' => fake()->randomFloat(2, 100, 5000),
            'description' => fake()->sentence(),
            'status' => 'pending',
        ];
    }
}
