<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use App\Support\RecordVisibility;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(public Task $task) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function targetTaskId(User $viewer): int
    {
        $parent = $this->task->parent;

        return $parent && RecordVisibility::allows($viewer, $parent, 'tasks') ? $parent->id : $this->task->id;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->task->parent_task_id ? 'Subtask completed' : 'Task completed',
            'message' => $this->task->task_no.' · '.$this->task->title,
            'task_id' => $this->targetTaskId($notifiable),
            'subtask_id' => $this->task->parent_task_id ? $this->task->id : null,
            'type' => $this->task->task_type.'_task',
            'icon' => 'circle-check-big',
        ];
    }
}
