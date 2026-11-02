<?php

namespace App\Models;

use App\Services\ComplaintOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplaintVisit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'appointment_at' => 'datetime',
            'accepted_at' => 'datetime',
            'en_route_at' => 'datetime',
            'arrived_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'labour_hours' => 'decimal:2',
            'checks' => 'array',
            'diagnosis' => 'array',
            'attachments' => 'array',
        ];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function assistantOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assistant_one_id');
    }

    public function assistantTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assistant_two_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function parts(): HasMany
    {
        return $this->hasMany(ComplaintPart::class);
    }

    public function label(): string
    {
        return sprintf('V%02d', $this->visit_no);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['completed', 'cancelled'], true);
    }

    /** @return array{label: string, tone: string} */
    public function statusMeta(): array
    {
        return ComplaintOptions::VISIT_STATUSES[$this->status] ?? ['label' => str((string) $this->status)->headline()->toString(), 'tone' => 'secondary'];
    }

    /** @return array{label: string, tone: string, status: string}|null */
    public function outcomeMeta(): ?array
    {
        return ComplaintOptions::VISIT_OUTCOMES[$this->outcome] ?? null;
    }

    public function check(string $key): ?string
    {
        $value = data_get($this->checks, $key);

        return is_string($value) ? $value : null;
    }

    /** Team on the visit, technician first. */
    public function teamNames(): string
    {
        return collect([$this->technician?->name, $this->assistantOne?->name, $this->assistantTwo?->name])->filter()->join(', ');
    }
}
