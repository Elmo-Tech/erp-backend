<?php

namespace App\Models;

use App\Enums\AuditActionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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

    /**
     * Get the auditable entity (Lead, Proposal, Quotation)
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who made this change
     */
    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Scope for specific action type
     */
    public function scopeOfType($query, AuditActionType $type)
    {
        return $query->where('action_type', $type);
    }

    /**
     * Scope for specific field
     */
    public function scopeForField($query, string $field)
    {
        return $query->where('field_name', $field);
    }

    /**
     * Scope for date range
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
