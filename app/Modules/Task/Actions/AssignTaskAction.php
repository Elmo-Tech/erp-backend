<?php

namespace App\Modules\Task\Actions;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Modules\User\Models\User;
use App\Modules\Task\Models\Task;
use App\Modules\Task\Models\TaskAssignee;
use App\Modules\Task\Models\TaskActivity;
use App\Modules\Task\Services\TaskNotificationService;

class AssignTaskAction
{
    public function __construct(
        private TaskNotificationService $taskNotificationService
    ) {}

    public function execute(Task $task, int $userId, string $role, bool $isPrimary, User $performer): TaskAssignee
    {
        if ($task->type !== TaskType::MAIN) {
            $mainTask = $this->findMainTask($task);
            if ($mainTask) {
                $isInTeam = $mainTask->assignees()->where('user_id', $userId)->exists();
                if (! $isInTeam) {
                    throw new \InvalidArgumentException(
                        'This user is not part of the project team. Add them to the Main Task first.'
                    );
                }
            }
        }

        $existingAssignee = $task->assignees()->where('user_id', $userId)->first();
        if ($existingAssignee) {
            throw new \InvalidArgumentException(
                'This user is already assigned to this task.'
            );
        }

        $assignee = TaskAssignee::create([
            'task_id' => $task->id,
            'user_id' => $userId,
            'role' => $role,
            'is_primary' => $isPrimary,
            'created_by' => $performer->id,
            'updated_by' => $performer->id,
        ]);

        $user = User::find($userId);
        TaskActivity::logAssigneeAdded($task, $performer, $user, $role);

        $this->taskNotificationService->sendTaskAssigned(
            $task->loadMissing(['project', 'assignees.user', 'createdBy']),
            $performer,
            collect([$user])->filter(fn ($assigneeUser) => $assigneeUser instanceof User)
        );

        if ($task->type === TaskType::MAIN) {
            $this->propagateAssigneeToWeeklyTasks($task, $userId, $role, $isPrimary, $performer);
        }

        return $assignee->load('user');
    }

    public function remove(Task $task, int $userId, User $performer): bool
    {
        $assignee = $task->assignees()->where('user_id', $userId)->first();
        if (! $assignee) {
            return false;
        }

        $user = User::find($userId);
        TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $performer->id,
            'type' => TaskActivity::TYPE_ASSIGNEE_REMOVED,
            'description' => "Removed {$user->name} from task",
            'metadata' => ['assignee_id' => $userId, 'assignee_name' => $user->name],
        ]);

        if ($task->type === TaskType::MAIN) {
            $this->removeAssigneeFromWeeklyTasks($task, $userId);
        }

        return $assignee->delete();
    }

    private function propagateAssigneeToWeeklyTasks(Task $task, int $userId, string $role, bool $isPrimary, User $performer): void
    {
        $weeklyTasks = $task->children()->where('type', TaskType::WEEKLY)->get();

        foreach ($weeklyTasks as $weeklyTask) {
            $existingAssignee = $weeklyTask->assignees()->where('user_id', $userId)->first();

            if (! $existingAssignee) {
                TaskAssignee::create([
                    'task_id' => $weeklyTask->id,
                    'user_id' => $userId,
                    'role' => $role,
                    'is_primary' => $isPrimary,
                    'created_by' => $performer->id,
                    'updated_by' => $performer->id,
                ]);
            }
        }
    }

    private function removeAssigneeFromWeeklyTasks(Task $task, int $userId): void
    {
        $weeklyTasks = $task->children()->where('type', TaskType::WEEKLY)->get();

        foreach ($weeklyTasks as $weeklyTask) {
            $weeklyTask->assignees()->where('user_id', $userId)->delete();
        }
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
