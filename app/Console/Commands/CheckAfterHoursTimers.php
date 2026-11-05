<?php

namespace App\Console\Commands;

use App\Models\TimeEntry;
use App\Services\TimeTracking\AfterHoursConfirmation;
use Illuminate\Console\Command;

class CheckAfterHoursTimers extends Command
{
    protected $signature = 'time-entries:check-after-hours';
    protected $description = 'Request confirmation for timers running after scheduled work';

    public function handle(AfterHoursConfirmation $confirmation): int
    {
        TimeEntry::whereNull('end_time')->chunkById(100, function ($entries) use ($confirmation): void {
            foreach ($entries as $entry) $confirmation->process($entry->id);
        });
        return self::SUCCESS;
    }
}
