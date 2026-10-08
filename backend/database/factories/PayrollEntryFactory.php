<?php

namespace Database\Factories;

use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollEntry>
 */
class PayrollEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_run_id' => PayrollRun::factory(),
            'user_id' => User::factory(),
            'working_days' => 26,
            'present_days' => 26,
            'payable_days' => 26,
            'earnings' => ['basic' => 25000, 'hra' => 10000],
            'deductions' => ['professional_tax' => 200],
            'gross_amount' => 35000,
            'deduction_amount' => 200,
            'net_amount' => 34800,
            'status' => 'draft',
        ];
    }
}
