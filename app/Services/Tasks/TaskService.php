<?php

namespace App\Services\Tasks;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TaskService
{
    /**
     * Get all tasks with optional filtering and pagination.
     *
     * @param array<string, mixed> $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllTasks(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Task::query()->with(['assignee', 'project']);

        // Filter by project
        if (!empty($filters['projectId'])) {
            $query->where('project_id', $filters['projectId']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by assignee
        if (!empty($filters['assignedTo'])) {
            $query->where('assigned_to', $filters['assignedTo']);
        }

        // Filter by parent (null for root tasks)
        if (array_key_exists('parentId', $filters)) {
            $query->where('parent_id', $filters['parentId']);
        }

        // Search by title
        if (!empty($filters['search'])) {
            $query->where('title', 'like', '%' . $filters['search'] . '%');
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get tasks for a specific project with hierarchy support.
     * Returns only root-level tasks (parent_id = null) with children loaded.
     *
     * Requirements: 1.3, 1.4
     *
     * @param int $projectId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getTasksForProject(int $projectId, int $perPage = 15): LengthAwarePaginator
    {
        return Task::query()
            ->where('project_id', $projectId)
            ->whereNull('parent_id')
            ->with(['assignee', 'children.assignee'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get children of a specific task for lazy loading.
     *
     * Requirements: 1.3, 1.4
     *
     * @param int $taskId
     * @return Collection<int, Task>
     */
    public function getTaskChildren(int $taskId): Collection
    {
        return Task::query()
            ->where('parent_id', $taskId)
            ->with(['assignee'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get a single task by ID with relationships.
     *
     * @param int $taskId
     * @return Task|null
     */
    public function getTask(int $taskId): ?Task
    {
        return Task::with(['assignee', 'project', 'parent', 'children.assignee'])
            ->find($taskId);
    }

    /**
     * Create a new task.
     *
     * @param array<string, mixed> $data
     * @param User $user
     * @return Task
     */
    public function createTask(array $data, User $user): Task
    {
        $taskData = [
            'project_id' => $data['projectId'],
            'parent_id' => $data['parentId'] ?? null,
            'assigned_to' => $data['assignedTo'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? TaskStatus::TODO->value,
            'estimated_hours' => $data['estimatedHours'] ?? null,
            'internal_reviewer_notes' => $data['internalReviewerNotes'] ?? null,
            'total_time_spent' => 0,
            'reopen_count' => 0,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        $task = Task::create($taskData);

        return $task->load(['assignee', 'project', 'parent', 'children']);
    }

    /**
     * Update an existing task.
     *
     * @param Task $task
     * @param array<string, mixed> $data
     * @param User $user
     * @return Task
     */
    public function updateTask(Task $task, array $data, User $user): Task
    {
        $updateData = [
            'updated_by' => $user->id,
        ];

        // Map camelCase to snake_case
        if (array_key_exists('parentId', $data)) {
            $updateData['parent_id'] = $data['parentId'];
        }

        if (array_key_exists('assignedTo', $data)) {
            $updateData['assigned_to'] = $data['assignedTo'];
        }

        if (isset($data['title'])) {
            $updateData['title'] = $data['title'];
        }

        if (array_key_exists('description', $data)) {
            $updateData['description'] = $data['description'];
        }

        if (isset($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        if (array_key_exists('estimatedHours', $data)) {
            $updateData['estimated_hours'] = $data['estimatedHours'];
        }

        if (array_key_exists('internalReviewerNotes', $data)) {
            $updateData['internal_reviewer_notes'] = $data['internalReviewerNotes'];
        }

        $task->update($updateData);

        return $task->fresh()->load(['assignee', 'project', 'parent', 'children']);
    }

    /**
     * Delete a task.
     *
     * @param Task $task
     * @return bool
     */
    public function deleteTask(Task $task): bool
    {
        return $task->delete();
    }

    /**
     * Get all tasks assigned to a specific user.
     *
     * @param int $userId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getTasksForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Task::query()
            ->where('assigned_to', $userId)
            ->with(['project', 'parent'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get task hierarchy (all ancestors and descendants).
     *
     * @param Task $task
     * @return array<string, mixed>
     */
    public function getTaskHierarchy(Task $task): array
    {
        return [
            'task' => $task->load(['assignee', 'project']),
            'ancestors' => $task->getAllAncestors()->load('assignee'),
            'children' => $this->loadAllDescendants($task),
        ];
    }

    /**
     * Recursively load all descendants of a task.
     *
     * @param Task $task
     * @return Collection<int, Task>
     */
    private function loadAllDescendants(Task $task): Collection
    {
        $children = $task->children()->with('assignee')->get();

        foreach ($children as $child) {
            $child->setRelation('children', $this->loadAllDescendants($child));
        }

        return $children;
    }
}
