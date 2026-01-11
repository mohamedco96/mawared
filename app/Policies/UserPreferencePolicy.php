<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserPreference;

class UserPreferencePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // Users can see their own preferences
    }

    public function view(User $user, UserPreference $userPreference): bool
    {
        // Users can only view their own preferences
        return $user->id === $userPreference->user_id
            || $user->can('view_any_user::preference');
    }

    public function create(User $user): bool
    {
        return true; // Users can create their own preferences
    }

    public function update(User $user, UserPreference $userPreference): bool
    {
        // Users can only update their own preferences
        return $user->id === $userPreference->user_id
            || $user->can('update_any_user::preference');
    }

    public function delete(User $user, UserPreference $userPreference): bool
    {
        // Users can only delete their own preferences
        return $user->id === $userPreference->user_id
            || $user->can('delete_any_user::preference');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_user::preference');
    }

    public function forceDelete(User $user, UserPreference $userPreference): bool
    {
        return $user->id === $userPreference->user_id
            || $user->can('force_delete_any_user::preference');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_user::preference');
    }

    public function restore(User $user, UserPreference $userPreference): bool
    {
        return $user->id === $userPreference->user_id
            || $user->can('restore_any_user::preference');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_user::preference');
    }

    public function replicate(User $user, UserPreference $userPreference): bool
    {
        return $user->id === $userPreference->user_id
            || $user->can('replicate_any_user::preference');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_user::preference');
    }
}
