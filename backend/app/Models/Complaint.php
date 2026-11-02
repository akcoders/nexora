<?php

namespace App\Models;

use App\Services\ComplaintOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Complaint extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'response_due_at' => 'datetime',
            'resolution_due_at' => 'datetime',
            'first_response_at' => 'datetime',
            'installation_date' => 'date',
            'last_service_date' => 'date',
            'last_pm_date' => 'date',
            'contract_expiry' => 'date',
            'follow_up_date' => 'date',
            'expected_material_date' => 'date',
            'target_date' => 'date',
            'pending_since' => 'datetime',
            'happy_call_at' => 'datetime',
            'callback_date' => 'date',
            'closed_at' => 'datetime',
            'fsr_checked' => 'boolean',
            'happy_call_required' => 'boolean',
            'callback_required' => 'boolean',
            'visit_charges' => 'decimal:2',
            'labour_charges' => 'decimal:2',
            'parts_charges' => 'decimal:2',
            'travel_charges' => 'decimal:2',
            'other_charges' => 'decimal:2',
            'discount' => 'decimal:2',
            'gst_percent' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(CustomerBranch::class, 'customer_branch_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(CustomerEquipment::class, 'customer_equipment_id');
    }

    public function callTaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'call_taken_by');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function closureApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closure_approved_by');
    }

    public function fsrCheckedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fsr_checked_by');
    }

    public function happyCallBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'happy_call_by');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(ComplaintVisit::class)->orderBy('visit_no');
    }

    public function latestVisit(): HasOne
    {
        return $this->hasOne(ComplaintVisit::class)->latestOfMany('visit_no');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(ComplaintPart::class);
    }

    public function usedParts(): HasMany
    {
        return $this->parts()->where('kind', ComplaintPart::KIND_USED);
    }

    public function recommendedParts(): HasMany
    {
        return $this->parts()->where('kind', ComplaintPart::KIND_RECOMMENDED);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ComplaintActivity::class)->latest()->orderByDesc('id');
    }

    /**
     * Complaints the user may see: everything with complaints.read, otherwise the ones they
     * registered, currently own, or worked on as technician / assistant.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('complaints.read')) {
            return;
        }
        if (! $user->can('complaints.view_own')) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->where(fn (Builder $query) => $query->where($this->qualifyColumn('created_by'), $user->id)
            ->orWhere($this->qualifyColumn('assigned_to'), $user->id)
            ->orWhereHas('visits', fn (Builder $visits) => $visits->where('technician_id', $user->id)
                ->orWhere('assistant_one_id', $user->id)->orWhere('assistant_two_id', $user->id)));
    }

    /** Complaints the service team still has to act on. */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn($this->qualifyColumn('status'), ComplaintOptions::ACTIVE_STATUSES);
    }

    /** Pending or under-observation complaints whose follow-up falls on or before today. */
    public function scopeFollowUpDue(Builder $query): void
    {
        $query->whereIn($this->qualifyColumn('status'), ['pending', 'under_observation'])
            ->whereNotNull($this->qualifyColumn('follow_up_date'))
            ->whereDate($this->qualifyColumn('follow_up_date'), '<=', now()->toDateString());
    }

    /** Active complaints past their resolution deadline. */
    public function scopeSlaBreached(Builder $query): void
    {
        $query->active()->whereNotNull($this->qualifyColumn('resolution_due_at'))->where($this->qualifyColumn('resolution_due_at'), '<', now());
    }

    public function isActive(): bool
    {
        return in_array($this->status, ComplaintOptions::ACTIVE_STATUSES, true);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['closed', 'cancelled'], true);
    }

    /** @return array{label: string, tone: string, icon: string} */
    public function statusMeta(): array
    {
        return ComplaintOptions::status($this->status);
    }

    public function priorityLabel(): string
    {
        return ComplaintOptions::PRIORITIES[$this->priority]['label'] ?? str((string) $this->priority)->headline()->toString();
    }

    public function priorityTone(): string
    {
        return ComplaintOptions::PRIORITIES[$this->priority]['tone'] ?? 'secondary';
    }

    public function callTypeLabel(): string
    {
        return ComplaintOptions::CALL_TYPES[$this->call_type]['label'] ?? str((string) $this->call_type)->headline()->toString();
    }

    /** Days the complaint has been open (or was open, until it was closed). */
    public function ageInDays(): int
    {
        $end = $this->closed_at ?? now();

        return (int) $this->registered_at->copy()->startOfDay()->diffInDays($end->copy()->startOfDay());
    }

    /** Days since the complaint was last put on pending. */
    public function pendingAgeInDays(): int
    {
        return ComplaintOptions::ageInDays($this->pending_since ?? $this->registered_at);
    }

    /** @return array{key: string, label: string, min: int, max: ?int, tone: string} */
    public function pendingBucket(): array
    {
        return ComplaintOptions::ageBucket($this->pendingAgeInDays());
    }

    /**
     * Resolution SLA state: met, breached, at risk (under 25% of the window left) or on track.
     *
     * @return array{key: string, label: string, tone: string}
     */
    public function slaState(): array
    {
        if (! $this->resolution_due_at) {
            return ['key' => 'none', 'label' => 'No SLA', 'tone' => 'secondary'];
        }
        $finishedAt = $this->isActive() ? null : ($this->closed_at ?? $this->updated_at);
        if ($finishedAt) {
            return $finishedAt->lte($this->resolution_due_at)
                ? ['key' => 'met', 'label' => 'SLA met', 'tone' => 'success']
                : ['key' => 'missed', 'label' => 'SLA missed', 'tone' => 'danger'];
        }
        if ($this->resolution_due_at->isPast()) {
            return ['key' => 'breached', 'label' => 'SLA breached', 'tone' => 'danger'];
        }
        $window = max(1, $this->registered_at->diffInMinutes($this->resolution_due_at));
        $left = now()->diffInMinutes($this->resolution_due_at);

        return $left / $window < 0.25
            ? ['key' => 'at_risk', 'label' => 'SLA at risk', 'tone' => 'warning']
            : ['key' => 'on_track', 'label' => 'On track', 'tone' => 'success'];
    }

    public function visitLabel(int $visitNo): string
    {
        return sprintf('V%02d', $visitNo);
    }

    public function equipmentSummary(): string
    {
        return collect([
            ComplaintOptions::label('equipment_types', $this->equipment_type),
            $this->make,
            $this->model,
            $this->capacity,
        ])->filter()->join(' · ') ?: 'Equipment not recorded';
    }

    public function chargesSubtotal(): float
    {
        return (float) $this->visit_charges + (float) $this->labour_charges + (float) $this->parts_charges
            + (float) $this->travel_charges + (float) $this->other_charges - (float) $this->discount;
    }
}
