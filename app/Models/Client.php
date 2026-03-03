<?php

namespace App\Models;

use App\Enums\ClientStatus;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, HasCreatedUpdatedBy, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'position',
        'address',
        'city',
        'country',
        'website',
        'notes',
        'status',
        'user_id',
        'lead_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ClientStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user account linked to this client (for portal access)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the original lead this client was converted from
     */
    public function originalLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    /**
     * Get all leads associated with this client (for future leads from same client)
     */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Get all projects for this client
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get all quotations through leads
     */
    public function quotations(): HasManyThrough
    {
        return $this->hasManyThrough(Quotation::class, Lead::class);
    }

    /**
     * Get all invoices through projects
     */
    public function invoices(): HasManyThrough
    {
        return $this->hasManyThrough(Invoice::class, Project::class);
    }

    /**
     * Scope for active clients
     */
    public function scopeActive($query)
    {
        return $query->where('status', ClientStatus::ACTIVE);
    }

    /**
     * Check if client has portal access
     */
    public function hasPortalAccess(): bool
    {
        return $this->user_id !== null && $this->user?->is_active;
    }

    /**
     * Get total revenue from all projects
     */
    public function getTotalRevenueAttribute(): float
    {
        return $this->projects()->sum('budget') ?? 0;
    }

    /**
     * Get count of active projects
     */
    public function getActiveProjectsCountAttribute(): int
    {
        return $this->projects()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();
    }
}
