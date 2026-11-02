<?php

namespace App\Policies;

use App\Models\Complaint;
use App\Models\ComplaintVisit;
use App\Models\User;
use App\Support\RecordVisibility;

class ComplaintPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['complaints.read', 'complaints.view_own']);
    }

    public function view(User $user, Complaint $complaint): bool
    {
        return RecordVisibility::allows($user, $complaint, 'complaints')
            || ($user->can('complaints.view_own') && $complaint->visits()->where(fn ($query) => $query
                ->where('technician_id', $user->id)->orWhere('assistant_one_id', $user->id)->orWhere('assistant_two_id', $user->id))->exists());
    }

    public function create(User $user): bool
    {
        return $user->can('complaints.create');
    }

    public function update(User $user, Complaint $complaint): bool
    {
        return $user->can('complaints.update') && $user->can('complaints.read') && ! $complaint->isClosed();
    }

    public function assign(User $user, Complaint $complaint): bool
    {
        return $user->can('complaints.assign') && ! $complaint->isClosed();
    }

    /** Office users with update access, or the technician team on the visit. */
    public function report(User $user, Complaint $complaint, ComplaintVisit $visit): bool
    {
        if (! $user->can('complaints.update') || $visit->complaint_id !== $complaint->id || $visit->status === 'cancelled' || $complaint->isClosed()) {
            return false;
        }

        return $user->can('complaints.read') || in_array($user->id, array_filter([$visit->technician_id, $visit->assistant_one_id, $visit->assistant_two_id]), true);
    }

    public function close(User $user, Complaint $complaint): bool
    {
        return $user->can('complaints.approve') && ! $complaint->isClosed();
    }

    public function reopen(User $user, Complaint $complaint): bool
    {
        return $user->can('complaints.approve') && in_array($complaint->status, ['closed', 'cancelled', 'completed'], true);
    }
}
