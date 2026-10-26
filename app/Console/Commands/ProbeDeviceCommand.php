<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\DeviceUser;
use App\Services\Devices\DevicePollingService;
use App\Services\Devices\DeviceSyncService;
use App\Services\Devices\HikvisionIsapiService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Probe a Hikvision terminal from the command line.
 *
 * This is the first thing to run when a device is installed: it verifies the
 * network route, the credentials and the ISAPI endpoints the ERP relies on.
 */
class ProbeDeviceCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'devices:probe
        {device? : The id or the IP address of the device}
        {--username= : Override the stored username}
        {--password= : Override the stored password}
        {--push-users : Push the ERP employees onto the device}
        {--poll : Read the access control event log}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the connection and the ISAPI endpoints of an access control terminal';

    /**
     * Execute the console command.
     */
    public function handle(
        HikvisionIsapiService $isapi,
        DeviceSyncService $sync,
        DevicePollingService $polling
    ): int {
        $device = $this->resolveDevice();

        if (! $device) {
            $this->error(__('messages.device_not_found'));

            return self::FAILURE;
        }

        $this->info("Device: {$device->name} ({$device->baseUrl()})");

        try {
            $info = $isapi->testConnection($device);
        } catch (Throwable $exception) {
            $this->error('Connection failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Connection OK');
        foreach ($info as $key => $value) {
            $this->line("  {$key}: ".($value === null ? '-' : $value));
        }

        try {
            $users = $sync->pullUsers($device);
            $this->info('People on the device: '.count($users));
        } catch (Throwable $exception) {
            $this->warn('Could not read the people list: '.$exception->getMessage());
        }

        if ($this->option('push-users')) {
            try {
                $result = $sync->pushAll($device);
                $this->info("Pushed {$result['pushed']} employees, {$result['failed']} failed");

                foreach ($result['errors'] as $error) {
                    $this->warn('  '.$error);
                }
            } catch (Throwable $exception) {
                $this->error('Could not push the employees: '.$exception->getMessage());
            }
        }

        if ($this->option('poll')) {
            try {
                $result = $polling->poll($device);
                $this->info("Events ingested: {$result['ingested']}, skipped: {$result['skipped']}");
            } catch (Throwable $exception) {
                $this->error('Could not read the event log: '.$exception->getMessage());
            }
        }

        $this->line('');
        $this->line('Linked employees:');
        DeviceUser::query()
            ->where('device_id', $device->id)
            ->with('user:id,name,email')
            ->get()
            ->each(function (DeviceUser $mapping): void {
                $this->line(sprintf(
                    '  #%s  %s  card: %s  fingerprint: %s',
                    $mapping->device_employee_no,
                    $mapping->user->name ?? '-',
                    $mapping->card_no ?? '-',
                    $mapping->fingerprint_enrolled ? 'yes' : 'no'
                ));
            });

        return self::SUCCESS;
    }

    /**
     * Resolve the device from the argument (id, IP address or a name).
     */
    private function resolveDevice(): ?Device
    {
        $argument = $this->argument('device');

        if (! $argument) {
            $device = Device::query()->where('is_active', true)->orderBy('id')->first();

            return $device ? $this->applyOverrides($device) : null;
        }

        $device = Device::query()
            ->where('ip_address', $argument)
            ->orWhere('id', is_numeric($argument) ? (int) $argument : 0)
            ->first();

        return $device ? $this->applyOverrides($device) : null;
    }

    /**
     * Apply the command line credential overrides.
     */
    private function applyOverrides(Device $device): Device
    {
        if ($this->option('username')) {
            $device->username = (string) $this->option('username');
        }

        if ($this->option('password')) {
            $device->password = (string) $this->option('password');
        }

        return $device;
    }
}
