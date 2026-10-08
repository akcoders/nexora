<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\Holiday;
use App\Models\PayrollRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function generate(string $month, User $generatedBy, ?string $notes = null): PayrollRun
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $end = $start->endOfMonth();
        $holidayDates = Holiday::query()->whereBetween('holiday_date', [$start, $end])->where('optional', false)->pluck('holiday_date')->map->format('Y-m-d')->all();
        $workingDates = collect(CarbonPeriod::create($start, $end))
            ->filter(fn ($date): bool => $date->dayOfWeekIso <= 6 && ! in_array($date->format('Y-m-d'), $holidayDates, true))
            ->map(fn ($date): string => $date->format('Y-m-d'));

        return DB::transaction(function () use ($month, $generatedBy, $notes, $start, $end, $workingDates): PayrollRun {
            $run = PayrollRun::query()->where('payroll_month', $month)->lockForUpdate()->first();
            abort_if($run && $run->status !== 'draft', 422, 'Approved payroll cannot be regenerated.');
            $run ??= PayrollRun::create([
                'payroll_month' => $month,
                'status' => 'draft',
                'generated_by' => $generatedBy->id,
                'generated_at' => now(),
            ]);
            $run->update(['generated_by' => $generatedBy->id, 'generated_at' => now(), 'notes' => $notes]);
            $run->entries()->delete();

            EmployeeProfile::query()
                ->whereHas('user', fn ($query) => $query->where('status', 'active'))
                ->with([
                    'user.attendances' => fn ($query) => $query->whereBetween('attendance_date', [$start, $end]),
                    'user.leaveRequests' => fn ($query) => $query->where('status', 'approved')->where('from_date', '<=', $end)->where('to_date', '>=', $start)->with('leaveType'),
                ])
                ->chunkById(100, function ($profiles) use ($run, $start, $end, $workingDates): void {
                    foreach ($profiles as $profile) {
                        $approvedAttendance = $profile->user->attendances->where('status', 'approved');
                        $fullDays = $approvedAttendance->where('day_status', 'full_day')->count();
                        $halfDays = $approvedAttendance->where('day_status', 'half_day')->count();
                        $paidLeaveDates = collect();
                        foreach ($profile->user->leaveRequests->where('leaveType.paid', true) as $leave) {
                            $leaveStart = $leave->from_date->max($start);
                            $leaveEnd = $leave->to_date->min($end);
                            foreach (CarbonPeriod::create($leaveStart, $leaveEnd) as $date) {
                                if ($workingDates->contains($date->format('Y-m-d'))) {
                                    $paidLeaveDates->push($date->format('Y-m-d'));
                                }
                            }
                        }
                        $paidLeaveDays = $paidLeaveDates->unique()->count();
                        $workingDays = $workingDates->count();
                        $payableDays = min($workingDays, $fullDays + ($halfDays * 0.5) + $paidLeaveDays);
                        $ratio = $workingDays > 0 ? $payableDays / $workingDays : 0;
                        $earnings = [
                            'basic' => round((float) $profile->basic_salary * $ratio, 2),
                            'hra' => round((float) $profile->hra * $ratio, 2),
                            'transport_allowance' => round((float) $profile->transport_allowance * $ratio, 2),
                            'fuel_allowance' => round((float) $profile->fuel_allowance * $ratio, 2),
                            'other_allowance' => round((float) $profile->other_allowance * $ratio, 2),
                        ];
                        $gross = round(array_sum($earnings), 2);
                        $compensation = $profile->compensation ?? [];
                        $deductions = [
                            'pf' => round($earnings['basic'] * ((float) ($compensation['pf_percent'] ?? 0) / 100), 2),
                            'esic' => round($gross * ((float) ($compensation['esic_percent'] ?? 0) / 100), 2),
                            'professional_tax' => round((float) ($compensation['professional_tax'] ?? 0), 2),
                            'tds' => round((float) ($compensation['tds_amount'] ?? 0), 2),
                            'loan_emi' => round((float) ($compensation['loan_emi'] ?? 0), 2),
                        ];
                        $deductionAmount = round(array_sum($deductions), 2);
                        $run->entries()->create([
                            'user_id' => $profile->user_id,
                            'working_days' => $workingDays,
                            'present_days' => $fullDays,
                            'half_days' => $halfDays,
                            'paid_leave_days' => $paidLeaveDays,
                            'unpaid_days' => max(0, $workingDays - $payableDays),
                            'payable_days' => $payableDays,
                            'earnings' => $earnings,
                            'deductions' => $deductions,
                            'gross_amount' => $gross,
                            'deduction_amount' => $deductionAmount,
                            'net_amount' => max(0, $gross - $deductionAmount),
                            'status' => 'draft',
                        ]);
                    }
                });

            return $run->load('entries.user');
        });
    }
}
