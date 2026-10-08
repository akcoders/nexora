<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'inside_premises' => 'boolean',
            'checkout_inside_premises' => 'boolean',
            'auto_checked_out' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function premises(): BelongsTo
    {
        return $this->belongsTo(Premises::class);
    }

    public function checkoutPremises(): BelongsTo
    {
        return $this->belongsTo(Premises::class, 'checkout_premises_id');
    }
}
