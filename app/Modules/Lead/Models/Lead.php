<?php

namespace App\Modules\Lead\Models;

use App\Enums\LeadStage;
use App\Enums\LeadStatus;
use App\Modules\Client\Models\Client;
use App\Modules\Settings\Models\User;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int         $id
 * @property string      $title
 * @property string|null $description
 * @property LeadStage   $stage
 * @property LeadStatus  $status
 * @property int|null    $assigned_to
 * @property int|null    $client_id
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
        'notes',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'stage'               => LeadStage::class,
            'status'              => LeadStatus::class,
            'expected_budget'     => 'decimal:2',
            'expected_close_date' => 'date',
            'metadata'            => 'array',
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

    /**
     * All proposals created during this lead's pipeline.
     */
    public function proposals(): HasMany
    {
        return $this->hasMany(\App\Modules\Lead\Models\Proposal::class, 'lead_id');
    }

    /**
     * All quotations created during this lead's pipeline.
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(\App\Modules\Lead\Models\Quotation::class, 'lead_id');
    }

    /**
     * All contracts created during this lead's pipeline.
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(\App\Modules\Lead\Models\Contract::class, 'lead_id');
    }
}
