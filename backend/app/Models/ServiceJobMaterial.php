<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceJobMaterial extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_inventory' => 'boolean', 'quantity' => 'decimal:2', 'unit_rate' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    public function serviceJob(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
