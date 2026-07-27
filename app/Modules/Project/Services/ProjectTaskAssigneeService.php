<?php

namespace App\Modules\Project\Services;

use App\Enums\ProjectRole;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectTask;
use App\Modules\Settings\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class ProjectTaskAssigneeService
{
    /** @param array<int, int|string> $assigneeIds */
    public function sync(ProjectTask $task, Project $project, array $assigneeIds): Collection
    {
        $ids = collect($assigneeIds)->map(fn ($id): int => (int) $id)->unique()->values();
        $users = User::query()
            ->employees()
            ->where('is_active', true)
            ->whereKey($ids)
            ->get();

        if ($users->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'assigneeIds' => ['Every assignee must be an active employee.'],
            ]);
        }

        foreach ($users as $user) {
            $project->members()->firstOrCreate(
                ['user_id' => $user->id],
                ['role' => ProjectRole::DEVELOPER->value]
            );
        }

        $task->assignees()->sync(
            $ids->mapWithKeys(
                fn (int $id, int $position): array => [$id => ['position' => $position]]
            )->all()
        );
        $task->forceFill(['assignee_id' => $ids->first()])->save();

        return $users;
    }
}
