<?php

namespace App\Jobs;

use App\Models\Device;
use App\Services\Devices\DevicePollingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Poll every active device for new access control events.
 */
class SyncDeviceEventsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly ?int $deviceId = null) {}

    /**
     * Execute the job.
     */
    public function handle(DevicePollingService $polling): void
    {
        $devices = Device::query()
            ->where('is_active', true)
            ->where('auto_sync', true)
            ->when($this->deviceId !== null, fn ($query) => $query->whereKey($this->deviceId))
            ->get();

        foreach ($devices as $device) {
            try {
                $polling->poll($device);
            } catch (Throwable $exception) {
                // A single unreachable device must not stop the whole run.
                Log::error('Device sync failed', [
                    'device_id' => $device->id,
                    'name' => $device->name,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }
}
