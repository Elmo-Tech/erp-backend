<?php

namespace App\Policies;

use App\Modules\Invoice\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvoicePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any invoices.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the invoice.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        if ($user->hasRole('client')) {
            return $invoice->project->client_id === $user->client?->id;
        }

        return $user->hasAnyRole(['admin', 'super-admin', 'employee']);
    }

    /**
     * Determine whether the user can create invoices.
     */
    public function create(User $user): bool
    {
        return ! $user->hasRole('client');
    }

    /**
     * Determine whether the user can update the invoice.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return ! $user->hasRole('client');
    }

    /**
     * Determine whether the user can delete the invoice.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        return ! $user->hasRole('client');
    }

    /**
     * Determine whether the user can pay the invoice.
     */
    public function pay(User $user, Invoice $invoice): bool
    {
        if (! $invoice->isPayable()) {
            return false;
        }

        if ($user->hasRole('client')) {
            return $invoice->project->client_id === $user->client?->id;
        }

        return $user->hasAnyRole(['admin', 'super-admin']);
    }
}
