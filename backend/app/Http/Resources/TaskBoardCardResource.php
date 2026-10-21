<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact Kanban card for a workflow or ticket. Expects the task to be loaded with
 * {@see Task::scopeWithBoardData()} so counts and the viewer's watch flag need no extra queries.
 *
 * @mixin Task
 */
class TaskBoardCardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isOpen = ! $this->isClosed();

        return [
            'id' => $this->id,
            'task_no' => $this->task_no,
            'title' => $this->title,
            'type' => $this->task_type,
            'status' => $isOpen ? 'pending' : Task::STATUS_CLOSED,
            'priority' => $this->priority,
            'category' => $this->category,
            'customer' => $this->customer?->name,
            'assignee' => $this->assignee ? ['id' => $this->assignee->id, 'name' => $this->assignee->name, 'designation' => $this->assignee->designation] : null,
            'due_at' => $this->due_at?->toIso8601String(),
            'due_label' => $this->due_at?->format('d M, h:i A'),
            'overdue' => $this->isOverdue(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'actions_count' => (int) $this->actions_count,
            'observers_count' => (int) $this->observers_count,
            'latest' => $this->latestAction ? [
                'action' => $this->latestAction->action,
                'by' => $this->latestAction->user?->name,
                'remark' => str($this->latestAction->remark)->limit(110)->toString(),
                'at' => $this->latestAction->created_at->diffForHumans(),
            ] : null,
            'mine' => $this->assigned_to === $user->id,
            'created_by_me' => $this->created_by === $user->id,
            'watching' => (bool) $this->watched_by_viewer,
            'can_act' => $user->can('act', $this->resource) && ($isOpen || $user->can('reopen', $this->resource)),
            'can_prioritise' => $isOpen && $user->can('reprioritise', $this->resource),
            'url' => route('tasks.show', $this->resource),
            'action_url' => route('tasks.action', $this->resource),
            'priority_url' => route('tasks.priority', $this->resource),
        ];
    }
}
