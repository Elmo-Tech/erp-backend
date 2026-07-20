<?php

namespace App\Modules\Project\Models;

use App\Enums\ProjectRole;
use App\Modules\Settings\Models\User;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

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
     * Return a revocable delivery URL instead of exposing the storage path.
     */
    public function getFileUrlAttribute(): ?string
    {
        if (! $this->is_downloadable) {
            return null;
        }

        if ($this->type === 'link') {
            return $this->url;
        }

        if ($this->file_path) {
            return route('projects.files.download', [
                'project' => $this->project_id,
                'file' => $this->id,
            ]);
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
        if ($this->hasGlobalFileVisibility($user)) {
            return true;
        }

        // Client-portal users see only client-visible files for their project.
        if ($user->can('access_client_portal')) {
            return $this->is_client_visible && $project->client_id === $user->client?->id;
        }

        $member = $project->members()->where('user_id', $user->id)->first();

        if (! $member instanceof ProjectMember) {
            return false;
        }

        if (empty($this->visible_to_roles)) {
            return true;
        }

        return in_array($member->role->value, $this->visible_to_roles, true);
    }

    /**
     * Apply the same portal and project-role visibility boundary used at delivery.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($this->hasGlobalFileVisibility($user)) {
            return $query;
        }

        if ($user->can('access_client_portal')) {
            return $query
                ->where('is_client_visible', true)
                ->whereHas('project', fn (Builder $projectQuery) => $projectQuery
                    ->where('client_id', $user->client?->id));
        }

        return $query
            ->whereHas('project.members', fn (Builder $memberQuery) => $memberQuery
                ->where('user_id', $user->id))
            ->where(function (Builder $visibilityQuery) use ($user) {
                $visibilityQuery
                    ->whereNull('visible_to_roles')
                    ->orWhereJsonLength('visible_to_roles', 0);

                foreach (ProjectRole::cases() as $role) {
                    $visibilityQuery->orWhere(function (Builder $roleQuery) use ($role, $user) {
                        $roleQuery
                            ->whereJsonContains('visible_to_roles', $role->value)
                            ->whereHas('project.members', fn (Builder $memberQuery) => $memberQuery
                                ->where('user_id', $user->id)
                                ->where('role', $role->value));
                    });
                }
            });
    }

    private function hasGlobalFileVisibility(User $user): bool
    {
        return $user->can('manage_project_files')
            || ($user->can('view_project_files')
                && ($user->can('manage_all_projects') || $user->can('view_all_projects')));
    }
}
