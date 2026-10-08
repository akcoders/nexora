<?php

namespace App\Models;

use Database\Factories\EmployeeProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProfile extends Model
{
    /** @use HasFactory<EmployeeProfileFactory> */
    use HasFactory;

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporting_manager_id');
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'anniversary_date' => 'date',
            'date_of_joining' => 'date',
            'languages' => 'array',
            'identity_documents' => 'array',
            'current_address' => 'array',
            'permanent_address' => 'array',
            'company_accommodation_address' => 'array',
            'emergency_family' => 'array',
            'employment_details' => 'array',
            'verification' => 'array',
            'compensation' => 'array',
            'bank_details' => 'array',
            'benefits_allowances' => 'array',
            'education_skills' => 'array',
            'safety' => 'array',
            'performance_career' => 'array',
            'assets_access' => 'array',
            'engagement_wellbeing' => 'array',
            'exit_separation' => 'array',
            'documents' => 'array',
            'basic_salary' => 'decimal:2',
            'hra' => 'decimal:2',
            'transport_allowance' => 'decimal:2',
            'fuel_allowance' => 'decimal:2',
            'other_allowance' => 'decimal:2',
            'gross_monthly_salary' => 'decimal:2',
        ];
    }
}
