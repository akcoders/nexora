<?php

namespace App\Support;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class RecordVisibility
{
    /** @param Builder<Model> $query */
    public static function scope(Builder $query, User $user, string $module, bool $assignable = true): Builder
    {
        if ($user->can($module.'.read')) {
            return $query;
        }

        abort_unless($user->can($module.'.view_own'), 403);

        return $query->where(function (Builder $query) use ($user, $assignable): void {
            $query->where('created_by', $user->id);
            if ($assignable) {
                $query->orWhere('assigned_to', $user->id);
            }
            if ($query->getModel() instanceof Task) {
                $query->orWhereHas('members', fn (Builder $members) => $members->where('user_id', $user->id));
                $query->orWhereHas('parent', fn (Builder $parent) => $parent->where('created_by', $user->id)
                    ->orWhere('assigned_to', $user->id)
                    ->orWhereHas('members', fn (Builder $members) => $members->where('user_id', $user->id)));
            }
        });
    }

    public static function allows(User $user, Model $record, string $module, bool $assignable = true): bool
    {
        if ($record instanceof Task && $user->can($module.'.view_own')) {
            return self::scope(Task::query(), $user, $module, $assignable)->whereKey($record->id)->exists();
        }

        return $user->can($module.'.read')
            || ($user->can($module.'.view_own') && ($record->created_by === $user->id
                || ($assignable && $record->assigned_to === $user->id)));
    }
}
