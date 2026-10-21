<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Task extends Model
{
    use SoftDeletes;

    public const TYPE_TICKET = 'ticket';

    public const TYPE_WORKFLOW = 'workflow';

    public const STATUS_CLOSED = 'closed';

    /** @var list<string> */
    public const PRIORITIES = ['normal', 'high', 'very_high'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(TaskAction::class)->latest();
    }

    public function latestAction(): HasOne
    {
        return $this->hasOne(TaskAction::class)->latestOfMany();
    }

    public function members(): HasMany
    {
        return $this->hasMany(TaskMember::class);
    }

    public function observers(): HasMany
    {
        return $this->members()->where('role', 'observer');
    }

    /** Internal workflows and customer tickets (excludes legacy task types). */
    public function scopeWorkItems(Builder $query): void
    {
        $query->whereIn('task_type', [self::TYPE_WORKFLOW, self::TYPE_TICKET]);
    }

    public function scopeOpen(Builder $query): void
    {
        $query->where('status', '!=', self::STATUS_CLOSED);
    }

    public function scopeClosed(Builder $query): void
    {
        $query->where('status', self::STATUS_CLOSED);
    }

    /** Very high priority first, then the nearest due date; items without a due date last. */
    public function scopeByUrgency(Builder $query): void
    {
        $query->orderByRaw("CASE WHEN priority = 'very_high' THEN 1 WHEN priority = 'high' THEN 2 ELSE 3 END")
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at');
    }

    /** Relations, counts and the viewer's watch flag needed to render a Kanban card. */
    public function scopeWithBoardData(Builder $query, User $viewer): void
    {
        $query->with(['customer', 'assignee', 'latestAction.user'])
            ->withCount(['actions', 'observers'])
            ->withExists(['observers as watched_by_viewer' => fn (Builder $query) => $query->where('user_id', $viewer->id)]);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isOverdue(): bool
    {
        return ! $this->isClosed() && (bool) $this->due_at?->isPast();
    }

    /** Changes the priority and records it on the timeline. */
    public function changePriority(string $priority, User $by, ?string $note = null): void
    {
        if ($priority === $this->priority) {
            return;
        }

        DB::transaction(function () use ($priority, $by, $note): void {
            $summary = 'Priority changed from '.str($this->priority)->headline().' to '.str($priority)->headline().'.';
            $this->actions()->create(['user_id' => $by->id, 'action' => 'priority', 'remark' => trim($summary.' '.$note)]);
            $this->update(['priority' => $priority]);
        });
    }
}
