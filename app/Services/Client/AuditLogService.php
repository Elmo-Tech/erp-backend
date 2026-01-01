<?php

namespace App\Services\Client;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

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
            'auditable_type' => null,
            'auditable_id' => null,
            'ip_address' => $ipAddress ?? Request::ip(),
            'user_agent' => $userAgent ?? Request::userAgent(),
            'metadata' => [],
        ]);
    }
}