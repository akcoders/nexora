<?php

namespace App\Models;

use Database\Factories\EmployeeProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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

    public function hrManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_manager_id');
    }

    public function probationConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'probation_confirmed_by');
    }

    /**
     * Employees still on probation whose probation end date has arrived and HR has not been told yet.
     */
    public function scopeProbationDue(Builder $query, ?Carbon $asOf = null): void
    {
        $query->where('employment_type', 'probation')
            ->whereNull('probation_confirmed_at')
            ->whereNull('probation_notified_at')
            ->whereNotNull('probation_end_date')
            ->whereDate('probation_end_date', '<=', ($asOf ?? now())->toDateString());
    }

    public function isOnProbation(): bool
    {
        return $this->employment_type === 'probation' && $this->probation_confirmed_at === null;
    }

    public static function probationEndDate(?string $dateOfJoining, ?int $months): ?Carbon
    {
        if (blank($dateOfJoining) || ! $months) {
            return null;
        }

        return Carbon::parse($dateOfJoining)->addMonthsNoOverflow($months);
    }

    public function noticePeriodDays(): ?int
    {
        $days = data_get($this->employment_details, 'notice_period_days');

        return is_numeric($days) ? (int) $days : null;
    }

    public function resignationDate(): ?Carbon
    {
        $date = data_get($this->exit_separation, 'resignation_date');

        return filled($date) ? Carbon::parse($date) : null;
    }

    public function lastWorkingDate(): ?Carbon
    {
        $date = data_get($this->exit_separation, 'last_working_date');

        return filled($date) ? Carbon::parse($date) : null;
    }

    /**
     * Where the employee stands today: exited, serving notice, on probation (ending / overdue)
     * or the plain employment type.
     *
     * @return array{key: string, label: string, tone: string, detail: ?string}
     */
    public function employmentStatus(): array
    {
        $today = now()->startOfDay();
        $lastDay = $this->lastWorkingDate();
        $resigned = $this->resignationDate();

        if ($lastDay && $lastDay->lt($today)) {
            return ['key' => 'exited', 'label' => 'Exited', 'tone' => 'secondary', 'detail' => 'Last working day '.$lastDay->format('d M Y')];
        }

        if ($resigned) {
            $detail = $lastDay ? 'Last working day '.$lastDay->format('d M Y').' · '.(int) $today->diffInDays($lastDay).' days left' : 'Resigned '.$resigned->format('d M Y');

            return ['key' => 'notice', 'label' => 'Serving notice', 'tone' => 'warning', 'detail' => $detail];
        }

        if ($this->isOnProbation()) {
            $end = $this->probation_end_date;
            if (! $end) {
                return ['key' => 'probation', 'label' => 'Probation', 'tone' => 'info', 'detail' => 'Probation end date not set'];
            }
            if ($end->lt($today)) {
                return ['key' => 'probation_review', 'label' => 'Probation review due', 'tone' => 'danger', 'detail' => 'Ended '.$end->format('d M Y').' · awaiting HR confirmation'];
            }

            return ['key' => 'probation', 'label' => 'Probation', 'tone' => 'info', 'detail' => 'Ends '.$end->format('d M Y').' · '.(int) $today->diffInDays($end).' days left'];
        }

        $type = $this->employment_type ?: 'permanent';

        return ['key' => $type, 'label' => str($type)->headline()->toString(), 'tone' => $type === 'permanent' ? 'success' : 'primary', 'detail' => null];
    }

    /** @return list<array<string, mixed>> */
    public function emergencyContacts(): array
    {
        $family = $this->emergency_family ?? [];
        if (is_array($family['contacts'] ?? null)) {
            return array_values($family['contacts']);
        }

        return array_values(array_filter([
            ['name' => $family['emergency_name'] ?? '', 'relation' => $family['relation'] ?? '', 'mobile' => $family['mobile'] ?? ''],
            ['name' => $family['secondary_name'] ?? '', 'relation' => $family['secondary_relation'] ?? '', 'mobile' => $family['secondary_mobile'] ?? ''],
        ], fn (array $contact): bool => filled($contact['name']) || filled($contact['mobile'])));
    }

    /** @return list<array<string, mixed>> */
    public function educationRecords(): array
    {
        $details = $this->education_skills ?? [];

        return is_array($details['education_records'] ?? null)
            ? array_values($details['education_records'])
            : (filled($details['highest_qualification'] ?? null) ? [['name' => $details['highest_qualification'], 'institution' => '']] : []);
    }

    /** @return list<array<string, mixed>> */
    public function certificationRecords(): array
    {
        $details = $this->education_skills ?? [];

        return is_array($details['certification_records'] ?? null)
            ? array_values($details['certification_records'])
            : (filled($details['certifications'] ?? null) ? [['name' => $details['certifications'], 'issuer' => '']] : []);
    }

    /** @return list<array<string, mixed>> */
    public function insurancePolicies(): array
    {
        $details = $this->benefits_allowances ?? [];

        return is_array($details['insurance_policies'] ?? null)
            ? array_values($details['insurance_policies'])
            : (filled($details['insurance_policy'] ?? null) ? [['name' => $details['insurance_policy'], 'number' => '']] : []);
    }

    /** @return list<array<string, mixed>> */
    public function assignedAssets(): array
    {
        $details = $this->assets_access ?? [];
        if (is_array($details['assigned_assets'] ?? null)) {
            return array_values($details['assigned_assets']);
        }
        $assets = [];
        foreach (['laptop' => 'Laptop', 'mobile' => 'Company mobile', 'sim' => 'SIM', 'uniform' => 'Uniform', 'toolkit' => 'Toolkit', 'access_card' => 'Access card'] as $key => $name) {
            $value = $details[$key] ?? null;
            if (filled($value) && ! in_array(strtolower((string) $value), ['not issued', 'returned', 'no'])) {
                $assets[] = ['name' => $name, 'identifier' => $value, 'items' => []];
            }
        }

        return $assets;
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'anniversary_date' => 'date',
            'date_of_joining' => 'date',
            'probation_end_date' => 'date',
            'probation_notified_at' => 'datetime',
            'probation_confirmed_at' => 'datetime',
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
