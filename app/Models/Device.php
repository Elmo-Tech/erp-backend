<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $vendor
 * @property string|null $model
 * @property string|null $location
 * @property string $ip_address
 * @property int $port
 * @property string|null $username
 * @property string|null $password
 * @property bool $verify_tls
 * @property string|null $webhook_token
 * @property bool $is_active
 * @property bool $auto_sync
 * @property int $sync_interval_minutes
 * @property Carbon|null $last_event_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $last_synced_at
 * @property string|null $last_status
 * @property string|null $last_error
 * @property array<string, mixed>|null $meta
 */
class Device extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'vendor',
        'model',
        'location',
        'ip_address',
        'port',
        'username',
        'password',
        'verify_tls',
        'webhook_token',
        'is_active',
        'auto_sync',
        'sync_interval_minutes',
        'last_event_at',
        'last_seen_at',
        'last_synced_at',
        'last_status',
        'last_error',
        'meta',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'verify_tls' => 'boolean',
            'is_active' => 'boolean',
            'auto_sync' => 'boolean',
            'sync_interval_minutes' => 'integer',
            'last_event_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * Get the users mapped to this device.
     *
     * @return HasMany<DeviceUser, $this>
     */
    public function deviceUsers(): HasMany
    {
        return $this->hasMany(DeviceUser::class);
    }

    /**
     * Get the access events recorded for this device.
     *
     * @return HasMany<DeviceAccessEvent, $this>
     */
    public function accessEvents(): HasMany
    {
        return $this->hasMany(DeviceAccessEvent::class);
    }

    /**
     * Get the attendances produced by this device.
     */
    public function attendances(): HasManyThrough
    {
        return $this->hasManyThrough(
            Attendance::class,
            DeviceAccessEvent::class,
            'device_id',
            'user_id',
            'id',
            'user_id'
        );
    }

    /**
     * Base URL used to reach the device over ISAPI.
     */
    public function baseUrl(): string
    {
        $scheme = ($this->meta['scheme'] ?? 'http') === 'https' ? 'https' : 'http';

        return sprintf('%s://%s:%d', $scheme, $this->ip_address, $this->port);
    }

    /**
     * Determine whether the device is considered reachable right now.
     */
    public function isOnline(): bool
    {
        if (! $this->last_seen_at) {
            return false;
        }

        $threshold = max(5, $this->sync_interval_minutes) * 3;

        return $this->last_seen_at->gt(Carbon::now()->subMinutes($threshold));
    }

    /**
     * Generate a fresh webhook token for push mode.
     */
    public static function generateWebhookToken(): string
    {
        return bin2hex(random_bytes(24));
    }

    /**
     * Ensure every device can be reached by the push endpoint.
     */
    protected static function booted(): void
    {
        static::creating(function (Device $device): void {
            if (blank($device->webhook_token)) {
                $device->webhook_token = self::generateWebhookToken();
            }
        });
    }
}
