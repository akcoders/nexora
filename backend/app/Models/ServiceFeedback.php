<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceFeedback extends Model
{
    protected $table = 'service_feedback';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['issue_still_exists' => 'boolean', 'request_callback' => 'boolean', 'submitted_at' => 'datetime'];
    }

    public function serviceJob(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
