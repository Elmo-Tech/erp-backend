<?php

namespace App\Services\Devices;

use App\Models\Attendance;
use App\Models\Device;
use App\Models\DeviceAccessEvent;
use App\Models\DeviceUser;
use App\Models\User;
use App\Services\HR\AttendanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Turns raw access control events into attendance records.
 *
 * The terminal reports every authentication attempt. All of them are kept in
 * `device_access_events` (audit trail + replay) and only authorised punches
 * of a mapped employee become attendance.
 *
 * Punch pairing is device driven: the first authorised punch of the day is
 * the clock-in, the second is the clock-out, anything after that is stored
 * but ignored for attendance purposes.
 */
class DeviceEventService
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
    ) {}

    /**
     * Normalise a raw device event into a flat structure.
     *
     * Handles both the polling payload (`AcsEvent` list items) and the
     * push payload (`JSON_EventNotificationAlert` wrapping an
     * `AccessControllerEvent`).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function normalize(array $payload): ?array
    {
        $event = $payload;

        // Push mode wraps the event inside a notification envelope.
        if (isset($payload['AccessControllerEvent']) && is_array($payload['AccessControllerEvent'])) {
            $event = $payload['AccessControllerEvent'] + ['dateTime' => $payload['dateTime'] ?? null];
        }

        $occurredAt = $this->resolveOccurredAt($event);

        if (! $occurredAt) {
            return null;
        }

        $employeeNo = $this->firstString($event, ['employeeNoString', 'employeeNo', 'userId']);
        $cardNo = $this->firstString($event, ['cardNo', 'cardNumber']);

        if ($employeeNo === null && $cardNo === null) {
            return null;
        }

        return [
            'external_id' => $this->resolveExternalId($event, $employeeNo, $cardNo, $occurredAt),
            'device_employee_no' => $employeeNo,
            'card_no' => $cardNo,
            'event_type' => $this->resolveEventType($event),
            'verify_mode' => $this->resolveVerifyMode($event, $cardNo),
            'door_no' => $this->resolveDoorNo($event),
            'is_authorized' => $this->isAuthorized($event),
            'occurred_at' => $occurredAt,
            'raw_payload' => $payload,
        ];
    }

    /**
     * Persist a normalised event and apply it to attendance.
     *
     * @param  array<string, mixed>  $event
     */
    public function record(Device $device, array $event): ?DeviceAccessEvent
    {
        $mapping = $this->resolveMapping($device, $event);

        $user = $mapping?->user;

        $accessEvent = DeviceAccessEvent::query()->firstOrCreate(
            [
                'device_id' => $device->id,
                'external_id' => $event['external_id'],
            ],
            [
                'user_id' => $user?->id,
                'device_employee_no' => $event['device_employee_no'],
                'event_type' => $event['event_type'],
                'verify_mode' => $event['verify_mode'],
                'card_no' => $event['card_no'],
                'door_no' => $event['door_no'],
                'is_authorized' => $event['is_authorized'],
                'occurred_at' => $event['occurred_at'],
                'raw_payload' => $event['raw_payload'] ?? null,
            ]
        );

        // Already known event - never apply it twice.
        if (! $accessEvent->wasRecentlyCreated) {
            return $accessEvent;
        }

        if ($user && ! $accessEvent->user_id) {
            $accessEvent->update(['user_id' => $user->id]);
        }

        if ($event['is_authorized'] && $user) {
            $this->syncAttendance($device, $user, $event['occurred_at']);
        }

        return $accessEvent;
    }

    /**
     * Find the ERP user behind a device event.
     *
     * @param  array<string, mixed>  $event
     */
    private function resolveMapping(Device $device, array $event): ?DeviceUser
    {
        if (! empty($event['device_employee_no'])) {
            $mapping = DeviceUser::query()
                ->where('device_id', $device->id)
                ->where('device_employee_no', $event['device_employee_no'])
                ->first();

            if ($mapping) {
                return $mapping;
            }
        }

        if (! empty($event['card_no'])) {
            return DeviceUser::query()
                ->where('device_id', $device->id)
                ->where('card_no', $event['card_no'])
                ->first();
        }

        return null;
    }

    /**
     * Recompute the attendance of a day from the punches recorded for it.
     *
     * Recomputing (instead of incrementally patching) keeps the result
     * correct even when events arrive out of order or the same day is
     * reported by more than one device.
     */
    private function syncAttendance(Device $device, User $user, Carbon $occurredAt): void
    {
        $date = $occurredAt->toDateString();

        $punches = DeviceAccessEvent::query()
            ->where('user_id', $user->id)
            ->where('is_authorized', true)
            ->whereDate('occurred_at', $date)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        if ($punches->isEmpty()) {
            return;
        }

        $firstPunch = $punches->first();
        $secondPunch = $punches->count() > 1 ? $punches->get(1) : null;

        $attendance = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $date)
            ->first();

        if (! $attendance) {
            $data = [
                'userId' => $user->id,
                'date' => $date,
                'clockIn' => $firstPunch->occurred_at->format('Y-m-d\TH:i:s'),
            ];

            if ($secondPunch) {
                $data['clockOut'] = $secondPunch->occurred_at->format('Y-m-d\TH:i:s');
            }

            $created = $this->attendanceService->createAttendance($data, $user);

            $created->update([
                'source' => Attendance::SOURCE_DEVICE,
                'device_id' => $device->id,
                'external_id' => $firstPunch->external_id,
            ]);

            return;
        }

        $update = [];

        // Keep the earliest known arrival (web app timer or device punch).
        $deviceOwnsClockIn = ! $attendance->clock_in
            || $firstPunch->occurred_at->lte($attendance->clock_in);

        if ($deviceOwnsClockIn) {
            $update['clockIn'] = $firstPunch->occurred_at->format('Y-m-d\TH:i:s');
        }

        // Only the device can close the day when the web app did not.
        $deviceOwnsClockOut = $secondPunch && ! $attendance->clock_out;

        if ($deviceOwnsClockOut) {
            $update['clockOut'] = $secondPunch->occurred_at->format('Y-m-d\TH:i:s');
        }

        if ($update !== []) {
            $this->attendanceService->updateAttendance($attendance, $update, $user);
        }

        if ($deviceOwnsClockIn || $deviceOwnsClockOut) {
            $attendance->update([
                'source' => Attendance::SOURCE_DEVICE,
                'device_id' => $attendance->device_id ?? $device->id,
                'external_id' => $deviceOwnsClockIn
                    ? $firstPunch->external_id
                    : ($attendance->external_id ?? $firstPunch->external_id),
            ]);
        }
    }

    /**
     * Resolve the moment the event happened.
     *
     * @param  array<string, mixed>  $event
     */
    private function resolveOccurredAt(array $event): ?Carbon
    {
        $raw = $this->firstString($event, ['dateTime', 'time', 'eventTime', 'startTime']);

        if (! $raw) {
            return null;
        }

        try {
            // The device reports wall-clock time; we keep it as-is so the
            // stored value matches what the employee saw on the terminal.
            return Carbon::parse($raw);
        } catch (\Throwable) {
            Log::warning('Could not parse device event time', ['raw' => $raw]);

            return null;
        }
    }

    /**
     * Build a stable identifier so the same event is never applied twice.
     *
     * @param  array<string, mixed>  $event
     */
    private function resolveExternalId(
        array $event,
        ?string $employeeNo,
        ?string $cardNo,
        Carbon $occurredAt
    ): string {
        $serial = $this->firstString($event, ['serialNo', 'eventID', 'eventId']);

        if ($serial) {
            return $serial;
        }

        return 'h'.hash('sha1', implode('|', [
            $employeeNo ?? '',
            $cardNo ?? '',
            $occurredAt->format('Y-m-d H:i:s'),
            (string) ($event['major'] ?? ''),
            (string) ($event['minor'] ?? ''),
        ]));
    }

    /**
     * Resolve a human readable event type.
     *
     * @param  array<string, mixed>  $event
     */
    private function resolveEventType(array $event): ?string
    {
        $type = $this->firstString($event, ['eventType', 'eventDescription', 'eventKind']);

        if (! $type) {
            $major = $event['major'] ?? null;
            $minor = $event['minor'] ?? null;

            if ($major === null && $minor === null) {
                return null;
            }

            return sprintf('acs:%s:%s', (string) $major, (string) $minor);
        }

        return strtolower($type);
    }

    /**
     * Resolve how the employee was verified (fingerprint, card, face...).
     *
     * @param  array<string, mixed>  $event
     */
    private function resolveVerifyMode(array $event, ?string $cardNo): ?string
    {
        $mode = $this->firstString($event, ['verifyMode', 'verifyType', 'userVerifyMode']);

        if ($mode) {
            return strtolower($mode);
        }

        if (! empty($event['fingerPrintID']) || ! empty($event['fingerIndex'])) {
            return 'fingerprint';
        }

        if ($cardNo) {
            return 'card';
        }

        return null;
    }

    /**
     * Resolve the door number the event belongs to.
     *
     * @param  array<string, mixed>  $event
     */
    private function resolveDoorNo(array $event): ?int
    {
        $door = $event['doorNo'] ?? $event['doorID'] ?? null;

        return is_numeric($door) ? (int) $door : null;
    }

    /**
     * Determine whether the event represents an authorised access.
     *
     * The terminal exposes the outcome in different fields depending on the
     * firmware and the event source, so several signals are inspected.
     *
     * @param  array<string, mixed>  $event
     */
    private function isAuthorized(array $event): bool
    {
        $deniedMarkers = ['invalid', 'fail', 'denied', 'deny', 'refuse', 'unauthor', 'timeout'];

        foreach (['status', 'result', 'attendanceStatus', 'eventDescription'] as $key) {
            $value = $event[$key] ?? null;

            if (! is_string($value)) {
                continue;
            }

            $normalised = strtolower($value);

            foreach ($deniedMarkers as $marker) {
                if (str_contains($normalised, $marker)) {
                    return false;
                }
            }
        }

        $authorized = $event['authorized'] ?? $event['isAuthorized'] ?? null;

        if (is_bool($authorized)) {
            return $authorized;
        }

        $minor = $event['minor'] ?? null;

        if ($minor !== null) {
            $deniedMinors = array_map('intval', (array) config('devices.denied_minor_codes', []));

            if (in_array((int) $minor, $deniedMinors, true)) {
                return false;
            }
        }

        $statusValue = $event['statusValue'] ?? null;

        if ($statusValue !== null) {
            $authorizedStatuses = array_map('intval', (array) config('devices.authorized_status_values', []));

            if ($authorizedStatuses !== [] && in_array((int) $statusValue, $authorizedStatuses, true)) {
                return true;
            }
        }

        return true;
    }

    /**
     * Return the first non-empty string found under the given keys.
     *
     * @param  array<string, mixed>  $event
     * @param  list<string>  $keys
     */
    private function firstString(array $event, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $event[$key] ?? null;

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if (is_scalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
