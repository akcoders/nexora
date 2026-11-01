<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskMember;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskCompletedNotification;
use App\Support\RecordVisibility;
use Illuminate\Support\Carbon;
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
                abort_unless(RecordVisibility::allows($by, $parent, 'tasks') && $by->can('act', $parent), 403);
                abort_unless($parent->task_type === Task::TYPE_WORKFLOW, 422, 'Subtasks belong to internal workflows only.');
                abort_if($parent->isClosed(), 422, 'Reopen the workflow before creating a subtask.');
            }
            $observers = $parent ? $parent->observers()->whereNotIn('user_id', $parent->subtaskOnlyMembers()->pluck('user_id'))->pluck('user_id')->all() : ($data['observers'] ?? []);
            unset($data['observers'], $data['return_to']);
            if ($data['task_type'] === Task::TYPE_WORKFLOW) {
                $data['category'] = 'Internal';
            }
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
                    if (! in_array($participant, $observers)) {
                        $task->members()->firstOrCreate(['user_id' => $participant, 'role' => 'participant']);
                    }
                }
                $ancestor = $parent;
                while ($ancestor) {
                    $ancestor->members()->firstOrCreate(['user_id' => $task->assigned_to, 'role' => 'assignee']);
                    $this->assign($ancestor, $task->assigned_to, restrictParents: false, restoreAccess: false);
                    $ancestor = $ancestor->parent;
                }
                $this->restrictParentAccess($task, $task->assigned_to);
            }
            $task->recordAction([
                'user_id' => $by->id, 'assigned_to' => $task->assigned_to, 'action' => 'created',
                'remark' => $parent
                    ? 'Responsible Person: '.$task->assignee->name.'. Priority: '.str($task->priority)->headline().'. Due: '.($task->due_at?->format('d M Y, h:i A') ?? 'Not set').'. Observers inherited: '.count($observers).'.'
                    : 'Task created and assigned.',
            ]);

            return $task;
        });
        $this->notifyAssigned($task);

        return $task;
    }

    /** @param array<string, mixed> $data */
    public function act(Task $task, array $data, User $by, bool $allowReopen = false): void
    {
        $stored = [];
        $updating = $data['action'] === 'update';
        $reassigning = $data['action'] === 'assign' || ($updating && ($data['reassign'] ?? false));
        $completing = $data['action'] === 'close' || ($updating && $data['status'] === Task::STATUS_CLOSED);
        $status = $completing ? Task::STATUS_CLOSED : 'pending';
        $action = $status === Task::STATUS_CLOSED ? 'close' : ($reassigning ? 'assign' : 'update');
        $returnedParents = [];
        try {
            DB::transaction(function () use ($task, $data, $by, $allowReopen, $action, $status, &$stored, &$returnedParents): void {
                $task = Task::lockForUpdate()->findOrFail($task->id);
                abort_unless(RecordVisibility::allows($by, $task, 'tasks') && $by->can('act', $task), 403);
                abort_if($task->isClosed() && (! $allowReopen || $action !== 'assign'), 422, 'This task is already closed.');
                if ($action === 'assign' && $task->isClosed() && $task->parent?->isClosed()) {
                    throw ValidationException::withMessages(['action' => 'Reopen the parent workflow before reopening this subtask.']);
                }
                if ($action === 'close') {
                    if ($task->subtasks()->open()->exists()) {
                        throw ValidationException::withMessages(['action' => 'Complete all subtasks before completing this workflow.']);
                    }
                    if ($task->attachment_required_on_completion && empty($data['attachments'])) {
                        throw ValidationException::withMessages(['attachments' => 'Attach at least one file before completing this workflow.']);
                    }
                }
                $followUp = $this->updateFollowUp($task, $data);
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
                    'user_id' => $by->id, 'action' => $action, 'remark' => $data['remark'],
                    'assigned_to' => $action === 'assign' ? $data['assigned_to'] : null,
                    'attachments' => $attachments ?: null,
                    ...$followUp,
                ]);
                if ($action === 'assign') {
                    $this->assign($task, $data['assigned_to']);
                }
                if ($action === 'close') {
                    $task->update(['status' => Task::STATUS_CLOSED, 'closed_at' => now()]);
                    if ($task->parent_task_id) {
                        $returnedParents = $this->returnToCreator($task, $by);
                    }
                } else {
                    $task->update(['status' => $status, 'closed_at' => null]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($stored);
            throw $exception;
        }
        $task->refresh();
        if ($action === 'assign') {
            $this->notifyAssigned($task);
        } elseif ($action === 'close') {
            $ids = $this->participantIds($task);
            $parent = $task->parent;
            while ($parent) {
                $ids = array_merge($ids, $this->participantIds($parent));
                $parent = $parent->parent;
            }
            foreach (User::whereIn('id', array_unique($ids))->get() as $participant) {
                $completion = new TaskCompletedNotification($task);
                $participant->notify($completion);
                $this->oneSignal->sendToUser($participant, $task->parent_task_id ? 'Subtask completed' : 'Task completed', $task->task_no.' · '.$task->title, [
                    'type' => $task->task_type.'_task', 'task_id' => $completion->targetTaskId($participant),
                ]);
            }
            foreach (Task::whereIn('id', $returnedParents)->get() as $parent) {
                $this->notifyAssigned($parent, 'Workflow returned to you');
            }
        }
    }

    /** @return list<int> */
    private function returnToCreator(Task $task, User $by): array
    {
        $task->members()->firstOrCreate(['user_id' => $task->assigned_to, 'role' => 'previous_assignee']);
        $task->members()->firstOrCreate(['user_id' => $task->created_by, 'role' => 'assignee']);
        $task->update(['assigned_to' => $task->created_by]);
        $returnedParents = [];
        $parentId = $task->parent_task_id;
        while ($parentId) {
            $parent = Task::lockForUpdate()->find($parentId);
            if (! $parent) {
                break;
            }
            if (! $parent->isClosed()) {
                $previousOwner = $parent->assigned_to;
                $this->assign($parent, $task->created_by, restrictParents: false);
                if ($previousOwner !== $task->created_by) {
                    $returnedParents[] = $parent->id;
                }
            }
            $parentId = $parent->parent_task_id;
        }
        $task->recordAction([
            'user_id' => $by->id, 'action' => 'returned', 'assigned_to' => $task->created_by,
            'remark' => 'Subtask completed. Ownership returned to '.$task->creator->name.', who created the subtask.',
        ]);

        return $returnedParents;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function updateFollowUp(Task $task, array $data): array
    {
        if (! array_key_exists('next_follow_up_at', $data)) {
            return [];
        }
        $next = filled($data['next_follow_up_at']) ? Carbon::parse($data['next_follow_up_at'])->setTimezone(config('app.timezone')) : null;
        if (($next && $task->next_follow_up_at && $next->equalTo($task->next_follow_up_at)) || ($next === null && $task->next_follow_up_at === null)) {
            return [];
        }
        if ($next?->lt(today())) {
            throw ValidationException::withMessages(['next_follow_up_at' => 'Choose today or a later follow-up date.']);
        }
        $revision = $next && $task->due_at && $next->gt($task->due_at);
        $reason = trim($data['delay_reason'] ?? '');
        if ($revision && $reason === '') {
            throw ValidationException::withMessages(['delay_reason' => 'Please specify the reason for the delay.']);
        }
        $task->next_follow_up_at = $next;
        if ($revision) {
            $task->revised_due_at = $next;
        }
        $task->save();

        return [
            'next_follow_up_at' => $next,
            'revised_due_at' => $revision ? $next : null,
            'delay_reason' => $revision ? $reason : null,
        ];
    }

    private function assign(Task $task, int $owner, bool $restrictParents = true, bool $restoreAccess = true): void
    {
        $task->members()->firstOrCreate(['user_id' => $task->assigned_to, 'role' => 'previous_assignee']);
        $task->members()->firstOrCreate(['user_id' => $owner, 'role' => 'assignee']);
        if ($restoreAccess) {
            $task->subtaskOnlyMembers()->where('user_id', $owner)->delete();
        }
        if ($restrictParents) {
            $this->restrictParentAccess($task, $owner);
        }
        $task->update(['status' => 'pending', 'assigned_to' => $owner, 'closed_at' => null]);
    }

    private function restrictParentAccess(Task $task, int $owner): void
    {
        if ($owner === $task->created_by) {
            return;
        }
        $parent = $task->parent;
        while ($parent) {
            if ($parent->created_by !== $owner) {
                $parent->members()->firstOrCreate(['user_id' => $owner, 'role' => TaskMember::ROLE_SUBTASK_ONLY]);
            }
            $parent = $parent->parent;
        }
    }

    /** @return list<int> */
    private function participantIds(Task $task): array
    {
        return array_values(array_diff(array_unique(array_merge([$task->created_by, $task->assigned_to], $task->members()->pluck('user_id')->all())), $task->subtaskOnlyMembers()->pluck('user_id')->all()));
    }

    private function notifyAssigned(Task $task, ?string $title = null): void
    {
        $task->assignee->notify(new TaskAssignedNotification($task, $title));
        $this->oneSignal->sendToUser($task->assignee, $title ?? 'New '.($task->task_type === Task::TYPE_TICKET ? 'complaint' : 'workflow').' assigned', $task->title, ['type' => $task->task_type.'_task', 'task_id' => $task->id]);
    }
}
