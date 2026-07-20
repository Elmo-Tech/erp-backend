<?php

namespace App\Policies;

use App\Modules\Calendar\Models\CalendarEvent;
use App\Modules\Settings\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CalendarEventPolicy
{
    use HandlesAuthorization;

    public function view(User $user, CalendarEvent $event): bool
    {
        return $this->owns($user, $event);
    }

    public function update(User $user, CalendarEvent $event): bool
    {
        return $this->owns($user, $event);
    }

    public function delete(User $user, CalendarEvent $event): bool
    {
        return $this->owns($user, $event);
    }

    private function owns(User $user, CalendarEvent $event): bool
    {
        return $event->user_id === $user->id;
    }
}
