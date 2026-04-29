<?php

namespace App\Modules\Dashboard\Actions;

use App\Enums\TaskStatus;
use App\Modules\Project\Models\Project;

class GetProjectTimelineAction
{
    public function execute(): array
    {
        return Project::withoutGlobalScopes()
            ->select(['id', 'title', 'status', 'deadline'])
            ->withCount([
                'tasks as total_tasks',
                'tasks as completed_tasks' => fn ($query) => $query->where('status', TaskStatus::COMPLETED->value),
            ])
            ->withMin('tasks as start_date', 'start_date')
            ->orderByRaw('deadline IS NULL, deadline ASC')
            ->limit(10)
            ->get()
            ->map(fn (Project $project): array => [
                'project_id' => $project->id,
                'title' => $project->title,
                'status' => $this->scalar($project->status),
                'start_date' => $project->start_date,
                'end_date' => $project->deadline?->toDateString(),
                'deadline' => $project->deadline?->toDateString(),
                'total_tasks' => (int) $project->total_tasks,
                'completed_tasks' => (int) $project->completed_tasks,
                'progress' => $project->total_tasks > 0
                    ? round(($project->completed_tasks / $project->total_tasks) * 100, 1)
                    : 0.0,
            ])
            ->all();
    }

    private function scalar(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
