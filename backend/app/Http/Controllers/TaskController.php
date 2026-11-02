<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\TaskActionRequest;
use App\Http\Requests\UpdateTaskPriorityRequest;
use App\Http\Resources\TaskBoardCardResource;
use App\Models\Task;
use App\Models\TaskAction;
use App\Models\User;
use App\Services\TaskWorkflow;
use App\Support\RecordVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskController extends Controller
{
    private const BOARD_CLOSED_LIMIT = 40;

    public function index(Request $request): View
    {
        if ($request->input('status') === 'in_progress') {
            $request->merge(['status' => 'pending']);
        }
        $workType = $this->workType($request);
        $visibleTasks = RecordVisibility::scope(Task::query(), $request->user(), 'tasks')->where('task_type', $workType);
        $tasks = (clone $visibleTasks)->withVisibleParent($request->user())->with(['customer', 'assignee', 'creator', 'latestAction.user', 'observers.user:id,name'])
            ->withCount(['actions', 'subtasks', 'subtasks as completed_subtasks_count' => fn ($query) => $query->closed()])
            ->when($request->string('scope')->toString() === 'open', fn ($query) => $query->open())
            ->when($request->input('status') === 'overdue', fn ($query) => $query->overdue())
            ->when($request->filled('status') && $request->input('status') !== 'overdue', fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->filled('assignee'), fn ($query) => $query->where('assigned_to', $request->integer('assignee')))
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($query) => $query
                ->where('title', 'like', '%'.$request->string('search').'%')
                ->orWhere('task_no', 'like', '%'.$request->string('search').'%')
                ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))))
            ->orderByRaw("CASE WHEN status = 'closed' THEN 2 ELSE 1 END")
            ->byUrgency()->orderByDesc('id')->limit(2000)->get();

        return view('tasks.index', [
            'tasks' => $tasks, 'workType' => $workType,
            'users' => User::active()->orderBy('name')->get(),
            'customers' => collect(),
            'openCount' => (clone $visibleTasks)->open()->count(),
            'closedCount' => (clone $visibleTasks)->closed()->count(),
            'overdueCount' => (clone $visibleTasks)->open()->where('due_at', '<', now())->count(),
        ]);
    }

    public function store(StoreTaskRequest $request, TaskWorkflow $workflow): RedirectResponse
    {
        $task = $workflow->create($request->validated(), $request->user());
        if ($request->validated('return_to') === 'board') {
            return redirect()->route('tasks.board', ['type' => $task->task_type])->with('success', $task->task_no.' created and added to the board.');
        }

        return redirect()->route('tasks.show', $task)->with('success', 'Created successfully.');
    }

    public function subtask(StoreTaskRequest $request, Task $task, TaskWorkflow $workflow): RedirectResponse|JsonResponse
    {
        $subtask = $workflow->create($request->validated(), $request->user(), $task);
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Subtask created and workflow assigned to its owner.', 'task' => Task::withVisibleParent($request->user())->findOrFail($subtask->id)], 201);
        }

        return redirect()->route('tasks.show', $task)->with('success', $subtask->task_no.' created. Workflow assigned to the subtask owner.');
    }

    public function board(Request $request): View|JsonResponse
    {
        $workType = $this->workType($request);
        $visibleTasks = RecordVisibility::scope(Task::query(), $request->user(), 'tasks')->where('task_type', $workType);
        if (! $request->expectsJson()) {
            return view('tasks.board', [
                'workType' => $workType,
                'users' => User::active()->orderBy('name')->get(),
                'customers' => collect(),
            ]);
        }
        $user = $request->user();
        $open = (clone $visibleTasks)->open()->withBoardData($user)->byUrgency()->get();
        $closed = (clone $visibleTasks)->closed()->withBoardData($user)->latest('closed_at')->limit(self::BOARD_CLOSED_LIMIT)->get();

        return response()->json([
            'tasks' => TaskBoardCardResource::collection($open->concat($closed))->resolve($request),
            'users' => User::active()->orderBy('name')->get(['id', 'name', 'designation']),
            'me' => $user->id, 'closed_limit' => self::BOARD_CLOSED_LIMIT,
            'can_reopen' => $user->can('tasks.update'), 'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function priority(UpdateTaskPriorityRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        abort_unless(RecordVisibility::allows($request->user(), $task, 'tasks'), 403);
        abort_if($task->isClosed(), 422, 'Closed items cannot be re-prioritised.');
        $task->changePriority($request->validated('priority'), $request->user(), $request->validated('remark'));
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $task->task_no.' priority updated.', 'task' => $this->boardCard($request, $task)]);
        }

        return back()->with('success', 'Priority updated.');
    }

    public function dueDate(Request $request, Task $task): never
    {
        abort_unless(RecordVisibility::allows($request->user(), $task, 'tasks') && $request->user()->can('act', $task), 403);
        throw ValidationException::withMessages([
            'due_at' => 'The original due date cannot be changed. Set a next follow-up date instead.',
        ]);
    }

    public function show(Request $request, Task $task): View
    {
        abort_unless(RecordVisibility::allows($request->user(), $task, 'tasks'), 403);

        return view('tasks.show', [
            'task' => $task->load(['customer', 'assignee', 'creator', 'members.user'])->loadVisibleRelations($request->user()),
            'workType' => $task->task_type,
            'users' => User::active()->orderBy('name')->get(),
            'customers' => collect(),
        ]);
    }

    public function action(TaskActionRequest $request, Task $task, TaskWorkflow $workflow): RedirectResponse|JsonResponse
    {
        $workflow->act($task, $request->validated(), $request->user(), $request->user()->can('tasks.update'));
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $task->task_no.' updated.', 'task' => $this->boardCard($request, $task)]);
        }

        return back()->with('success', 'Task action recorded.');
    }

    public function attachment(Request $request, Task $task, TaskAction $action, int $attachment): StreamedResponse
    {
        abort_unless(RecordVisibility::allows($request->user(), $task, 'tasks'), 403);
        abort_unless($action->task_id === $task->id, 404);
        $file = $action->attachments[$attachment] ?? null;
        abort_unless(is_array($file) && isset($file['path'], $file['name']), 404);
        abort_unless(Storage::disk('local')->exists($file['path']), 404);

        return Storage::disk('local')->download($file['path'], $file['name']);
    }

    /** Only internal workflows are managed here; customer complaints have their own register. */
    private function workType(Request $request): string
    {
        return Task::TYPE_WORKFLOW;
    }

    /** @return array<string, mixed> */
    private function boardCard(Request $request, Task $task): array
    {
        return (new TaskBoardCardResource(Task::withBoardData($request->user())->findOrFail($task->id)))->resolve($request);
    }
}
