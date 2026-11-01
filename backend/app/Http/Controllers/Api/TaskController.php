<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskActionRequest;
use App\Models\Attendance;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskWorkflow;
use App\Support\RecordVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($request->input('status') === 'in_progress') {
            $request->merge(['status' => 'pending']);
        }
        $filters = $request->validate([
            'scope' => ['nullable', Rule::in(['open'])],
            'status' => ['nullable', Rule::in(['pending', 'closed', 'overdue'])],
            'type' => ['nullable', Rule::in([Task::TYPE_WORKFLOW, Task::TYPE_TICKET])],
        ]);

        $tasks = RecordVisibility::scope(Task::query(), $request->user(), 'tasks')
            ->withVisibleParent($request->user())->with(['customer', 'assignee', 'latestAction.user', 'latestAction.assignee', 'observers.user:id,name'])
            ->withCount(['actions', 'subtasks', 'subtasks as completed_subtasks_count' => fn ($query) => $query->closed()])
            ->whereIn('task_type', [Task::TYPE_WORKFLOW, Task::TYPE_TICKET])
            ->when(($filters['scope'] ?? null) === 'open', fn ($query) => $query->where('status', '!=', 'closed'))
            ->when(($filters['status'] ?? null) === 'overdue', fn ($query) => $query->overdue())
            ->when(isset($filters['status']) && $filters['status'] !== 'overdue', fn ($query) => $query->where('status', $filters['status']))
            ->where('task_type', $filters['type'] ?? Task::TYPE_WORKFLOW)
            ->orderByRaw("CASE WHEN status = 'closed' THEN 2 ELSE 1 END")
            ->orderByRaw("CASE WHEN priority = 'very_high' THEN 1 WHEN priority = 'high' THEN 2 ELSE 3 END")
            ->orderBy('due_at')
            ->paginate(20);

        return response()->json($tasks);
    }

    public function show(Request $request, Task $task): JsonResponse
    {
        abort_unless(RecordVisibility::allows($request->user(), $task, 'tasks'), 403);

        return response()->json([
            'task' => $task->load(['customer', 'assignee', 'creator', 'observers.user:id,name'])->loadVisibleRelations($request->user()),
            'assignees' => User::where('status', 'active')->orderBy('name')->get(['id', 'name', 'employee_code', 'designation']),
        ]);
    }

    public function action(TaskActionRequest $request, Task $task, TaskWorkflow $workflow): JsonResponse
    {
        abort_unless(RecordVisibility::allows($request->user(), $task, 'tasks'), 403);
        abort_unless($task->assigned_to === $request->user()->id, 403, 'Only the current assignee can act on this task.');
        abort_if($task->status === 'closed', 422, 'This task is already closed.');
        if ((bool) Setting::valueFor('require_attendance_for_tasks', config('nexora.require_attendance_for_task_actions'))) {
            abort_unless(Attendance::where('user_id', $request->user()->id)->whereDate('attendance_date', today())->exists(), 422, 'Mark attendance before taking task action.');
        }

        $workflow->act($task, $request->validated(), $request->user());

        return response()->json(['message' => 'Task updated.', 'task' => $task->fresh()->load('assignee')->loadVisibleRelations($request->user())]);
    }
}
