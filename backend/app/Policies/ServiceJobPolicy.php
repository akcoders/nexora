<?php

namespace App\Policies;

use App\Models\ServiceJob;
use App\Models\User;

class ServiceJobPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('service_jobs.read') || $user->hasRole('Technician');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ServiceJob $serviceJob): bool
    {
        return $serviceJob->assigned_to === $user->id
            || $user->can('service_jobs.assign')
            || $user->can('service_jobs.update');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('service_jobs.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ServiceJob $serviceJob): bool
    {
        return $user->can('service_jobs.update');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ServiceJob $serviceJob): bool
    {
        return $user->can('service_jobs.delete');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ServiceJob $serviceJob): bool
    {
        return $user->can('service_jobs.update');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ServiceJob $serviceJob): bool
    {
        return $user->can('service_jobs.delete');
    }
}
