<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tasks')->whereIn('task_type', ['workflow', 'ticket'])->where('status', 'in_progress')->update(['status' => 'pending']);
        DB::table('tasks')->where('task_type', 'workflow')->whereNotNull('parent_task_id')
            ->whereNull('deleted_at')->orderBy('id')->chunkById(200, function ($tasks): void {
                foreach ($tasks as $task) {
                    $workers = DB::table('task_actions')->where('task_id', $task->id)->whereNull('related_task_id')->whereNotNull('assigned_to')->pluck('assigned_to')
                        ->merge(DB::table('task_members')->where('task_id', $task->id)->whereIn('role', ['assignee', 'previous_assignee'])->pluck('user_id'))
                        ->push($task->assigned_to)->unique()->reject(fn ($userId): bool => $userId === $task->created_by);
                    $parentId = $task->parent_task_id;
                    $visited = [];
                    while ($parentId && ! in_array($parentId, $visited)) {
                        $visited[] = $parentId;
                        $parent = DB::table('tasks')->whereNull('deleted_at')->find($parentId);
                        if (! $parent) {
                            break;
                        }
                        foreach ($workers as $worker) {
                            if ($parent->created_by === $worker || ($task->status === 'closed' && $parent->assigned_to === $worker)) {
                                continue;
                            }
                            DB::table('task_members')->insertOrIgnore([
                                'task_id' => $parent->id, 'user_id' => $worker, 'role' => 'subtask_only',
                                'created_at' => now(), 'updated_at' => now(),
                            ]);
                        }
                        $parentId = $parent->parent_task_id;
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('task_members')->where('role', 'subtask_only')->delete();
        // Pending records cannot be distinguished from previously in-progress records.
    }
};
