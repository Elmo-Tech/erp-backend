<?php

namespace App\Models;

use App\Contracts\Trackable;
use App\Enums\IndustrySector;
use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LeadTemperature;
use App\Enums\ReferenceType;
use App\Traits\HasAuditTrail;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property LeadSource|string|null $source
 */
class Lead extends Model implements Trackable
{
    use HasAuditTrail, HasCreatedUpdatedBy, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'project_name',
        'email',
        'phone',
        'company',
        'country',
        'timezone_offset',
        'industry_sector',
        'contact_preferences',
        'source',
        'reference_type',
        'reference_id',
        'tech_lead_id',
        'status',
        'priority',
        'lead_temperature',
        'expected_budget',
        'expected_close_date',
        'last_contact_at',
        'next_follow_up_at',
        'notes',
        'loss_reason',
        'client_id',
        'converted_at',
        'converted_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'reference_type' => ReferenceType::class,
            'status' => LeadStatus::class,
            'priority' => LeadPriority::class,
            'industry_sector' => IndustrySector::class,
            'lead_temperature' => LeadTemperature::class,
            'contact_preferences' => 'array',
            'expected_budget' => 'decimal:2',
            'expected_close_date' => 'date',
            'last_contact_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    /**
     * Get the reference entity (User or Client)
     */
    public function reference(): MorphTo
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }

    /**
     * Get the sales rep if reference_type is sales_rep
     */
    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reference_id')
            ->where('reference_type', ReferenceType::SALES_REP->value);
    }

    /**
     * Get the client this lead was converted to
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the user who converted this lead
     */
    public function convertedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    /**
     * Check if lead is converted
     */
    public function isConverted(): bool
    {
        return $this->client_id !== null;
    }

    /**
     * Get the quotations for this lead.
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * Get the proposals for this lead.
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the tech lead assigned for evaluation.
     */
    public function techLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tech_lead_id');
    }

    /**
     * Get the activities for this lead.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get all polymorphic activities for this lead.
     */
    public function allActivities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject')->orderBy('created_at', 'desc');
    }

    /**
     * Get the meetings for this lead.
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(LeadMeeting::class)->orderBy('scheduled_at', 'desc');
    }

    /**
     * Get all time entries for this trackable entity.
     */
    public function timeEntries(): MorphMany
    {
        return $this->morphMany(TimeEntry::class, 'trackable');
    }

    /**
     * Get the display name for this trackable entity.
     */
    public function getTrackableDisplayName(): string
    {
        return $this->name.($this->company ? ' ('.$this->company.')' : '');
    }

    /**
     * Scope for leads needing follow-up
     */
    public function scopeNeedsFollowUp($query)
    {
        return $query->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', now())
            ->whereNotIn('status', [LeadStatus::WON, LeadStatus::LOST]);
    }

    /**
     * Scope for active leads (not won or lost)
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [LeadStatus::WON, LeadStatus::LOST]);
    }
}
