<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Services\OneSignalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $tasks = Task::with(['customer', 'assignee', 'creator', 'latestAction.user', 'latestAction.assignee'])
            ->withCount('actions')
            ->whereIn('task_type', [Task::TYPE_WORKFLOW, Task::TYPE_TICKET])
            ->when($request->string('scope')->toString() === 'open', fn ($query) => $query->where('status', '!=', 'closed'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->filled('type'), fn ($query) => $query->where('task_type', $request->string('type')))
            ->when($request->filled('assignee'), fn ($query) => $query->where('assigned_to', $request->integer('assignee')))
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($query) => $query
                ->where('title', 'like', '%'.$request->string('search').'%')
                ->orWhere('task_no', 'like', '%'.$request->string('search').'%')
                ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))))
            ->orderByRaw("CASE WHEN status = 'closed' THEN 2 ELSE 1 END")
            ->orderByRaw("CASE WHEN priority = 'very_high' THEN 1 WHEN priority = 'high' THEN 2 ELSE 3 END")
            ->orderBy('due_at')->paginate(20)->withQueryString();

        return view('tasks.index', [
            'tasks' => $tasks,
            'users' => User::where('status', 'active')->orderBy('name')->get(),
            'customers' => Customer::where('status', 'active')->orderBy('name')->get(),
            'openCount' => Task::where('status', '!=', 'closed')->count(),
            'workflowOpenCount' => Task::where('task_type', 'workflow')->where('status', '!=', 'closed')->count(),
            'ticketOpenCount' => Task::where('task_type', Task::TYPE_TICKET)->where('status', '!=', 'closed')->count(),
            'closedCount' => Task::where('status', 'closed')->count(),
            'overdueCount' => Task::where('status', '!=', 'closed')->where('due_at', '<', now())->count(),
        ]);
    }

    public function store(Request $request, OneSignalService $oneSignal): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'task_type' => ['required', Rule::in([Task::TYPE_WORKFLOW, Task::TYPE_TICKET])],
            'description' => ['nullable', 'string', 'max:5000'],
            'customer_id' => [
                Rule::requiredIf(fn (): bool => $request->string('task_type')->toString() === Task::TYPE_TICKET),
                Rule::prohibitedIf(fn (): bool => $request->string('task_type')->toString() === Task::TYPE_WORKFLOW),
                'nullable',
                'exists:customers,id',
            ],
            'category' => ['nullable', 'string', 'max:100'],
            'priority' => ['required', Rule::in(['normal', 'high', 'very_high'])],
            'due_at' => ['nullable', 'date'],
            'assigned_to' => ['required', 'exists:users,id'],
            'observers' => ['nullable', 'array'],
            'observers.*' => ['exists:users,id'],
        ]);

        $task = DB::transaction(function () use ($request, $data) {
            $observers = $data['observers'] ?? [];
            unset($data['observers']);
            $nextId = (Task::withTrashed()->max('id') ?? 0) + 1;
            $prefix = $data['task_type'] === Task::TYPE_TICKET ? 'TKT' : 'WFL';
            $data['customer_id'] = $data['task_type'] === Task::TYPE_TICKET ? $data['customer_id'] : null;
            $task = Task::create(array_merge($data, ['task_no' => $prefix.str_pad((string) $nextId, 6, '0', STR_PAD_LEFT), 'created_by' => $request->user()->id, 'status' => 'pending']));
            foreach ($observers as $observer) {
                $task->members()->create(['user_id' => $observer, 'role' => 'observer']);
            }
            $task->actions()->create(['user_id' => $request->user()->id, 'assigned_to' => $task->assigned_to, 'action' => 'created', 'remark' => 'Task created and assigned.']);

            return $task;
        });
        $task->assignee->notify(new TaskAssignedNotification($task));
        $label = $task->task_type === Task::TYPE_TICKET ? 'ticket' : 'workflow';
        $oneSignal->sendToUser($task->assignee, 'New '.ucfirst($label).' assigned', $task->title, ['type' => $label.'_task', 'task_id' => $task->id]);

        return redirect()->route('tasks.show', $task)->with('success', 'Task created successfully.');
    }

    public function show(Task $task): View
    {
        return view('tasks.show', [
            'task' => $task->load(['customer', 'assignee', 'creator', 'actions.user', 'actions.assignee', 'members.user']),
            'users' => User::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function action(Request $request, Task $task, OneSignalService $oneSignal): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['close', 'assign'])],
            'remark' => ['required', 'string', 'max:3000'],
            'assigned_to' => ['required_if:action,assign', 'nullable', 'exists:users,id'],
        ]);

        DB::transaction(function () use ($request, $task, $data) {
            $task->actions()->create(['user_id' => $request->user()->id, 'assigned_to' => $data['assigned_to'] ?? null, 'action' => $data['action'], 'remark' => $data['remark']]);
            if ($data['action'] === 'assign') {
                $task->members()->firstOrCreate(['user_id' => $task->assigned_to, 'role' => 'previous_assignee']);
                $task->members()->firstOrCreate(['user_id' => $data['assigned_to'], 'role' => 'assignee']);
            }
            $task->update($data['action'] === 'close'
                ? ['status' => 'closed', 'closed_at' => now()]
                : ['status' => 'pending', 'assigned_to' => $data['assigned_to'], 'closed_at' => null]);
        });
        if ($data['action'] === 'assign') {
            $task->fresh()->assignee->notify(new TaskAssignedNotification($task->fresh()));
            $type = $task->task_type === Task::TYPE_TICKET ? 'ticket' : 'workflow';
            $oneSignal->sendToUser($task->fresh()->assignee, ucfirst($type).' assigned to you', $task->title, ['type' => $type.'_task', 'task_id' => $task->id]);
        }

        return back()->with('success', 'Task action recorded.');
    }
}
