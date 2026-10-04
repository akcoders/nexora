<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerContact extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'anniversary' => 'date',
            'is_primary' => 'boolean',
            'is_service' => 'boolean',
            'is_billing' => 'boolean',
            'is_escalation' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(CustomerBranch::class, 'customer_branch_id');
    }
}
