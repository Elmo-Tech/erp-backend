<?php

namespace App\Models;

use App\Traits\HasCreatedUpdatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskTemplate extends Model
{
    use HasCreatedUpdatedBy, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'category',
        'default_values',
        'subtasks',
        'is_active',
    ];

    protected $casts = [
        'default_values' => 'array',
        'subtasks' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get tasks created from this template
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'template_id');
    }

    /**
     * Scope for active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific category
     */
    public function scopeForCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Create a task from this template
     */
    public function createTask(array $overrides = []): Task
    {
        $defaults = $this->default_values ?? [];
        $taskData = array_merge($defaults, $overrides, [
            'template_id' => $this->id,
        ]);

        $task = Task::create($taskData);

        // Create subtasks if defined
        if (! empty($this->subtasks)) {
            foreach ($this->subtasks as $index => $subtaskData) {
                Task::create([
                    'parent_id' => $task->id,
                    'project_id' => $task->project_id,
                    'title' => $subtaskData['title'] ?? 'Subtask '.($index + 1),
                    'description' => $subtaskData['description'] ?? null,
                    'status' => $subtaskData['status'] ?? 'pending',
                    'priority' => $subtaskData['priority'] ?? $task->priority,
                    'type' => 'subtask',
                    'template_id' => $this->id,
                ]);
            }
        }

        return $task;
    }

    /**
     * Get usage count
     */
    public function getUsageCountAttribute(): int
    {
        return $this->tasks()->count();
    }
}
