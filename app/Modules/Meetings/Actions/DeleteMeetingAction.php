<?php

namespace App\Modules\Meetings\Actions;

use App\Modules\Meetings\Models\Meeting;

class DeleteMeetingAction
{
    public function execute(Meeting $meeting): void
    {
        $meeting->delete();
    }
}
