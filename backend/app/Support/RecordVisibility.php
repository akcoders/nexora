<?php

namespace App\Support;

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
        });
    }

    public static function allows(User $user, Model $record, string $module, bool $assignable = true): bool
    {
        return $user->can($module.'.read')
            || ($user->can($module.'.view_own') && ($record->created_by === $user->id
                || ($assignable && $record->assigned_to === $user->id)));
    }
}
