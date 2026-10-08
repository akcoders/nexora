<?php

namespace App\Models;

use Database\Factories\PayrollEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollEntry extends Model
{
    /** @use HasFactory<PayrollEntryFactory> */
    use HasFactory;

    protected $guarded = [];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'earnings' => 'array',
            'deductions' => 'array',
            'paid_at' => 'datetime',
            'working_days' => 'decimal:2',
            'present_days' => 'decimal:2',
            'half_days' => 'decimal:2',
            'paid_leave_days' => 'decimal:2',
            'unpaid_days' => 'decimal:2',
            'payable_days' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'deduction_amount' => 'decimal:2',
            'net_amount' => 'decimal:2',
        ];
    }
}
