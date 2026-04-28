<?php

namespace App\Traits;

use App\Enums\AuditActionType;
use App\Modules\Lead\Models\LeadHistory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait HasAuditTrail
{
    /**
     * Boot the trait and register model events
     */
    protected static function bootHasAuditTrail(): void
    {
        // Log creation
        static::created(function ($model) {
            $model->logAudit(
                AuditActionType::CREATED,
                null,
                null,
                null,
                __('audit.record_created', ['model' => class_basename($model)])
            );
        });

        // Log updates with field tracking
        static::updated(function ($model) {
            $changes = $model->getChanges();
            $original = $model->getOriginal();

            // Skip audit columns
            $skipFields = ['updated_at', 'updated_by', 'created_at', 'created_by'];

            foreach ($changes as $field => $newValue) {
                if (in_array($field, $skipFields)) {
                    continue;
                }

                $oldValue = $original[$field] ?? null;

                // Determine action type
                $actionType = $field === 'status'
                    ? AuditActionType::STATUS_CHANGE
                    : AuditActionType::DATA_EDIT;

                $model->logAudit(
                    $actionType,
                    $field,
                    $model->formatAuditValue($field, $oldValue),
                    $model->formatAuditValue($field, $newValue),
                    $model->getAuditDescription($field, $oldValue, $newValue)
                );
            }
        });

        // Log deletion
        static::deleted(function ($model) {
            $model->logAudit(
                AuditActionType::DELETED,
                null,
                null,
                null,
                __('audit.record_deleted', ['model' => class_basename($model)])
            );
        });
    }

    /**
     * Get all audit history for this model
     */
    public function histories(): MorphMany
    {
        return $this->morphMany(LeadHistory::class, 'auditable')->orderByDesc('created_at');
    }

    /**
     * Log an audit entry
     */
    public function logAudit(
        AuditActionType $actionType,
        ?string $fieldName = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?string $description = null,
        ?string $reason = null,
        ?array $metadata = null
    ): LeadHistory {
        return LeadHistory::create([
            'auditable_type' => get_class($this),
            'auditable_id' => $this->id,
            'action_type' => $actionType,
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'description' => $description,
            'reason' => $reason,
            'metadata' => $metadata,
            'changed_by' => Auth::id(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * Log a custom audit entry (for manual logging)
     */
    public function logCustomAudit(
        AuditActionType $actionType,
        string $description,
        ?string $reason = null,
        ?array $metadata = null
    ): LeadHistory {
        return $this->logAudit($actionType, null, null, null, $description, $reason, $metadata);
    }

    /**
     * Format value for audit display
     */
    protected function formatAuditValue(string $field, $value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        // Handle enums
        if (is_object($value) && enum_exists(get_class($value))) {
            return $value->value;
        }

        // Handle arrays/JSON
        if (is_array($value)) {
            return json_encode($value);
        }

        // Handle booleans
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    /**
     * Get description for audit entry
     */
    protected function getAuditDescription(string $field, $oldValue, $newValue): string
    {
        $fieldLabel = $this->getAuditFieldLabel($field);

        if ($field === 'status') {
            return __('audit.status_changed', [
                'from' => $this->formatAuditValue($field, $oldValue) ?? 'N/A',
                'to' => $this->formatAuditValue($field, $newValue) ?? 'N/A',
            ]);
        }

        return __('audit.field_changed', [
            'field' => $fieldLabel,
            'from' => $this->formatAuditValue($field, $oldValue) ?? 'N/A',
            'to' => $this->formatAuditValue($field, $newValue) ?? 'N/A',
        ]);
    }

    /**
     * Get human-readable field label
     */
    protected function getAuditFieldLabel(string $field): string
    {
        // Convert snake_case to Title Case
        return ucwords(str_replace('_', ' ', $field));
    }
}
