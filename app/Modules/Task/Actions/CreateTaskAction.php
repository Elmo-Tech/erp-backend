<?php

namespace App\Modules\Task\Actions;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Modules\Task\Models\Tag;
use App\Modules\User\Models\User;
use App\Modules\Task\Models\Task;
use App\Modules\Task\Models\TaskActivity;
use App\Modules\Task\Models\TaskAssignee;
use App\Modules\Task\Services\TaskNotificationService;

class CreateTaskAction
{
    public function __construct(
        private TaskNotificationService $taskNotificationService
    ) {}

    public function execute(array $data, User $user): Task
    {
        $taskData = [
            'project_id' => $data['projectId'] ?? null,
            'parent_id' => $data['parentId'] ?? null,
            'assigned_to' => $data['assignedTo'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'acceptance_criteria' => $data['acceptanceCriteria'] ?? null,
            'status' => $data['status'] ?? TaskStatus::TODO->value,
            'type' => $data['type'] ?? TaskType::MAIN->value,
            'priority' => $data['priority'] ?? TaskPriority::MEDIUM->value,
            'task_category' => $data['taskCategory'] ?? null,
            'due_date' => $data['dueDate'] ?? null,
            'expected_resolution_date' => $data['expectedResolutionDate'] ?? null,
            'start_date' => $data['startDate'] ?? null,
            'sprint_number' => $data['sprintNumber'] ?? null,
            'estimated_hours' => $data['estimatedHours'] ?? null,
            'internal_reviewer_notes' => $data['internalReviewerNotes'] ?? null,
            'total_time_spent' => 0,
            'reopen_count' => 0,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ];

        $task = Task::create($taskData);

        if (! empty($data['tagIds'])) {
            $task->tags()->attach($data['tagIds']);
        }

        $taskType = $data['type'] ?? TaskType::MAIN->value;

        if ($taskType === TaskType::MAIN->value) {
            if (! empty($data['assignees'])) {
                foreach ($data['assignees'] as $assignee) {
                    TaskAssignee::create([
                        'task_id' => $task->id,
                        'user_id' => $assignee['userId'],
                        'role' => $assignee['role'] ?? 'developer',
                        'is_primary' => $assignee['isPrimary'] ?? false,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }
            }

            if (! empty($data['assignedTo'])) {
                $existingAssigneeIds = collect($data['assignees'] ?? [])->pluck('userId')->toArray();
                if (! in_array($data['assignedTo'], $existingAssigneeIds)) {
                    TaskAssignee::create([
                        'task_id' => $task->id,
                        'user_id' => $data['assignedTo'],
                        'role' => 'developer',
                        'is_primary' => true,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                }
            }
        } else {
            $mainTask = $this->findMainTask($task);
            $teamUserIds = $mainTask ? $mainTask->assignees()->pluck('user_id')->toArray() : [];

            if (! empty($data['assignees'])) {
                foreach ($data['assignees'] as $assignee) {
                    if (in_array($assignee['userId'], $teamUserIds)) {
                        TaskAssignee::create([
                            'task_id' => $task->id,
                            'user_id' => $assignee['userId'],
                            'role' => $assignee['role'] ?? 'developer',
                            'is_primary' => $assignee['isPrimary'] ?? false,
                            'created_by' => $user->id,
                            'updated_by' => $user->id,
                        ]);
                    }
                }
            }

            if (! empty($data['assignedTo'])) {
                $existingAssigneeIds = $task->assignees()->pluck('user_id')->toArray();

                if (! in_array($data['assignedTo'], $existingAssigneeIds)) {
                    if (in_array($data['assignedTo'], $teamUserIds)) {
                        TaskAssignee::create([
                            'task_id' => $task->id,
                            'user_id' => $data['assignedTo'],
                            'role' => 'developer',
                            'is_primary' => true,
                            'created_by' => $user->id,
                            'updated_by' => $user->id,
                        ]);
                    }
                }
            }
        }

        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'type' => TaskActivity::TYPE_CREATED,
            'description' => 'Created task',
        ]);

        $task->loadMissing(['assignees.user', 'project', 'createdBy']);
        $assignedUsers = $task->assignees
            ->map(fn ($assignee) => $assignee->user)
            ->filter(fn ($assigneeUser) => $assigneeUser instanceof User)
            ->values();

        $this->taskNotificationService->sendTaskAssigned($task, $user, $assignedUsers);

        return $task->load(['assignee', 'project', 'parent', 'children', 'tags', 'assignees.user']);
    }

    private function findMainTask(Task $task): ?Task
    {
        $current = $task;

        while ($current->parent_id !== null) {
            $parent = Task::find($current->parent_id);

            if (! $parent) {
                break;
            }

            if ($parent->type === TaskType::MAIN) {
                return $parent;
            }

            $current = $parent;
        }

        if ($current->type === TaskType::MAIN) {
            return $current;
        }

        return null;
    }
}
