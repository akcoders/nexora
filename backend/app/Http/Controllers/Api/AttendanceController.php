<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->attendances()->with('premises')->latest('attendance_date')->paginate(31));
    }

    public function today(Request $request): JsonResponse
    {
        $attendance = $request->user()->attendances()->with('premises')->whereDate('attendance_date', today())->first();

        return response()->json(['attendance' => $attendance]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'premises_id' => ['required', 'exists:premises,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'selfie' => ['required', 'image', 'max:5120'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_if(Attendance::where('user_id', $request->user()->id)->whereDate('attendance_date', today())->exists(), 422, 'Attendance is already marked today.');

        $premises = $request->user()->premises()->whereKey($validated['premises_id'])->firstOrFail();
        $distance = $this->distanceInMeters((float) $validated['latitude'], (float) $validated['longitude'], (float) $premises->latitude, (float) $premises->longitude);
        $inside = $distance <= $premises->radius_meters;
        abort_if(! $inside && blank($validated['remark'] ?? null), 422, 'A remark is required outside the premises.');

        $attendance = Attendance::create([
            'user_id' => $request->user()->id,
            'premises_id' => $premises->id,
            'attendance_date' => today(),
            'checked_in_at' => now(),
            'check_in_latitude' => $validated['latitude'],
            'check_in_longitude' => $validated['longitude'],
            'distance_meters' => round($distance),
            'inside_premises' => $inside,
            'selfie_path' => $request->file('selfie')->store('attendance/'.today()->format('Y/m'), 'public'),
            'remark' => $validated['remark'] ?? null,
            'status' => $inside ? 'approved' : 'pending',
        ]);

        ActivityLog::create(['user_id' => $request->user()->id, 'event' => 'attendance.checked_in', 'subject_type' => Attendance::class, 'subject_id' => $attendance->id, 'properties' => ['inside' => $inside, 'distance' => round($distance)], 'ip_address' => $request->ip()]);

        return response()->json(['message' => 'Check-in recorded.', 'attendance' => $attendance->load('premises')], 201);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        $attendance = Attendance::where('user_id', $request->user()->id)->whereDate('attendance_date', today())->firstOrFail();
        abort_if($attendance->checked_out_at, 422, 'Already checked out.');
        $attendance->update(['checked_out_at' => now(), 'check_out_latitude' => $validated['latitude'], 'check_out_longitude' => $validated['longitude']]);

        return response()->json(['message' => 'Checkout recorded.', 'attendance' => $attendance->fresh()]);
    }

    private function distanceInMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371000;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);
        $a = sin($latDelta / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
