<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceJobPhoto extends Model
{
    protected $guarded = [];

    public function serviceJob(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class);
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(ServiceInspection::class, 'service_inspection_id');
    }

    public function inspectionItem(): BelongsTo
    {
        return $this->belongsTo(ServiceInspectionItem::class);
    }
}
