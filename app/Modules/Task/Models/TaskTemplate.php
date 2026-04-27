<?php

namespace App\Modules\Task\Models;

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

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'template_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function createTask(array $overrides = []): Task
    {
        $defaults = $this->default_values ?? [];
        $taskData = array_merge($defaults, $overrides, [
            'template_id' => $this->id,
        ]);

        $task = Task::create($taskData);

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

    public function getUsageCountAttribute(): int
    {
        return $this->tasks()->count();
    }
}
