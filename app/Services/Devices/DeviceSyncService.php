<?php

namespace App\Services\Devices;

use App\Models\Device;
use App\Models\DeviceUser;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps the people stored on a terminal in sync with the ERP.
 *
 * The ERP is the source of truth: every employee that is linked to a device
 * is written to the terminal with a stable employee number. The terminal
 * then reports that number back on every event, which is how events are
 * mapped to ERP users.
 */
class DeviceSyncService
{
    public function __construct(
        private readonly HikvisionIsapiService $isapi,
    ) {}

    /**
     * Push a single employee to the terminal.
     */
    public function pushUser(Device $device, User $user): DeviceUser
    {
        $employeeNo = $this->employeeNoFor($user);

        $payload = [
            'employeeNo' => $employeeNo,
            'name' => $user->name,
            'userType' => 'normal',
            'deleteUser' => false,
            'Valid' => [
                'enable' => true,
                'beginTime' => Carbon::now()->startOfDay()->format('Y-m-d\TH:i:s'),
                'endTime' => Carbon::now()->addYears((int) config('devices.isapi.validity_years', 10))->format('Y-m-d\TH:i:s'),
                'timeType' => 'local',
            ],
            'belongGroup' => (string) config('devices.isapi.belong_group', '1'),
            'doorRight' => (string) config('devices.isapi.door_right', '1'),
            'userVerifyMode' => (string) config('devices.isapi.user_verify_mode', 'fpOrCard'),
            'checkUser' => false,
        ];

        $response = $this->isapi->upsertUser($device, $payload);

        $this->assertAccepted($response, "Could not provision {$user->name} on the device");

        $mapping = DeviceUser::query()->updateOrCreate(
            [
                'device_id' => $device->id,
                'user_id' => $user->id,
            ],
            [
                'device_employee_no' => $employeeNo,
                'last_synced_at' => Carbon::now(),
            ]
        );

        $mapping->update(['fingerprint_enrolled' => $this->hasFingerprint($device, $employeeNo)]);

        return $mapping->fresh();
    }

    /**
     * Push every employee that should be on the terminal.
     *
     * @return array{pushed: int, failed: int, errors: list<string>}
     */
    public function pushAll(Device $device, ?iterable $users = null): array
    {
        $users ??= User::query()->employees()->where('is_active', true)->orderBy('id')->get();

        $pushed = 0;
        $failed = 0;
        $errors = [];

        foreach ($users as $user) {
            try {
                $this->pushUser($device, $user);
                $pushed++;
            } catch (Throwable $exception) {
                $failed++;
                $errors[] = sprintf('%s: %s', $user->name, $exception->getMessage());
            }
        }

        return compact('pushed', 'failed', 'errors');
    }

    /**
     * Read the people currently stored on the terminal.
     *
     * @return list<array<string, mixed>>
     */
    public function pullUsers(Device $device): array
    {
        return $this->isapi->listUsers($device);
    }

    /**
     * Remove an employee from the terminal.
     */
    public function removeUser(Device $device, DeviceUser $mapping): void
    {
        $response = $this->isapi->deleteUser($device, $mapping->device_employee_no);

        $this->assertAccepted($response, 'Could not remove the user from the device');

        $mapping->delete();
    }

    /**
     * Download the fingerprint template of an employee from one terminal so
     * it can be enrolled on another one.
     *
     * @return array<string, mixed>|null
     */
    public function copyFingerprint(Device $source, Device $target, User $user): ?array
    {
        $mapping = DeviceUser::query()
            ->where('device_id', $source->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $mapping) {
            return null;
        }

        $templates = $this->isapi->listFingerprints($source, $mapping->device_employee_no);

        if ($templates === []) {
            return null;
        }

        $targetMapping = DeviceUser::query()->updateOrCreate(
            [
                'device_id' => $target->id,
                'user_id' => $user->id,
            ],
            [
                'device_employee_no' => $this->employeeNoFor($user),
                'last_synced_at' => Carbon::now(),
            ]
        );

        $last = null;

        foreach ($templates as $template) {
            $fingerData = $template['fingerData'] ?? null;

            if (! $fingerData) {
                continue;
            }

            $response = $this->isapi->setFingerprint($target, [
                'employeeNo' => $targetMapping->device_employee_no,
                'enableCardReader' => [1],
                'fingerPrintID' => (int) ($template['fingerPrintID'] ?? 1),
                'fingerType' => $template['fingerType'] ?? 'normalFP',
                'fingerData' => $fingerData,
            ]);

            $this->assertAccepted($response, 'Could not copy the fingerprint to the device');

            $last = $template;
        }

        if ($last) {
            $targetMapping->update(['fingerprint_enrolled' => true]);
        }

        return $last;
    }

    /**
     * Determine the employee number used on the terminal for a user.
     */
    public function employeeNoFor(User $user): string
    {
        $employeeNo = $user->employee_no;

        if (! $employeeNo && config('devices.employee_no.fallback_to_user_id', true)) {
            $employeeNo = (string) $user->id;
        }

        $prefix = (string) config('devices.employee_no.prefix', '');

        if ($prefix !== '' && $employeeNo !== null && ! str_starts_with($employeeNo, $prefix)) {
            $employeeNo = $prefix.$employeeNo;
        }

        return (string) ($employeeNo ?? $user->id);
    }

    /**
     * Check whether the terminal already holds a fingerprint for the person.
     */
    private function hasFingerprint(Device $device, string $employeeNo): bool
    {
        try {
            return $this->isapi->listFingerprints($device, $employeeNo) !== [];
        } catch (Throwable $exception) {
            Log::warning('Could not read fingerprint templates', [
                'device_id' => $device->id,
                'employee_no' => $employeeNo,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * ISAPI answers with a status code inside the payload - a HTTP 200 does
     * not mean the operation succeeded.
     *
     * @param  array<string, mixed>  $response
     */
    private function assertAccepted(array $response, string $message): void
    {
        $status = $response['statusCode'] ?? null;

        if ($status === null) {
            return;
        }

        if ((int) $status === 1) {
            return;
        }

        throw new \RuntimeException($response['statusString'] ?? $message);
    }
}
