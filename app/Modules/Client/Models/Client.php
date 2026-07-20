<?php

namespace App\Modules\Client\Models;

use App\Enums\ClientStatus;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Lead\Models\Lead;
use App\Modules\Project\Models\Project;
use App\Modules\Quotation\Models\Quotation;
use App\Modules\Settings\Models\User;
use App\Traits\HasCreatedUpdatedBy;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Collection<int, Project> $projects
 * @property-read Lead|null $originalLead
 */
class Client extends Model
{
    use HasCreatedUpdatedBy, HasFactory, SoftDeletes;

    protected $table = 'clients';

    protected $fillable = [
        'type',
        'display_name',
        'legal_name',
        'company_name',
        'primary_email',
        'primary_phone',
        'secondary_phone',
        'position',
        'industry',
        'website',
        'tax_number',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'source',
        'lifecycle_stage',
        'lead_temperature',
        'expected_budget',
        'expected_close_date',
        'became_client_at',
        'account_manager_id',
        'notes',
        'metadata',
        'status',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => ClientStatus::class,
            'metadata' => 'array',
            'expected_close_date' => 'date',
            'became_client_at' => 'datetime',
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
     * Get the account manager assigned to this client
     */
    public function accountManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_manager_id');
    }

    /**
     * Get all contacts for this client
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
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
     * Get invoices attached directly to this client.
     */
    public function invoices(): MorphMany
    {
        return $this->morphMany(Invoice::class, 'model');
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
