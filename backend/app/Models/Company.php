<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $guarded = [];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function sites(): HasMany
    {
        return $this->hasMany(CompanySite::class);
    }

    protected function casts(): array
    {
        return [
            'business_models' => 'array',
            'incorporation_date' => 'date',
            'address' => 'array',
            'contacts' => 'array',
            'localization' => 'array',
            'invoice_settings' => 'array',
            'compliance' => 'array',
            'banking' => 'array',
        ];
    }
}
