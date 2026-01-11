<?php

namespace App\Policies;

use App\Models\User;
use App\Models\GeneralSetting;

class GeneralSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_general::setting');
    }

    public function view(User $user, GeneralSetting $generalSetting): bool
    {
        return $user->can('view_general::setting');
    }

    public function create(User $user): bool
    {
        return $user->can('create_general::setting');
    }

    public function update(User $user, GeneralSetting $generalSetting): bool
    {
        return $user->can('update_general::setting');
    }

    public function delete(User $user, GeneralSetting $generalSetting): bool
    {
        return $user->can('delete_general::setting');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_general::setting');
    }

    public function forceDelete(User $user, GeneralSetting $generalSetting): bool
    {
        return $user->can('force_delete_general::setting');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_general::setting');
    }

    public function restore(User $user, GeneralSetting $generalSetting): bool
    {
        return $user->can('restore_general::setting');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_general::setting');
    }

    public function replicate(User $user, GeneralSetting $generalSetting): bool
    {
        return $user->can('replicate_general::setting');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_general::setting');
    }
}
