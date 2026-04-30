<?php

namespace App\Modules\Project\Models;

use App\Modules\Settings\Models\User;
use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    use HasCreatedUpdatedBy, HasFactory;

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
     * Check if user can view this file
     */
    public function canUserView(User $user): bool
    {
        /** @var Project $project */
        $project = $this->project;

        // Admin can see everything
        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            return true;
        }

        // Check if user is a project member
        $isMember = $project->members()->where('user_id', $user->id)->exists();
        if ($isMember) {
            return true;
        }

        // Check client visibility
        if ($user->hasRole('client') && $this->is_client_visible) {
            return $project->client_id === $user->client?->id;
        }

        // Check role-based visibility
        if (is_array($this->visible_to_roles)) {
            return $user->roles
                ->pluck('name')
                ->intersect($this->visible_to_roles)
                ->isNotEmpty();
        }

        return false;
    }
}
