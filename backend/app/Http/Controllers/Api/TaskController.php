<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Services\OneSignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'scope' => ['nullable', Rule::in(['open'])],
            'status' => ['nullable', Rule::in(['pending', 'in_progress', 'closed'])],
            'type' => ['nullable', Rule::in([Task::TYPE_WORKFLOW, Task::TYPE_TICKET])],
        ]);

        $tasks = Task::with(['customer', 'assignee', 'latestAction.user', 'latestAction.assignee'])
            ->withCount('actions')
            ->whereIn('task_type', [Task::TYPE_WORKFLOW, Task::TYPE_TICKET])
            ->where(function ($query) use ($request) {
                $query->where('assigned_to', $request->user()->id)
                    ->orWhereHas('members', fn ($query) => $query->where('user_id', $request->user()->id));
            })
            ->when(($filters['scope'] ?? null) === 'open', fn ($query) => $query->where('status', '!=', 'closed'))
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['type']), fn ($query) => $query->where('task_type', $filters['type']))
            ->orderByRaw("CASE WHEN status = 'closed' THEN 2 ELSE 1 END")
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

    public function action(Request $request, Task $task, OneSignalService $oneSignal): JsonResponse
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
            if ($validated['action'] === 'assign') {
                $task->members()->firstOrCreate(['user_id' => $task->assigned_to, 'role' => 'previous_assignee']);
                $task->members()->firstOrCreate(['user_id' => $validated['assigned_to'], 'role' => 'assignee']);
            }
            $task->update($validated['action'] === 'close'
                ? ['status' => 'closed', 'closed_at' => now()]
                : ['status' => 'pending', 'assigned_to' => $validated['assigned_to'], 'closed_at' => null]);
        });

        if ($validated['action'] === 'assign') {
            $task->fresh()->assignee->notify(new TaskAssignedNotification($task->fresh()));
            $type = $task->task_type === Task::TYPE_TICKET ? 'ticket' : 'workflow';
            $oneSignal->sendToUser($task->fresh()->assignee, ucfirst($type).' assigned to you', $task->title, ['type' => $type.'_task', 'task_id' => $task->id]);
        }

        return response()->json(['message' => 'Task updated.', 'task' => $task->fresh()->load(['assignee', 'actions.user', 'actions.assignee'])]);
    }
}
