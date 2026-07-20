<?php

namespace App\Policies;

use App\Modules\Client\Models\Client;
use App\Modules\Settings\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClientPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        if ($user->can('access_client_portal')) {
            return false;
        }

        return $this->canViewInternalClients($user);
    }

    public function view(User $user, Client $client): bool
    {
        if ($user->can('access_client_portal')) {
            return $client->user_id === $user->id;
        }

        return $this->canViewInternalClients($user);
    }

    public function viewActivity(User $user, Client $client): bool
    {
        return ! $user->can('access_client_portal')
            && $this->canViewInternalClients($user);
    }

    public function viewProjects(User $user, Client $client): bool
    {
        return $this->view($user, $client) && $user->can('view_projects');
    }

    public function viewMeetings(User $user, Client $client): bool
    {
        return $this->view($user, $client)
            && ($user->can('view_meetings') || $user->can('schedule_meetings'));
    }

    private function canViewInternalClients(User $user): bool
    {
        return $user->can('view_clients') || $user->can('manage_clients');
    }
}
