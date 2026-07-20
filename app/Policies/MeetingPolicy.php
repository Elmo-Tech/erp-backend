<?php

namespace App\Policies;

use App\Modules\Meetings\Models\Meeting;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Gate;

class MeetingPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Meeting $meeting): bool
    {
        return $this->isParticipant($user, $meeting)
            || $this->canAccessProjectSubject($user, $meeting, 'view');
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $this->canMutate($user, $meeting);
    }

    public function complete(User $user, Meeting $meeting): bool
    {
        return $this->canMutate($user, $meeting);
    }

    public function cancel(User $user, Meeting $meeting): bool
    {
        return $this->canMutate($user, $meeting);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $this->canMutate($user, $meeting);
    }

    private function canMutate(User $user, Meeting $meeting): bool
    {
        return $this->isParticipant($user, $meeting)
            || $this->canAccessProjectSubject($user, $meeting, 'update');
    }

    private function isParticipant(User $user, Meeting $meeting): bool
    {
        return $meeting->created_by === $user->id
            || $meeting->assigned_to === $user->id
            || collect((array) $meeting->internal_attendees)->map(fn ($id): int => (int) $id)->contains($user->id);
    }

    private function canAccessProjectSubject(User $user, Meeting $meeting, string $ability): bool
    {
        $subject = $meeting->model;

        return $subject instanceof Project
            && Gate::forUser($user)->allows($ability, $subject);
    }
}
