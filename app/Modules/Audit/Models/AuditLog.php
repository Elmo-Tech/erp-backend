<?php

namespace App\Modules\Audit\Models;

use App\Modules\User\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $event_type
 * @property string $action
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property string|null $severity
 * @property string|null $description
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read User|null $user
 */
class AuditLog extends Model
{
    use HasFactory;

    private const SENSITIVE_ACTIONS = [
        'update_internal_cost',
        'bulk_update_internal_cost',
        'update_role_permissions',
        'create_role',
        'delete_role',
        'view_profitability',
        'forbidden_access_attempt',
    ];

    protected $fillable = [
        'user_id',
        'event_type',
        'action',
        'auditable_type',
        'auditable_id',
        'severity',
        'description',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AuditLog $auditLog): void {
            $auditLog->event_type ??= self::inferEventType($auditLog->action);
            $auditLog->severity ??= self::inferSeverity($auditLog->action);
            $auditLog->description ??= self::buildDescription($auditLog->action, $auditLog->auditable_type, $auditLog->auditable_id);
        });
    }

    /**
     * Get the user that performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the auditable model (polymorphic relationship).
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isSensitive(): bool
    {
        return in_array($this->action, self::SENSITIVE_ACTIONS, true);
    }

    private static function inferEventType(?string $action): string
    {
        if (! $action) {
            return 'system_action';
        }

        return match (true) {
            Str::contains($action, ['login', 'logout', 'password', 'forbidden', 'permission']) => 'security',
            Str::contains($action, ['cost', 'role', 'admin']) => 'admin_action',
            default => 'system_action',
        };
    }

    private static function inferSeverity(?string $action): string
    {
        if (! $action) {
            return 'info';
        }

        return match (true) {
            Str::contains($action, ['password', 'permission']) => 'critical',
            Str::contains($action, ['failed', 'forbidden', 'delete']) => 'warning',
            default => 'info',
        };
    }

    private static function buildDescription(?string $action, ?string $auditableType, mixed $auditableId): string
    {
        $formattedAction = $action
            ? Str::of($action)->replace('_', ' ')->headline()->value()
            : 'Audit event';

        if (! $auditableType || ! $auditableId) {
            return $formattedAction;
        }

        return sprintf('%s for %s #%s', $formattedAction, class_basename($auditableType), $auditableId);
    }
}
