<?php

namespace App\Observers;

use App\Models\InnovationLog;
use App\Models\User;
use App\Notifications\InnovationLogSubmitted;
use App\Notifications\InnovationLogVerified;
use Illuminate\Support\Facades\Notification;

class InnovationLogObserver
{
    /**
     * Handle the InnovationLog "created" event.
     */
    public function created(InnovationLog $innovationLog): void
    {
        // Notify all admins when a new innovation log is submitted
        $admins = User::role(['admin', 'super-admin'])->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new InnovationLogSubmitted($innovationLog));
        }
    }

    /**
     * Handle the InnovationLog "updated" event.
     */
    public function updated(InnovationLog $innovationLog): void
    {
        // Check if verification status changed
        if ($innovationLog->isDirty('is_verified') && $innovationLog->is_verified) {
            // Notify the user that their innovation log was verified
            $innovationLog->user->notify(new InnovationLogVerified($innovationLog));
        }
    }
}
