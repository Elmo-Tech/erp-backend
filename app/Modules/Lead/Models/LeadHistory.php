<?php

namespace App\Modules\Lead\Models;

use App\Enums\AuditActionType;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $auditable_type
 * @property int $auditable_id
 * @property AuditActionType $action_type
 * @property string|null $field_name
 * @property string|null $old_value
 * @property string|null $new_value
 * @property string|null $description
 * @property string|null $reason
 * @property array<string, mixed>|null $metadata
 * @property int $changed_by
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read User $changedByUser
 */
class LeadHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'action_type',
        'field_name',
        'old_value',
        'new_value',
        'description',
        'reason',
        'metadata',
        'changed_by',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'action_type' => AuditActionType::class,
            'metadata' => 'array',
        ];
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function scopeOfType($query, AuditActionType $type)
    {
        return $query->where('action_type', $type);
    }

    public function scopeForField($query, string $field)
    {
        return $query->where('field_name', $field);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
