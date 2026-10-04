<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['specifications' => 'array', 'active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(ServiceChecklist::class, 'service_checklist_id');
    }
}
