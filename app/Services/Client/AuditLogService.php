<?php

namespace App\Services\Client;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

class AuditLogService
{
    /**
     * Log an action for audit trail.
     */
    public function log(string $action, ?Model $auditable = null, array $metadata = [], ?string $ipAddress = null, ?string $userAgent = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'event_type' => $this->classifyEventType($action),
            'severity' => $this->classifySeverity($action),
            'description' => $this->buildDescription($action, $auditable),
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->id,
            'ip_address' => $ipAddress ?? Request::ip(),
            'user_agent' => $userAgent ?? Request::userAgent(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log a forbidden access attempt.
     */
    public function logForbiddenAccess(string $resourceType, ?int $resourceId = null, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        $this->log('forbidden_access_attempt', null, [
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
        ], $ipAddress, $userAgent);
    }

    /**
     * Log client authentication event.
     */
    public function logAuthentication(string $event, ?int $userId = null, ?string $ipAddress = null, ?string $userAgent = null): void
    {
        AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $event,
            'event_type' => 'security',
            'severity' => Str::contains($event, ['failed', 'forbidden']) ? 'warning' : 'info',
            'description' => Str::headline(str_replace('_', ' ', $event)),
            'auditable_type' => null,
            'auditable_id' => null,
            'ip_address' => $ipAddress ?? Request::ip(),
            'user_agent' => $userAgent ?? Request::userAgent(),
            'metadata' => [],
        ]);
    }

    private function classifyEventType(string $action): string
    {
        return match (true) {
            Str::contains($action, ['login', 'logout', 'password', 'forbidden', 'permission']) => 'security',
            Str::contains($action, ['cost', 'role', 'admin']) => 'admin_action',
            default => 'system_action',
        };
    }

    private function classifySeverity(string $action): string
    {
        return match (true) {
            Str::contains($action, ['password', 'permission']) => 'critical',
            Str::contains($action, ['failed', 'forbidden', 'delete']) => 'warning',
            default => 'info',
        };
    }

    private function buildDescription(string $action, ?Model $auditable): string
    {
        $description = Str::headline(str_replace('_', ' ', $action));

        if (!$auditable) {
            return $description;
        }

        return sprintf('%s for %s #%s', $description, class_basename($auditable), $auditable->getKey());
    }
}
