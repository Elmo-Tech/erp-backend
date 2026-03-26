<?php

namespace App\Http\Resources\V1\Admin;

use App\Http\Resources\V1\AuditLog\AuditLogResource as BaseAuditLogResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AuditLogResource',
    description: 'Audit log entry with user information and action details',
    properties: [
        new OA\Property(property: 'auditLogId', type: 'integer', example: 1),
        new OA\Property(property: 'userId', type: 'integer', nullable: true, example: 1),
        new OA\Property(property: 'userName', type: 'string', nullable: true, example: 'John Doe'),
        new OA\Property(property: 'action', type: 'string', example: 'update_internal_cost'),
        new OA\Property(property: 'auditableType', type: 'string', nullable: true, example: 'App\\Models\\User'),
        new OA\Property(property: 'auditableId', type: 'integer', nullable: true, example: 5),
        new OA\Property(property: 'ipAddress', type: 'string', nullable: true, example: '192.168.1.100'),
        new OA\Property(property: 'userAgent', type: 'string', nullable: true, example: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
        new OA\Property(
            property: 'metadata',
            type: 'object',
            nullable: true,
            example: ['old_cost' => '50.00', 'new_cost' => '75.00', 'reason' => 'Performance review']
        ),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2024-01-15T10:30:00Z'),
        new OA\Property(property: 'isSensitive', type: 'boolean', example: true, description: 'Indicates if this is a security-sensitive action'),
    ]
)]
class AuditLogResource extends BaseAuditLogResource
{
}
