<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Device>
     */
    protected $model = Device::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Main Entrance',
            'vendor' => 'hikvision',
            'model' => 'DS-K1T8003MF',
            'location' => 'HQ - Main Entrance',
            'ip_address' => '192.168.1.64',
            'port' => 80,
            'username' => 'admin',
            'password' => 'secret',
            'verify_tls' => false,
            'webhook_token' => Device::generateWebhookToken(),
            'is_active' => true,
            'auto_sync' => true,
            'sync_interval_minutes' => 5,
        ];
    }
}
