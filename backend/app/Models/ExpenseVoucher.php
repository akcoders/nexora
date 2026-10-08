<?php

namespace App\Models;

use Database\Factories\ExpenseVoucherFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseVoucher extends Model
{
    /** @use HasFactory<ExpenseVoucherFactory> */
    use HasFactory;

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'amount' => 'decimal:2', 'reviewed_at' => 'datetime'];
    }
}
