<?php

namespace App\Policies;

use App\Models\User;
use App\Models\InvoicePayment;

class InvoicePaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_invoice::payment');
    }

    public function view(User $user, InvoicePayment $invoicePayment): bool
    {
        return $user->can('view_invoice::payment');
    }

    public function create(User $user): bool
    {
        return $user->can('create_invoice::payment');
    }

    public function update(User $user, InvoicePayment $invoicePayment): bool
    {
        return $user->can('update_invoice::payment');
    }

    public function delete(User $user, InvoicePayment $invoicePayment): bool
    {
        return $user->can('delete_invoice::payment');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_invoice::payment');
    }

    public function forceDelete(User $user, InvoicePayment $invoicePayment): bool
    {
        return $user->can('force_delete_invoice::payment');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_invoice::payment');
    }

    public function restore(User $user, InvoicePayment $invoicePayment): bool
    {
        return $user->can('restore_invoice::payment');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_invoice::payment');
    }

    public function replicate(User $user, InvoicePayment $invoicePayment): bool
    {
        return $user->can('replicate_invoice::payment');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_invoice::payment');
    }
}
