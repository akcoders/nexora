<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\ServiceJob;
use App\Models\Task;
use App\Models\User;
use App\Support\RecordVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $today = now()->toDateString();
        $technicians = User::whereHas('roles', fn ($query) => $query->where('name', 'Technician'))->count();
        $present = Attendance::whereDate('attendance_date', $today)->count();

        $visibleTasks = $request->user()->canAny(['tasks.read', 'tasks.view_own'])
            ? RecordVisibility::scope(Task::query(), $request->user(), 'tasks')
            : Task::query()->whereRaw('1 = 0');

        return view('dashboard', [
            'metrics' => [
                'technicians' => $technicians,
                'present' => $present,
                'absent' => max(0, $technicians - $present),
                'outside' => Attendance::whereDate('attendance_date', $today)->where('inside_premises', false)->count(),
                'reviews' => Attendance::where('status', 'pending')->count(),
                'pendingTasks' => (clone $visibleTasks)->open()->count(),
                'closedTasks' => (clone $visibleTasks)->closed()->count(),
                'overdueTasks' => (clone $visibleTasks)->overdue()->count(),
                'serviceJobsToday' => ServiceJob::whereDate('scheduled_at', $today)->count(),
                'serviceJobsInProgress' => ServiceJob::whereIn('status', ['on_the_way', 'arrived', 'inspection_in_progress', 'service_in_progress'])->count(),
            ],
            'recentTasks' => (clone $visibleTasks)->with(['assignee', 'customer'])->latest()->limit(6)->get(),
            'activities' => ActivityLog::latest()->limit(6)->get(),
            'attendanceTrend' => collect(range(6, 0))->map(fn (int $days) => [
                'date' => now()->subDays($days)->format('d M'),
                'count' => Attendance::whereDate('attendance_date', now()->subDays($days))->count(),
            ]),
        ]);
    }
}
