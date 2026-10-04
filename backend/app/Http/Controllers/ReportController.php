<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        $days = collect(range(29, 0))->map(fn ($days) => now()->subDays($days));

        return view('reports.index', [
            'summary' => [
                'customers' => Customer::count(),
                'technicians' => User::whereHas('roles', fn ($query) => $query->where('name', 'Technician'))->count(),
                'attendance' => Attendance::whereMonth('attendance_date', now()->month)->count(),
                'completion' => Task::count() ? round(Task::where('status', 'closed')->count() / Task::count() * 100) : 0,
            ],
            'attendanceSeries' => $days->map(fn ($date) => Attendance::whereDate('attendance_date', $date)->count()),
            'taskSeries' => $days->map(fn ($date) => Task::whereDate('created_at', $date)->count()),
            'labels' => $days->map->format('d M'),
            'technicianStats' => User::whereHas('roles', fn ($query) => $query->where('name', 'Technician'))->withCount(['attendances', 'assignedTasks'])->get(),
        ]);
    }
}
