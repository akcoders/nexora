<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Determine whether the user can close the task or hand it to the next owner.
     */
    public function act(User $user, Task $task): bool
    {
        return $user->can('tasks.update')
            || $user->can('tasks.assign')
            || $task->assigned_to === $user->id
            || $task->created_by === $user->id;
    }

    /**
     * Determine whether the user can reopen a closed task by assigning it again.
     */
    public function reopen(User $user, Task $task): bool
    {
        return $user->can('tasks.update');
    }

    /**
     * Determine whether the user can change the task priority.
     */
    public function reprioritise(User $user, Task $task): bool
    {
        return $user->can('tasks.update')
            || $user->can('tasks.assign')
            || $task->created_by === $user->id;
    }
}
