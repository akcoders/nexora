<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tasks = Task::with(['customer', 'assignee'])
            ->where(function ($query) use ($request) {
                $query->where('assigned_to', $request->user()->id)
                    ->orWhereHas('members', fn ($query) => $query->where('user_id', $request->user()->id));
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('task_type', $request->string('type')))
            ->orderByRaw("CASE WHEN priority = 'very_high' THEN 1 WHEN priority = 'high' THEN 2 ELSE 3 END")
            ->orderBy('due_at')
            ->paginate(20);

        return response()->json($tasks);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        abort_unless($task->assigned_to === $request->user()->id || $task->members()->where('user_id', $request->user()->id)->exists(), 403);

        return response()->json([
            'task' => $task->load(['customer', 'assignee', 'creator', 'actions.user', 'actions.assignee']),
            'assignees' => User::where('status', 'active')->orderBy('name')->get(['id', 'name', 'employee_code', 'designation']),
        ]);
    }

    public function action(Request $request, Task $task): JsonResponse
    {
        abort_unless($task->assigned_to === $request->user()->id, 403, 'Only the current assignee can act on this task.');
        abort_if($task->status === 'closed', 422, 'This task is already closed.');
        if ((bool) Setting::valueFor('require_attendance_for_tasks', config('nexora.require_attendance_for_task_actions'))) {
            abort_unless(Attendance::where('user_id', $request->user()->id)->whereDate('attendance_date', today())->exists(), 422, 'Mark attendance before taking task action.');
        }

        $validated = $request->validate([
            'action' => ['required', Rule::in(['close', 'assign'])],
            'remark' => ['required', 'string', 'max:3000'],
            'assigned_to' => ['required_if:action,assign', 'nullable', 'exists:users,id'],
        ]);

        DB::transaction(function () use ($request, $task, $validated) {
            $task->actions()->create(['user_id' => $request->user()->id, 'action' => $validated['action'], 'remark' => $validated['remark'], 'assigned_to' => $validated['assigned_to'] ?? null]);
            $task->update($validated['action'] === 'close'
                ? ['status' => 'closed', 'closed_at' => now()]
                : ['status' => 'pending', 'assigned_to' => $validated['assigned_to'], 'closed_at' => null]);
        });

        if ($validated['action'] === 'assign') {
            $task->fresh()->assignee->notify(new TaskAssignedNotification($task->fresh()));
        }

        return response()->json(['message' => 'Task updated.', 'task' => $task->fresh()->load(['assignee', 'actions.user', 'actions.assignee'])]);
    }
}
