<?php

namespace App\Observers;

use App\Modules\ProjectHandover\Models\InnovationLog;
use App\Modules\User\Models\User;
use App\Modules\Notification\Notifications\InnovationLogSubmitted;
use App\Modules\Notification\Notifications\InnovationLogVerified;
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
