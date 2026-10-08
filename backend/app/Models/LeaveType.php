<?php

namespace App\Models;

use Database\Factories\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    /** @use HasFactory<LeaveTypeFactory> */
    use HasFactory;

    protected $guarded = [];

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    protected function casts(): array
    {
        return ['annual_quota' => 'decimal:2', 'paid' => 'boolean', 'requires_document' => 'boolean', 'active' => 'boolean'];
    }
}
