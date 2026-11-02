<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintActivity extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['details' => 'array', 'follow_up_date' => 'date'];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(ComplaintVisit::class, 'complaint_visit_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function icon(): string
    {
        return match ($this->type) {
            'created' => 'file-plus-2',
            'visit_assigned' => 'user-plus',
            'visit_report' => 'clipboard-check',
            'pending' => 'hourglass',
            'follow_up' => 'phone-forwarded',
            'commercial' => 'indian-rupee',
            'closure', 'closed' => 'lock',
            'reopened' => 'rotate-ccw',
            'cancelled' => 'ban',
            'details' => 'square-pen',
            default => 'message-square',
        };
    }
}
