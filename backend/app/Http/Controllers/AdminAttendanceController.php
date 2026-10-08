<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceReview;
use App\Models\Premises;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminAttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'employee' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
        ]);
        $month = $validated['month'] ?? now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $attendances = Attendance::with(['user', 'premises', 'checkoutPremises'])
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->when($request->filled('employee'), fn ($query) => $query->where('user_id', $request->integer('employee')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('attendance_date')->latest('checked_in_at')->paginate(31)->withQueryString();

        return view('attendance.index', [
            'attendances' => $attendances,
            'month' => $month,
            'start' => $start,
            'pendingCount' => Attendance::where('status', 'pending')->count(),
            'premises' => Premises::with(['users:id,name'])->withCount('users')->orderBy('name')->get(),
            'technicians' => User::whereHas('roles', fn ($query) => $query->where('name', 'Technician'))->orderBy('name')->get(),
            'employees' => User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'employee_code']),
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
