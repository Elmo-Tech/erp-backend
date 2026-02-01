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
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'auditLogId' => $this->id,
            'userId' => $this->user_id,
            'eventType' => $this->event_type ?? '',
            'action' => $this->action,
            'severity' => $this->severity ?? 'info',
            'severityLabel' => __('enums.audit_severity.' . ($this->severity ?? 'info')),
            'severityColor' => $this->getSeverityColor(),
            'description' => $this->description ?? '',
            'auditableType' => $this->auditable_type ?? '',
            'auditableId' => $this->auditable_id,
            'ipAddress' => $this->ip_address ?? '',
            'userAgent' => $this->user_agent ?? '',
            'metadata' => $this->metadata ?? [],
            
            // Relationships
            'user' => $this->when($this->relationLoaded('user') && $this->user, function () {
                return [
                    'userId' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ];
            }),
            
            'auditable' => $this->when($this->relationLoaded('auditable') && $this->auditable, function () {
                return [
                    'type' => class_basename($this->auditable_type),
                    'id' => $this->auditable_id,
                    'identifier' => $this->getAuditableIdentifier(),
                ];
            }),
            
            'createdAt' => $this->created_at->toISOString(),
        ];
    }

    /**
     * Get severity color for UI display
     */
    private function getSeverityColor(): string
    {
        return match ($this->severity ?? 'info') {
            'info' => 'default',
            'warning' => 'warning',
            'critical' => 'destructive',
            default => 'default',
        };
    }

    /**
     * Get auditable entity identifier for display
     */
    private function getAuditableIdentifier(): string
    {
        if (!$this->auditable) {
            return '';
        }

        return match (class_basename($this->auditable_type)) {
            'Invoice' => $this->auditable->invoice_number ?? "Invoice #{$this->auditable_id}",
            'Contract' => $this->auditable->contract_number ?? "Contract #{$this->auditable_id}",
            'Project' => $this->auditable->name ?? "Project #{$this->auditable_id}",
            'User' => $this->auditable->name ?? "User #{$this->auditable_id}",
            default => "{$this->auditable_type} #{$this->auditable_id}",
        };
    }
}