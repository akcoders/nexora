<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Setting;
use Carbon\CarbonInterface;

class AttendancePolicy
{
    public function rules(): array
    {
        return [
            'check_in_time' => (string) Setting::valueFor('attendance_check_in_time', '09:00'),
            'check_in_grace_minutes' => (int) Setting::valueFor('attendance_check_in_grace_minutes', 15),
            'checkout_time' => (string) Setting::valueFor('attendance_checkout_time', '18:00'),
            'checkout_grace_minutes' => (int) Setting::valueFor('attendance_checkout_grace_minutes', 15),
            'auto_checkout_time' => (string) Setting::valueFor('auto_checkout_time', '23:59'),
        ];
    }

    public function checkInDayStatus(CarbonInterface $checkedInAt): string
    {
        $rules = $this->rules();
        $deadline = $checkedInAt->copy()
            ->startOfDay()
            ->setTimeFromTimeString($rules['check_in_time'])
            ->addMinutes($rules['check_in_grace_minutes']);

        return $checkedInAt->lte($deadline) ? 'full_day' : 'half_day';
    }

    public function checkoutDayStatus(Attendance $attendance, CarbonInterface $checkedOutAt): string
    {
        if ($attendance->day_status === 'half_day') {
            return 'half_day';
        }

        $rules = $this->rules();
        $earliestFullDayCheckout = $checkedOutAt->copy()
            ->startOfDay()
            ->setTimeFromTimeString($rules['checkout_time'])
            ->subMinutes($rules['checkout_grace_minutes']);

        return $checkedOutAt->gte($earliestFullDayCheckout) ? 'full_day' : 'half_day';
    }

    public function displayStatus(Attendance $attendance): string
    {
        if ($attendance->status === 'pending') {
            return 'pending_review';
        }

        if ($attendance->status === 'rejected') {
            return 'absent';
        }

        return $attendance->day_status === 'half_day' ? 'half_day' : 'present';
    }
}
