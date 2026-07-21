<?php

namespace App\Modules\Lead\Models;

use App\Enums\DocumentCreationType;
use App\Enums\ProposalStatus;
use App\Modules\Client\Models\Client;
use App\Modules\PersonalTasks\Models\PersonalTask;
use App\Modules\Project\Models\Project;
use App\Modules\Settings\Models\User;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $lead_id
 * @property int|null $client_id
 * @property int|null $project_id
 * @property ProposalStatus $status
 * @property DocumentCreationType $creation_type
 * @property string|null $skip_reason
 * @property int|null $assigned_to
 * @property int|null $personal_task_id
 * @property string|null $file_path
 * @property string|null $file_name
 * @property string|null $file_mime_type
 * @property int|null $file_size
 */
class Proposal extends Model
{
    use HasCreatedUpdatedBy, SoftDeletes;

    protected $table = 'proposals';

    protected $fillable = [
        'lead_id',
        'client_id',
        'project_id',
        'status',
        'creation_type',
        'skip_reason',
        'assigned_to',
        'personal_task_id',
        'file_path',
        'file_name',
        'file_mime_type',
        'file_size',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProposalStatus::class,
            'creation_type' => DocumentCreationType::class,
            'file_size' => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function personalTask(): BelongsTo
    {
        return $this->belongsTo(PersonalTask::class);
    }
}
