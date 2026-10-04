<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = now()->toDateString();
        $technicians = User::whereHas('roles', fn ($query) => $query->where('name', 'Technician'))->count();
        $present = Attendance::whereDate('attendance_date', $today)->count();

        return view('dashboard', [
            'metrics' => [
                'technicians' => $technicians,
                'present' => $present,
                'absent' => max(0, $technicians - $present),
                'outside' => Attendance::whereDate('attendance_date', $today)->where('inside_premises', false)->count(),
                'reviews' => Attendance::where('status', 'pending')->count(),
                'pendingTasks' => Task::where('status', 'pending')->count(),
                'closedTasks' => Task::where('status', 'closed')->count(),
                'overdueTasks' => Task::where('status', 'pending')->where('due_at', '<', now())->count(),
            ],
            'recentTasks' => Task::with(['assignee', 'customer'])->latest()->limit(6)->get(),
            'activities' => ActivityLog::latest()->limit(6)->get(),
            'attendanceTrend' => collect(range(6, 0))->map(fn (int $days) => [
                'date' => now()->subDays($days)->format('d M'),
                'count' => Attendance::whereDate('attendance_date', now()->subDays($days))->count(),
            ]),
        ]);
    }
}
