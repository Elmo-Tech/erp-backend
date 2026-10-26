<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;

class ConfigureDeviceBridge extends Command
{
    protected $signature = 'devices:bridge {device} {--activate : Apply received punches to attendance} {--rotate-token : Issue a new bridge credential}';

    protected $description = 'Configure outbound LAN bridge delivery (shadow/read-only by default)';

    public function handle(): int
    {
        $device = Device::findOrFail($this->argument('device'));
        $meta = $device->meta ?? [];
        $token = null;
        if (empty($meta['bridge_token_hash']) || $this->option('rotate-token')) {
            $token = bin2hex(random_bytes(32));
            $meta['bridge_token_hash'] = hash('sha256', $token);
        }
        $meta['connection_mode'] = 'lan_bridge';
        $meta['bridge_mode'] = $this->option('activate') ? 'attendance' : 'shadow';
        $device->update(['meta' => $meta, 'auto_sync' => false]);
        $this->info('Bridge mode: '.$meta['bridge_mode'].'. Server-side polling is disabled for this device.');
        $this->line('ERP_BRIDGE_URL='.rtrim(config('app.url'), '/').'/api/v1/device-bridge/'.$device->id.'/events');
        if ($token) {
            $this->line('ERP_BRIDGE_TOKEN='.$token);
            $this->comment('Save this credential on the office bridge only. It is shown once.');
        }

        return self::SUCCESS;
    }
}
