<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $device_id
 * @property int|null $user_id
 * @property string|null $external_id
 * @property string|null $device_employee_no
 * @property string|null $event_type
 * @property string|null $verify_mode
 * @property string|null $card_no
 * @property int|null $door_no
 * @property bool $is_authorized
 * @property Carbon $occurred_at
 * @property array<string, mixed>|null $raw_payload
 * @property-read Device $device
 * @property-read User|null $user
 */
class DeviceAccessEvent extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'device_id',
        'user_id',
        'external_id',
        'device_employee_no',
        'event_type',
        'verify_mode',
        'card_no',
        'door_no',
        'is_authorized',
        'occurred_at',
        'raw_payload',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_authorized' => 'boolean',
            'occurred_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    /**
     * Get the device that produced this event.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Get the ERP user this event was mapped to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
