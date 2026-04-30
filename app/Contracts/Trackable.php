<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;

interface Trackable
{
    /**
     * Get all time entries for this trackable entity.
     */
    public function timeEntries(): MorphMany;

    /**
     * Get the display name for this trackable entity.
     */
    public function getTrackableDisplayName(): string;
}
