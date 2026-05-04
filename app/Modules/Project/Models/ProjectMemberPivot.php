<?php

namespace App\Modules\Project\Models;

use App\Enums\ProjectRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Custom pivot model for the project_members table.
 *
 * Provides enum casting for the `role` column so that
 * $project->teamMembers()->first()->pivot->role
 * returns a ProjectRole enum instance.
 */
class ProjectMemberPivot extends Pivot
{
    protected $table = 'project_members';

    protected function casts(): array
    {
        return [
            'role' => ProjectRole::class,
        ];
    }
}
