<?php

namespace App\Policies;

use App\Models\Issue;
use App\Models\User;

class IssuePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(?User $user): bool
    {
        return $user && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, Issue $issue): bool
    {
        // Anyone can view public issues
        // Admins can view all issues
        // Users can view their own issues
        return $user?->hasRole('admin') || 
               $issue->user_id === $user?->id ||
               true; // Public viewing allowed
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(?User $user): bool
    {
        // Anyone can create issues (including anonymous users)
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(?User $user, Issue $issue): bool
    {
        return $user && $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(?User $user, Issue $issue): bool
    {
        return $user && $user->hasRole('admin');
    }
}
