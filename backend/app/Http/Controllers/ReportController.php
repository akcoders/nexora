<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Task;
use App\Models\TaskAction;
use App\Models\User;
use App\Support\RecordVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $days = collect(range(29, 0))->map(fn ($days) => now()->subDays($days));

        $revisions = TaskAction::query()->whereNotNull('revised_due_at')->whereNull('related_task_id');
        if ($request->user()->canAny(['tasks.read', 'tasks.view_own'])) {
            $revisions->whereHas('task', fn ($query) => RecordVisibility::scope($query, $request->user(), 'tasks')->where('task_type', Task::TYPE_WORKFLOW));
        } else {
            $revisions->whereRaw('1 = 0');
        }

        return view('reports.index', [
            'revisions' => $revisions->with(['task', 'user'])->latest('id')->paginate(25, ['*'], 'revisions_page')->withQueryString(),
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
