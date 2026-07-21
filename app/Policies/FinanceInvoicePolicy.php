<?php

namespace App\Policies;

use App\Modules\Client\Models\Client;
use App\Modules\Finance\Models\FinanceInvoice;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FinanceInvoicePolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly ClientPolicy $clients,
        private readonly ProjectPolicy $projects,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->isInternal($user)
            && $user->canAny(['view_invoices', 'manage_invoices']);
    }

    public function view(User $user, FinanceInvoice $invoice): bool
    {
        return $this->viewAny($user) && $this->canAccessInvoice($user, $invoice);
    }

    public function create(User $user): bool
    {
        return $this->isInternal($user)
            && $user->canAny(['create_invoices', 'manage_invoices']);
    }

    public function update(User $user, FinanceInvoice $invoice): bool
    {
        return $this->isInternal($user)
            && $user->canAny(['edit_invoices', 'manage_invoices'])
            && $this->canAccessInvoice($user, $invoice);
    }

    public function delete(User $user, FinanceInvoice $invoice): bool
    {
        return $this->isInternal($user)
            && $user->canAny(['delete_invoices', 'manage_invoices'])
            && $this->canAccessInvoice($user, $invoice);
    }

    public function send(User $user, FinanceInvoice $invoice): bool
    {
        return $this->isInternal($user)
            && $user->canAny(['send_invoices', 'manage_invoices'])
            && $this->canAccessInvoice($user, $invoice);
    }

    public function canAccessTarget(User $user, Client $client, ?Project $project): bool
    {
        if (! $this->isInternal($user)) {
            return false;
        }

        if ($project !== null) {
            return $project->client_id === $client->id
                && $this->projects->view($user, $project);
        }

        return $this->clients->view($user, $client);
    }

    private function canAccessInvoice(User $user, FinanceInvoice $invoice): bool
    {
        $invoice->loadMissing(['client', 'project']);

        /** @var Client|null $client */
        $client = $invoice->client;
        /** @var Project|null $project */
        $project = $invoice->project;

        return $client !== null
            && $this->canAccessTarget($user, $client, $project);
    }

    private function isInternal(User $user): bool
    {
        return ! $user->can('access_client_portal');
    }
}
