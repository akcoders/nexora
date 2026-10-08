<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(public Task $task) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New task assigned',
            'message' => $this->task->title,
            'task_id' => $this->task->id,
            'task_no' => $this->task->task_no,
            'type' => $this->task->task_type.'_task',
            'icon' => 'clipboard-list',
        ];
    }
}
