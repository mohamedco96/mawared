<?php

namespace App\Policies;

use App\Models\User;
use App\Models\TreasuryTransaction;
use Illuminate\Auth\Access\HandlesAuthorization;

class TreasuryTransactionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_treasury::transaction');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TreasuryTransaction $treasuryTransaction): bool
    {
        return $user->can('view_treasury::transaction');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_treasury::transaction');
    }

    /**
     * Determine whether the user can update the model.
     * Treasury transactions should never be updated for audit trail.
     */
    public function update(User $user, TreasuryTransaction $treasuryTransaction): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     * Treasury transactions should never be deleted for audit trail.
     */
    public function delete(User $user, TreasuryTransaction $treasuryTransaction): bool
    {
        return false;
    }

    /**
     * Determine whether the user can bulk delete.
     * Treasury transactions should never be deleted for audit trail.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete.
     * Treasury transactions should never be deleted for audit trail.
     */
    public function forceDelete(User $user, TreasuryTransaction $treasuryTransaction): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently bulk delete.
     * Treasury transactions should never be deleted for audit trail.
     */
    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore.
     * Treasury transactions should never be deleted for audit trail.
     */
    public function restore(User $user, TreasuryTransaction $treasuryTransaction): bool
    {
        return false;
    }

    /**
     * Determine whether the user can bulk restore.
     * Treasury transactions should never be deleted for audit trail.
     */
    public function restoreAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, TreasuryTransaction $treasuryTransaction): bool
    {
        return $user->can('replicate_treasury::transaction');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_treasury::transaction');
    }
}
