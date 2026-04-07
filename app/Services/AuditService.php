<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Log a system event to the master audit trail
     */
    public function log(
        string $eventType,
        string $action,
        ?Model $entity = null,
        array $metadata = [],
        string $severity = 'info',
        ?string $description = null
    ): AuditLog {
        $request = request();

        return AuditLog::create([
            'user_id' => Auth::id(),
            'event_type' => $eventType,
            'action' => $action,
            'auditable_type' => $entity ? get_class($entity) : null,
            'auditable_id' => $entity?->id,
            'severity' => $severity,
            'description' => $description,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => array_merge($metadata, [
                'timestamp' => now()->toISOString(),
                'user_name' => Auth::user()?->name,
            ]),
        ]);
    }

    /**
     * Log contract-related events
     */
    public function logContractEvent(string $action, Model $contract, array $metadata = [], string $severity = 'info'): AuditLog
    {
        return $this->log(
            eventType: 'contract',
            action: $action,
            entity: $contract,
            metadata: $metadata,
            severity: $severity,
            description: "Contract {$action}: {$contract->contract_number}"
        );
    }

    /**
     * Log invoice-related events
     */
    public function logInvoiceEvent(string $action, Model $invoice, array $metadata = [], string $severity = 'info'): AuditLog
    {
        return $this->log(
            eventType: 'invoice',
            action: $action,
            entity: $invoice,
            metadata: $metadata,
            severity: $severity,
            description: "Invoice {$action}: {$invoice->invoice_number}"
        );
    }

    /**
     * Log project-related events
     */
    public function logProjectEvent(string $action, Model $project, array $metadata = [], string $severity = 'info'): AuditLog
    {
        return $this->log(
            eventType: 'project',
            action: $action,
            entity: $project,
            metadata: $metadata,
            severity: $severity,
            description: "Project {$action}: {$project->name}"
        );
    }

    /**
     * Log payment-related events
     */
    public function logPaymentEvent(string $action, Model $invoice, array $metadata = [], string $severity = 'info'): AuditLog
    {
        return $this->log(
            eventType: 'payment',
            action: $action,
            entity: $invoice,
            metadata: $metadata,
            severity: $severity,
            description: "Payment {$action} for invoice: {$invoice->invoice_number}"
        );
    }

    /**
     * Log security-related events
     */
    public function logSecurityEvent(string $action, array $metadata = [], string $severity = 'warning'): AuditLog
    {
        return $this->log(
            eventType: 'security',
            action: $action,
            metadata: $metadata,
            severity: $severity,
            description: "Security event: {$action}"
        );
    }

    /**
     * Log system-related events
     */
    public function logSystemEvent(string $action, array $metadata = [], string $severity = 'info'): AuditLog
    {
        return $this->log(
            eventType: 'system',
            action: $action,
            metadata: $metadata,
            severity: $severity,
            description: "System event: {$action}"
        );
    }

    /**
     * Get audit logs with filters
     */
    public function getAuditLogs(array $filters = [])
    {
        $query = AuditLog::with(['user', 'auditable'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (! empty($filters['event_type'])) {
            $query->where('event_type', $filters['event_type']);
        }

        if (! empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', 'like', "%{$filters['action']}%");
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }
}
