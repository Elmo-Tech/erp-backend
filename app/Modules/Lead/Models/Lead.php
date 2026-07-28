<?php

namespace App\Modules\Lead\Models;

use App\Enums\DocumentCreationType;
use App\Enums\LeadStage;
use App\Enums\LeadStatus;
use App\Modules\Client\Models\Client;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property LeadStage $stage
 * @property LeadStatus $status
 * @property int|null $assigned_to
 * @property int|null $client_id
 * @property int|null $converted_project_id
 * @property-read bool $has_skipped_meeting_stage
 */
class Lead extends Model
{
    use HasCreatedUpdatedBy, SoftDeletes;

    protected $table = 'leads';

    protected $fillable = [
        'title',
        'description',
        'stage',
        'status',
        'lead_temperature',
        'source',
        'expected_budget',
        'expected_close_date',
        'assigned_to',
        'client_id',
        'converted_project_id',
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'stage' => LeadStage::class,
            'status' => LeadStatus::class,
            'expected_budget' => 'decimal:2',
            'expected_close_date' => 'date',
            'metadata' => 'array',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The user assigned to manage this lead.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * The client this lead is linked to (set after conversion).
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function convertedProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'converted_project_id');
    }

    /**
     * All proposals created during this lead's pipeline.
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class, 'lead_id');
    }

    /**
     * All quotations created during this lead's pipeline.
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class, 'lead_id');
    }

    /**
     * All contracts created during this lead's pipeline.
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'lead_id');
    }

    public function hasResolvedMeetingStage(): bool
    {
        $hasCompletedMeeting = Meeting::query()
            ->where('model_type', self::class)
            ->where('model_id', $this->id)
            ->where('status', 'completed')
            ->exists();

        if ($hasCompletedMeeting) {
            return true;
        }

        // The current pipeline stores an explicit meeting skip as proposal-shaped evidence.
        return $this->proposals()
            ->where('creation_type', DocumentCreationType::SKIPPED->value)
            ->exists();
    }
}
