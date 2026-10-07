<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceReview;
use App\Models\Premises;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminAttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $date = $request->filled('date') ? $request->date('date') : today();
        $attendances = Attendance::with(['user', 'premises'])
            ->whereDate('attendance_date', $date)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('checked_in_at')->paginate(20)->withQueryString();

        return view('attendance.index', [
            'attendances' => $attendances,
            'date' => $date,
            'pendingCount' => Attendance::where('status', 'pending')->count(),
            'premises' => Premises::with('users:id,name')->withCount('users')->orderBy('name')->get(),
            'activePremisesCount' => Premises::where('active', true)->count(),
            'technicians' => User::whereHas('roles', fn ($query) => $query->where('name', 'Technician'))->orderBy('name')->get(),
        ]);
    }

    public function review(Request $request, Attendance $attendance): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'remark' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $attendance, $data) {
            AttendanceReview::create(['attendance_id' => $attendance->id, 'reviewer_id' => $request->user()->id, 'decision' => $data['decision'], 'remark' => $data['remark']]);
            $attendance->update(['status' => $data['decision']]);
        });

        return back()->with('success', 'Attendance review saved.');
    }
}
