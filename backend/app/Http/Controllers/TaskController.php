<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Task;
use App\Models\TaskMember;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Services\OneSignalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public const PRIORITIES = ['normal', 'high', 'very_high'];

    /** Recently closed items shown in the board's Closed lane. */
    private const BOARD_CLOSED_LIMIT = 40;

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
        abort_unless($request->user()->can('tasks.create') || $request->user()->can('tasks.assign'), 403);
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
            'priority' => ['required', Rule::in(self::PRIORITIES)],
            'due_at' => ['nullable', 'date'],
            'assigned_to' => ['required', 'exists:users,id'],
            'observers' => ['nullable', 'array'],
            'observers.*' => ['exists:users,id'],
            'return_to' => ['nullable', Rule::in(['board'])],
        ]);
        $returnTo = $data['return_to'] ?? null;
        unset($data['return_to']);

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

        if ($returnTo === 'board') {
            return redirect()->route('tasks.board', ['type' => $task->task_type])->with('success', $task->task_no.' created and added to the board.');
        }

        return redirect()->route('tasks.show', $task)->with('success', 'Task created successfully.');
    }

    public function board(Request $request): View|JsonResponse
    {
        if (! $request->expectsJson()) {
            return view('tasks.board', [
                'users' => User::where('status', 'active')->orderBy('name')->get(),
                'customers' => Customer::where('status', 'active')->orderBy('name')->get(),
            ]);
        }

        $user = $request->user();
        $relations = ['customer', 'assignee', 'latestAction.user'];
        $open = Task::with($relations)->withCount(['actions', 'members as observers_count' => fn ($query) => $query->where('role', 'observer')])
            ->whereIn('task_type', [Task::TYPE_WORKFLOW, Task::TYPE_TICKET])
            ->where('status', '!=', 'closed')
            ->orderByRaw("CASE WHEN priority = 'very_high' THEN 1 WHEN priority = 'high' THEN 2 ELSE 3 END")
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->get();
        $closed = Task::with($relations)->withCount(['actions', 'members as observers_count' => fn ($query) => $query->where('role', 'observer')])
            ->whereIn('task_type', [Task::TYPE_WORKFLOW, Task::TYPE_TICKET])
            ->where('status', 'closed')
            ->latest('closed_at')
            ->limit(self::BOARD_CLOSED_LIMIT)
            ->get();
        $watching = TaskMember::where('user_id', $user->id)->where('role', 'observer')->pluck('task_id')->flip();

        return response()->json([
            'tasks' => $open->concat($closed)->map(fn (Task $task) => $this->boardCard($task, $user, $watching->has($task->id)))->values(),
            'users' => User::where('status', 'active')->orderBy('name')->get(['id', 'name', 'designation'])
                ->map(fn (User $member) => ['id' => $member->id, 'name' => $member->name, 'designation' => $member->designation]),
            'me' => $user->id,
            'closed_limit' => self::BOARD_CLOSED_LIMIT,
            'can_reopen' => $user->can('tasks.update'),
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function priority(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canReprioritise($task, $user), 403);
        abort_if($task->status === 'closed', 422, 'Closed items cannot be re-prioritised.');
        $data = $request->validate([
            'priority' => ['required', Rule::in(self::PRIORITIES)],
            'remark' => ['nullable', 'string', 'max:3000'],
        ]);

        if ($data['priority'] !== $task->priority) {
            DB::transaction(function () use ($task, $data, $user) {
                $summary = 'Priority changed from '.str($task->priority)->headline().' to '.str($data['priority'])->headline().'.';
                $task->actions()->create(['user_id' => $user->id, 'action' => 'priority', 'remark' => trim($summary.' '.($data['remark'] ?? ''))]);
                $task->update(['priority' => $data['priority']]);
            });
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $task->task_no.' priority updated.', 'task' => $this->freshCard($task, $user)]);
        }

        return back()->with('success', 'Priority updated.');
    }

    public function show(Task $task): View
    {
        return view('tasks.show', [
            'task' => $task->load(['customer', 'assignee', 'creator', 'actions.user', 'actions.assignee', 'members.user']),
            'users' => User::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function action(Request $request, Task $task, OneSignalService $oneSignal): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($this->canAct($task, $user), 403);
        if ($task->status === 'closed' && ! $user->can('tasks.update')) {
            abort_if($request->expectsJson(), 422, 'This task is already closed.');

            return back()->with('error', 'This task is already closed.');
        }
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

        if ($request->expectsJson()) {
            $message = $data['action'] === 'close' ? $task->task_no.' closed.' : $task->task_no.' assigned to '.$task->fresh()->assignee->name.'.';

            return response()->json(['message' => $message, 'task' => $this->freshCard($task, $user)]);
        }

        return back()->with('success', 'Task action recorded.');
    }

    private function canAct(Task $task, User $user): bool
    {
        return $user->can('tasks.update') || $user->can('tasks.assign') || $task->assigned_to === $user->id || $task->created_by === $user->id;
    }

    private function canReprioritise(Task $task, User $user): bool
    {
        return $user->can('tasks.update') || $user->can('tasks.assign') || $task->created_by === $user->id;
    }

    private function freshCard(Task $task, User $user): array
    {
        $task = $task->fresh(['customer', 'assignee', 'latestAction.user'])->loadCount(['actions', 'members as observers_count' => fn ($query) => $query->where('role', 'observer')]);

        return $this->boardCard($task, $user, $task->members()->where('user_id', $user->id)->where('role', 'observer')->exists());
    }

    /** Compact card payload consumed by the Kanban board script. */
    private function boardCard(Task $task, User $user, bool $watching): array
    {
        $isOpen = $task->status !== 'closed';

        return [
            'id' => $task->id,
            'task_no' => $task->task_no,
            'title' => $task->title,
            'type' => $task->task_type,
            'status' => $isOpen ? 'pending' : 'closed',
            'priority' => $task->priority,
            'category' => $task->category,
            'customer' => $task->customer?->name,
            'assignee' => $task->assignee ? ['id' => $task->assignee->id, 'name' => $task->assignee->name, 'designation' => $task->assignee->designation] : null,
            'due_at' => $task->due_at?->toIso8601String(),
            'due_label' => $task->due_at?->format('d M, h:i A'),
            'overdue' => $isOpen && $task->due_at?->isPast(),
            'closed_at' => $task->closed_at?->toIso8601String(),
            'actions_count' => (int) $task->actions_count,
            'observers_count' => (int) ($task->observers_count ?? 0),
            'latest' => $task->latestAction ? ['action' => $task->latestAction->action, 'by' => $task->latestAction->user?->name, 'remark' => str($task->latestAction->remark)->limit(110)->toString(), 'at' => $task->latestAction->created_at->diffForHumans()] : null,
            'mine' => $task->assigned_to === $user->id,
            'created_by_me' => $task->created_by === $user->id,
            'watching' => $watching,
            'can_act' => $this->canAct($task, $user) && ($isOpen || $user->can('tasks.update')),
            'can_prioritise' => $isOpen && $this->canReprioritise($task, $user),
            'url' => route('tasks.show', $task),
            'action_url' => route('tasks.action', $task),
            'priority_url' => route('tasks.priority', $task),
        ];
    }
}
