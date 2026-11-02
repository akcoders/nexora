<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintPart extends Model
{
    public const KIND_USED = 'used';

    public const KIND_RECOMMENDED = 'recommended';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'estimated_cost' => 'decimal:2',
            'billable_rate' => 'decimal:2',
            'available_in_stock' => 'boolean',
            'oem_warranty' => 'boolean',
            'old_part_returned' => 'boolean',
            'customer_approval_required' => 'boolean',
            'quotation_given' => 'boolean',
            'manager_approval_required' => 'boolean',
            'higher_approval_required' => 'boolean',
        ];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(ComplaintVisit::class, 'complaint_visit_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lineTotal(): float
    {
        return round((float) $this->quantity * (float) $this->billable_rate, 2);
    }
}
