<?php

namespace App\Console\Commands;

use App\Services\Sales\TimeTrackingService;
use Illuminate\Console\Command;

class ExpireStaleTimersCommand extends Command
{
    protected $signature = 'time-entries:expire-stale';

    protected $description = 'Stop active timers whose browser heartbeat has expired';

    public function handle(TimeTrackingService $timeTrackingService): int
    {
        $expiredCount = $timeTrackingService->expireStaleTimers();
        $this->info("Expired {$expiredCount} stale timer(s).");

        return self::SUCCESS;
    }
}
