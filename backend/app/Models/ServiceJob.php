<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceJob extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'preferred_visit_date' => 'date',
            'scheduled_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'journey_started_at' => 'datetime',
            'arrived_at' => 'datetime',
            'inspection_completed_at' => 'datetime',
            'service_started_at' => 'datetime',
            'service_completed_at' => 'datetime',
            'completed_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'final_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(CustomerBranch::class, 'customer_branch_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(CustomerEquipment::class, 'customer_equipment_id');
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ServiceJobStatusHistory::class)->orderByDesc('changed_at');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(ServiceVisit::class)->orderByDesc('occurred_at');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(ServiceInspection::class);
    }

    public function estimates(): HasMany
    {
        return $this->hasMany(ServiceEstimate::class)->orderByDesc('version');
    }

    public function currentEstimate(): HasOne
    {
        return $this->hasOne(ServiceEstimate::class)->latestOfMany('version');
    }

    public function performedServices(): HasMany
    {
        return $this->hasMany(ServiceJobService::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(ServiceJobMaterial::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ServiceJobPhoto::class)->latest();
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(ServiceSignature::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ServicePayment::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(ServiceFeedback::class);
    }
}
