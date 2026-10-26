<?php

namespace App\Console\Commands;

use App\Jobs\SyncDeviceEventsJob;
use App\Models\Device;
use Illuminate\Console\Command;

class SyncDevicesCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'devices:sync {device? : The id of a single device}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll the access control terminals for new access events';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deviceId = null;

        if ($this->argument('device')) {
            $device = Device::query()->find($this->argument('device'));

            if (! $device) {
                $this->error(__('messages.device_not_found'));

                return self::FAILURE;
            }

            $deviceId = $device->id;
        }

        dispatch_sync(new SyncDeviceEventsJob($deviceId));

        $this->info('Device sync completed.');

        return self::SUCCESS;
    }
}
