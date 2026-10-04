<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceInspectionItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(ServiceInspection::class, 'service_inspection_id');
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class);
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(InspectionCondition::class, 'inspection_condition_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ServiceJobPhoto::class);
    }
}
