<?php

namespace App\Modules\Client\Models;

use App\Enums\ClientStatus;
use App\Modules\Invoice\Models\Invoice;
use App\Models\Lead;
use App\Modules\Project\Models\Project;
use App\Models\Quotation;
use App\Modules\User\Models\User;
use App\Traits\HasCreatedUpdatedBy;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $company
 * @property string|null $position
 * @property string|null $address
 * @property string|null $city
 * @property string|null $country
 * @property string|null $website
 * @property string|null $notes
 * @property ClientStatus|null $status
 * @property int|null $user_id
 * @property int|null $lead_id
 * @property float|int|string|null $total_revenue
 * @property int|null $active_projects_count
 * @property int|null $projects_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Project> $projects
 * @property-read Lead|null $originalLead
 */
class Client extends Model
{
    use HasCreatedUpdatedBy, HasFactory, SoftDeletes;

    protected $table = 'clients';

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

    protected static function newFactory(): ClientFactory
    {
        return ClientFactory::new();
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
        return $this->projects()->sum('budget');
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
