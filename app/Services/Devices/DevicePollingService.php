<?php

namespace App\Services\Devices;

use App\Models\Device;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads the access control event log of a terminal and feeds it into the
 * attendance pipeline.
 *
 * This is the "pull" half of the integration. The "push" half is the
 * webhook endpoint, which calls the very same ingestion code.
 */
class DevicePollingService
{
    public function __construct(
        private readonly HikvisionIsapiService $isapi,
        private readonly DeviceEventService $events,
    ) {}

    /**
     * Poll a device and ingest every new event.
     *
     * @return array{ingested: int, skipped: int}
     */
    public function poll(Device $device): array
    {
        $since = $this->sinceTimestamp($device);
        $until = Carbon::now();

        $ingested = 0;
        $skipped = 0;

        try {
            $raw = $this->isapi->searchAccessEvents($device, [
                'startTime' => $since->format('Y-m-d\TH:i:s'),
                'endTime' => $until->format('Y-m-d\TH:i:s'),
                'picEnable' => false,
            ]);
        } catch (Throwable $exception) {
            $this->markFailed($device, $exception->getMessage());

            throw $exception;
        }

        foreach ($raw as $payload) {
            $event = $this->events->normalize($payload);

            if (! $event) {
                $skipped++;

                continue;
            }

            $this->events->record($device, $event);
            $ingested++;
        }

        $device->update([
            'last_seen_at' => Carbon::now(),
            'last_synced_at' => Carbon::now(),
            'last_event_at' => $until,
            'last_status' => 'ok',
            'last_error' => null,
        ]);

        Log::info('Device poll completed', [
            'device_id' => $device->id,
            'ingested' => $ingested,
            'skipped' => $skipped,
        ]);

        return compact('ingested', 'skipped');
    }

    /**
     * The window of events to request from the terminal.
     *
     * A small overlap is intentional: the terminal clock can drift a little
     * and we never want to miss an event that lands on the boundary.
     */
    private function sinceTimestamp(Device $device): Carbon
    {
        $lookback = (int) config('devices.polling.lookback_minutes', 60);

        if ($device->last_event_at) {
            return $device->last_event_at->copy()->subMinutes(min($lookback, 10));
        }

        return Carbon::now()->subMinutes($lookback);
    }

    /**
     * Record a failure on the device so the UI can surface it.
     */
    private function markFailed(Device $device, string $message): void
    {
        $device->update([
            'last_status' => 'error',
            'last_error' => mb_substr($message, 0, 500),
        ]);
    }
}
