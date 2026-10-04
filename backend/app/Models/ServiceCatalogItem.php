<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceCatalogItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['standard_price' => 'decimal:2', 'tax_percent' => 'decimal:2', 'active' => 'boolean'];
    }
}
