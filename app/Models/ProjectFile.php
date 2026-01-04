<?php

namespace App\Models;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProjectFile extends Model
{
    use HasFactory, HasCreatedUpdatedBy;

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
        // Admin can see everything
        if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
            return true;
        }

        // Check if user is a project member
        $isMember = $this->project->members()->where('user_id', $user->id)->exists();
        if ($isMember) {
            return true;
        }

        // Check client visibility
        if ($user->hasRole('client') && $this->is_client_visible) {
            return $this->project->client_id === $user->id;
        }

        // Check role-based visibility
        if ($this->visible_to_roles) {
            foreach ($user->roles as $role) {
                if (in_array($role->name, $this->visible_to_roles)) {
                    return true;
                }
            }
        }

        return false;
    }
}
