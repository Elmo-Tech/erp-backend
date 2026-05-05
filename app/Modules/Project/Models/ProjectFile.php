<?php

namespace App\Modules\Project\Models;

use App\Modules\Settings\Models\User;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $project_id
 * @property string $name
 * @property string $type
 * @property string|null $file_path
 * @property string|null $url
 * @property bool $is_client_visible
 * @property array<int, string>|null $visible_to_roles
 * @property-read Project $project
 */
class ProjectFile extends Model
{
    use HasCreatedUpdatedBy, HasFactory, SoftDeletes;

    protected $table = 'project_files';

    protected $fillable = [
        'project_id',
        'name',
        'type',
        'file_path',
        'url',
        'mime_type',
        'size',
        'description',
        'category',
        'is_client_visible',
        'is_downloadable',
        'visible_to_roles',
    ];

    protected function casts(): array
    {
        return [
            'is_client_visible' => 'boolean',
            'is_downloadable' => 'boolean',
            'visible_to_roles' => 'array',
            'size' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the project this file belongs to
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the file URL
     */
    public function getFileUrlAttribute(): ?string
    {
        if ($this->type === 'link') {
            return $this->url;
        }

        if ($this->file_path) {
            return Storage::disk('public')->url($this->file_path);
        }

        return null;
    }

    /**
     * Check if user can view this file.
     *
     * Authorization order (no role checks):
     *   1. manage_project_files  → unrestricted access without role-name checks.
     *   2. Project membership    → any member may view project files.
     *   3. access_client_portal  → client-portal users see client-visible files
     *                              for their own client's project only.
     */
    public function canUserView(User $user): bool
    {
        /** @var Project $project */
        $project = $this->project;

        // Full file management permission — unrestricted visibility.
        if ($user->can('manage_project_files')) {
            return true;
        }

        // Project members can always view files.
        if ($project->members()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Client-portal users see only client-visible files for their project.
        if ($user->can('access_client_portal') && $this->is_client_visible) {
            return $project->client_id === $user->client?->id;
        }

        return false;
    }
}
