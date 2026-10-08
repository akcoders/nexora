<?php

namespace App\Models;

use Database\Factories\CompanySiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySite extends Model
{
    /** @use HasFactory<CompanySiteFactory> */
    use HasFactory;

    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    protected function casts(): array
    {
        return ['address' => 'array', 'contacts' => 'array', 'active' => 'boolean'];
    }
}
