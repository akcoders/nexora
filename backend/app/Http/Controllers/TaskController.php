<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $tasks = Task::with(['customer', 'assignee', 'creator'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->filled('type'), fn ($query) => $query->where('task_type', $request->string('type')))
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($query) => $query
                ->where('title', 'like', '%'.$request->string('search').'%')
                ->orWhere('task_no', 'like', '%'.$request->string('search').'%')))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 1 ELSE 2 END")
            ->orderBy('due_at')->paginate(20)->withQueryString();

        return view('tasks.index', [
            'tasks' => $tasks,
            'users' => User::where('status', 'active')->orderBy('name')->get(),
            'customers' => Customer::where('status', 'active')->orderBy('name')->get(),
            'pendingCount' => Task::where('status', 'pending')->count(),
            'closedCount' => Task::where('status', 'closed')->count(),
            'overdueCount' => Task::where('status', 'pending')->where('due_at', '<', now())->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'task_type' => ['required', Rule::in(['job', 'workflow'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'customer_id' => ['nullable', 'exists:customers,id'],
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
            $task = Task::create(array_merge($data, ['task_no' => 'TSK'.str_pad((string) $nextId, 6, '0', STR_PAD_LEFT), 'created_by' => $request->user()->id, 'status' => 'pending']));
            foreach ($observers as $observer) {
                $task->members()->create(['user_id' => $observer, 'role' => 'observer']);
            }
            $task->actions()->create(['user_id' => $request->user()->id, 'assigned_to' => $task->assigned_to, 'action' => 'created', 'remark' => 'Task created and assigned.']);

            return $task;
        });
        $task->assignee->notify(new TaskAssignedNotification($task));

        return redirect()->route('tasks.show', $task)->with('success', 'Task created successfully.');
    }

    public function show(Task $task): View
    {
        return view('tasks.show', [
            'task' => $task->load(['customer', 'assignee', 'creator', 'actions.user', 'actions.assignee', 'members.user']),
            'users' => User::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function action(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['close', 'assign'])],
            'remark' => ['required', 'string', 'max:3000'],
            'assigned_to' => ['required_if:action,assign', 'nullable', 'exists:users,id'],
        ]);

        DB::transaction(function () use ($request, $task, $data) {
            $task->actions()->create(['user_id' => $request->user()->id, 'assigned_to' => $data['assigned_to'] ?? null, 'action' => $data['action'], 'remark' => $data['remark']]);
            $task->update($data['action'] === 'close'
                ? ['status' => 'closed', 'closed_at' => now()]
                : ['status' => 'pending', 'assigned_to' => $data['assigned_to'], 'closed_at' => null]);
        });
        if ($data['action'] === 'assign') {
            $task->fresh()->assignee->notify(new TaskAssignedNotification($task->fresh()));
        }

        return back()->with('success', 'Task action recorded.');
    }
}
