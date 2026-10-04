<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerBranch extends Model
{
    protected $guarded = [];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(CustomerEquipment::class);
    }

    public function floorPlans(): HasMany
    {
        return $this->hasMany(CustomerFloorPlan::class);
    }
}
