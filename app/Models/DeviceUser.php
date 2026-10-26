<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $device_id
 * @property int $user_id
 * @property string $device_employee_no
 * @property string|null $card_no
 * @property bool $fingerprint_enrolled
 * @property Carbon|null $last_synced_at
 * @property-read Device $device
 * @property-read User|null $user
 */
class DeviceUser extends Model
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
        'device_employee_no',
        'card_no',
        'fingerprint_enrolled',
        'last_synced_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fingerprint_enrolled' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Get the device this mapping belongs to.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Get the ERP user this mapping belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
