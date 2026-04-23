<?php

namespace App\Models;

use App\Enums\HandoverStatus;
use App\Modules\Project\Models\Project;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectHandover extends Model
{
    use HasCreatedUpdatedBy, HasFactory;

    protected $fillable = [
        'project_id',
        'contract_id',
        'quotation_id',
        'sales_rep_id',
        'project_manager_id',
        'handover_date',
        'scope_confirmed',
        'requirements_clear',
        'timeline_agreed',
        'budget_confirmed',
        'risks_identified',
        'notes',
        'attachments',
        'status',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'handover_date' => 'datetime',
            'scope_confirmed' => 'boolean',
            'requirements_clear' => 'boolean',
            'timeline_agreed' => 'boolean',
            'budget_confirmed' => 'boolean',
            'risks_identified' => 'boolean',
            'attachments' => 'array',
            'status' => HandoverStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    /**
     * Check if all checklist items are confirmed
     */
    public function isChecklistComplete(): bool
    {
        return $this->scope_confirmed
            && $this->requirements_clear
            && $this->timeline_agreed
            && $this->budget_confirmed
            && $this->risks_identified;
    }
}
