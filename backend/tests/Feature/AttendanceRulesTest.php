<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Setting;
use App\Services\AttendancePolicy;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_grace_periods_classify_full_and_half_days(): void
    {
        collect([
            'attendance_check_in_time' => '09:00',
            'attendance_check_in_grace_minutes' => '15',
            'attendance_checkout_time' => '18:00',
            'attendance_checkout_grace_minutes' => '15',
        ])->each(fn ($value, $key) => Setting::updateOrCreate(['key' => $key], ['value' => $value]));

        $policy = app(AttendancePolicy::class);

        $this->assertSame('full_day', $policy->checkInDayStatus(Carbon::parse('2026-10-04 09:15:00')));
        $this->assertSame('half_day', $policy->checkInDayStatus(Carbon::parse('2026-10-04 09:16:00')));

        $attendance = new Attendance(['day_status' => 'full_day', 'status' => 'approved']);
        $this->assertSame('full_day', $policy->checkoutDayStatus($attendance, Carbon::parse('2026-10-04 17:45:00')));
        $this->assertSame('half_day', $policy->checkoutDayStatus($attendance, Carbon::parse('2026-10-04 17:44:00')));
    }

    public function test_review_state_takes_priority_in_calendar_status(): void
    {
        $policy = app(AttendancePolicy::class);

        $this->assertSame('pending_review', $policy->displayStatus(new Attendance(['status' => 'pending', 'day_status' => 'full_day'])));
        $this->assertSame('absent', $policy->displayStatus(new Attendance(['status' => 'rejected', 'day_status' => 'full_day'])));
        $this->assertSame('half_day', $policy->displayStatus(new Attendance(['status' => 'approved', 'day_status' => 'half_day'])));
        $this->assertSame('present', $policy->displayStatus(new Attendance(['status' => 'approved', 'day_status' => 'full_day'])));
    }
}
