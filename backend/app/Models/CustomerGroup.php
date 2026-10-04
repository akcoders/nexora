<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerGroup extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
