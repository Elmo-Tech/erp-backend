<?php

namespace App\Http\Resources\V1\AuditLog;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="AuditLogResource",
 *     type="object",
 *     @OA\Property(property="auditLogId", type="integer", example=1),
 *     @OA\Property(property="userId", type="integer", example=1),
 *     @OA\Property(property="eventType", type="string", example="invoice"),
 *     @OA\Property(property="action", type="string", example="created"),
 *     @OA\Property(property="severity", type="string", example="info"),
 *     @OA\Property(property="description", type="string", example="Invoice created: INV-2026-000001"),
 *     @OA\Property(property="auditableType", type="string", example="App\\Models\\Invoice"),
 *     @OA\Property(property="auditableId", type="integer", example=1),
 *     @OA\Property(property="ipAddress", type="string", example="192.168.1.1"),
 *     @OA\Property(property="userAgent", type="string", example="Mozilla/5.0..."),
 *     @OA\Property(property="metadata", type="object", example={"amount": 5000, "currency": "USD"}),
 *     @OA\Property(
 *         property="user",
 *         type="object",
 *         @OA\Property(property="userId", type="integer", example=1),
 *         @OA\Property(property="name", type="string", example="John Doe"),
 *         @OA\Property(property="email", type="string", example="john@example.com")
 *     ),
 *     @OA\Property(
 *         property="auditable",
 *         type="object",
 *         @OA\Property(property="type", type="string", example="Invoice"),
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="identifier", type="string", example="INV-2026-000001")
 *     ),
 *     @OA\Property(property="createdAt", type="string", format="datetime", example="2026-02-01T10:00:00Z")
 * )
 */
class AuditLogResource extends JsonResource
{
    private const SENSITIVE_ACTIONS = [
        'update_internal_cost',
        'bulk_update_internal_cost',
        'update_role_permissions',
        'create_role',
        'delete_role',
        'view_profitability',
        'forbidden_access_attempt',
    ];

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->user_id,
            'userName' => $this->user?->name ?? 'Unknown User',
            'action' => $this->action,
            'eventType' => $this->event_type ?? '',
            'severity' => $this->severity ?? 'info',
            'description' => $this->description ?? '',
            'auditableType' => $this->auditable_type ?? '',
            'auditableId' => $this->auditable_id,
            'ipAddress' => $this->ip_address ?? '',
            'userAgent' => $this->user_agent ?? '',
            'metadata' => $this->metadata ?? [],
            'createdAt' => $this->created_at?->toISOString() ?? '',
            'isSensitive' => in_array($this->action, self::SENSITIVE_ACTIONS, true),
        ];
    }
}
