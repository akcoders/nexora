<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskCompletedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class TaskWorkflow
{
    public function __construct(private OneSignalService $oneSignal) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $by, ?Task $parent = null): Task
    {
        $task = DB::transaction(function () use ($data, $by, $parent): Task {
            if ($parent) {
                $parent = Task::lockForUpdate()->findOrFail($parent->id);
                abort_unless($by->can('act', $parent), 403);
                abort_unless($parent->task_type === Task::TYPE_WORKFLOW, 422, 'Subtasks belong to internal workflows only.');
                abort_if($parent->isClosed(), 422, 'Reopen the workflow before creating a subtask.');
            }
            $observers = $data['observers'] ?? [];
            unset($data['observers'], $data['return_to']);
            $prefix = $data['task_type'] === Task::TYPE_TICKET ? 'TKT' : 'WFL';
            $task = Task::create(array_merge($data, [
                'task_no' => $prefix.Str::ulid(),
                'customer_id' => $data['task_type'] === Task::TYPE_TICKET ? $data['customer_id'] : null,
                'parent_task_id' => $parent?->id,
                'created_by' => $by->id,
                'status' => 'pending',
            ]));
            $task->update(['task_no' => $prefix.str_pad((string) $task->id, 6, '0', STR_PAD_LEFT)]);
            foreach ($observers as $observer) {
                $task->members()->firstOrCreate(['user_id' => $observer, 'role' => 'observer']);
            }
            if ($parent) {
                foreach ($this->participantIds($parent) as $participant) {
                    $task->members()->firstOrCreate(['user_id' => $participant, 'role' => 'observer']);
                }
                $ancestor = $parent;
                while ($ancestor) {
                    $ancestor->members()->firstOrCreate(['user_id' => $task->assigned_to, 'role' => 'assignee']);
                    $this->assign($ancestor, $task->assigned_to);
                    $ancestor->actions()->create([
                        'user_id' => $by->id, 'assigned_to' => $task->assigned_to, 'action' => 'assign',
                        'related_task_id' => $task->id, 'remark' => 'Workflow assigned to the owner of subtask '.$task->task_no.'.',
                    ]);
                    $ancestor = $ancestor->parent;
                }
            }
            $task->recordAction([
                'user_id' => $by->id, 'assigned_to' => $task->assigned_to, 'action' => 'created',
                'remark' => $parent ? 'Subtask created and assigned under '.$parent->task_no.'.' : 'Task created and assigned.',
            ]);

            return $task;
        });
        $this->notifyAssigned($task);
        if ($parent && $parent->assigned_to !== $task->assigned_to) {
            $this->notifyAssigned($parent->fresh());
        }

        return $task;
    }

    /** @param array<string, mixed> $data */
    public function act(Task $task, array $data, User $by, bool $allowReopen = false): void
    {
        $stored = [];
        try {
            DB::transaction(function () use ($task, $data, $by, $allowReopen, &$stored): void {
                $task = Task::lockForUpdate()->findOrFail($task->id);
                abort_if($task->isClosed() && (! $allowReopen || $data['action'] !== 'assign'), 422, 'This task is already closed.');
                if ($data['action'] === 'assign' && $task->isClosed() && $task->parent?->isClosed()) {
                    throw ValidationException::withMessages(['action' => 'Reopen the parent workflow before reopening this subtask.']);
                }
                if ($data['action'] === 'close') {
                    if ($task->subtasks()->open()->exists()) {
                        throw ValidationException::withMessages(['action' => 'Complete all subtasks before completing this workflow.']);
                    }
                    if ($task->attachment_required_on_completion && empty($data['attachments'])) {
                        throw ValidationException::withMessages(['attachments' => 'Attach at least one file before completing this workflow.']);
                    }
                }
                $attachments = [];
                foreach ($data['attachments'] ?? [] as $file) {
                    $path = $file->store('task-attachments/'.$task->id, 'local');
                    if (! is_string($path)) {
                        throw new \RuntimeException('Unable to store the attachment. Please try again.');
                    }
                    $stored[] = $path;
                    $attachments[] = ['path' => $path, 'name' => $file->getClientOriginalName()];
                }
                $task->recordAction([
                    'user_id' => $by->id, 'action' => $data['action'], 'remark' => $data['remark'],
                    'assigned_to' => $data['action'] === 'assign' ? $data['assigned_to'] : null,
                    'attachments' => $attachments ?: null,
                ]);
                if ($data['action'] === 'assign') {
                    $this->assign($task, $data['assigned_to']);
                } else {
                    $task->update(['status' => Task::STATUS_CLOSED, 'closed_at' => now()]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($stored);
            throw $exception;
        }
        $task->refresh();
        if ($data['action'] === 'assign') {
            $this->notifyAssigned($task);
        } else {
            $ids = $this->participantIds($task);
            $parent = $task->parent;
            while ($parent) {
                $ids = array_merge($ids, $this->participantIds($parent));
                $parent = $parent->parent;
            }
            foreach (User::whereIn('id', array_unique($ids))->get() as $participant) {
                $participant->notify(new TaskCompletedNotification($task));
                $this->oneSignal->sendToUser($participant, $task->parent_task_id ? 'Subtask completed' : 'Task completed', $task->task_no.' · '.$task->title, [
                    'type' => $task->task_type.'_task', 'task_id' => $task->parent_task_id ?? $task->id,
                ]);
            }
        }
    }

    public function changeDueDate(Task $task, ?string $dueAt, string $reason, User $by): void
    {
        DB::transaction(function () use ($task, $dueAt, $reason, $by): void {
            $task = Task::lockForUpdate()->findOrFail($task->id);
            abort_if($task->isClosed(), 422, 'Closed items cannot be rescheduled.');
            $previous = $task->due_at?->format('d M Y, h:i A') ?? 'No due date';
            $task->fill(['due_at' => $dueAt]);
            if (! $task->isDirty('due_at')) {
                return;
            }
            $next = $task->due_at?->format('d M Y, h:i A') ?? 'No due date';
            $task->save();
            $task->recordAction(['user_id' => $by->id, 'action' => 'due_date', 'remark' => 'Due date changed from '.$previous.' to '.$next.'. Reason: '.$reason]);
        });
    }

    private function assign(Task $task, int $owner): void
    {
        $task->members()->firstOrCreate(['user_id' => $task->assigned_to, 'role' => 'previous_assignee']);
        $task->members()->firstOrCreate(['user_id' => $owner, 'role' => 'assignee']);
        $parent = $task->parent;
        while ($parent) {
            $parent->members()->firstOrCreate(['user_id' => $owner, 'role' => 'observer']);
            $parent = $parent->parent;
        }
        $task->update(['status' => 'pending', 'assigned_to' => $owner, 'closed_at' => null]);
    }

    /** @return list<int> */
    private function participantIds(Task $task): array
    {
        return array_unique(array_merge([$task->created_by, $task->assigned_to], $task->members()->pluck('user_id')->all()));
    }

    private function notifyAssigned(Task $task): void
    {
        $task->assignee->notify(new TaskAssignedNotification($task));
        $this->oneSignal->sendToUser($task->assignee, 'New '.($task->task_type === Task::TYPE_TICKET ? 'complaint' : 'workflow').' assigned', $task->title, ['type' => $task->task_type.'_task', 'task_id' => $task->id]);
    }
}
