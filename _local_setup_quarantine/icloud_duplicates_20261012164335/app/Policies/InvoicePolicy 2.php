<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::VIEW_ALL_INVOICES)
            || $user->hasPermission(Permission::VIEW_OWN_INVOICES);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->id === $invoice->user_id) {
            return $user->hasPermission(Permission::VIEW_OWN_INVOICES);
        }

        return $user->hasPermission(Permission::VIEW_ALL_INVOICES);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::CREATE_INVOICE);
    }

    public function verify(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission(Permission::VERIFY_PAYMENT);
    }

    public function waive(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission(Permission::WAIVE_PAYMENT);
    }

    public function makePayment(User $user, Invoice $invoice): bool
    {
        return $user->hasPermission(Permission::MAKE_PAYMENT)
            && $user->id === $invoice->user_id
            && $invoice->status === 'pending';
    }
}
