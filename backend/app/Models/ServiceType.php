<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceType extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['default_price' => 'decimal:2', 'tax_percent' => 'decimal:2', 'active' => 'boolean'];
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(ServiceJob::class);
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(ServiceChecklist::class);
    }
}
