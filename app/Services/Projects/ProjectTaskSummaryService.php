<?php

namespace App\Services\Projects;

use App\Models\LookupOption;
use App\Models\ProjectWorkflowStage;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Services\Tasks\TaskVisibility;
use Illuminate\Support\Collection;

class ProjectTaskSummaryService
{
    /** Summarize only tasks the viewer can access, in a single grouped query per page. */
    public function forProjects(Collection $projects, User $user): void
    {
        $visibility = app(TaskVisibility::class);
        if (! $visibility->canList($user)) return;

        $query = Task::query()->whereIn('project_id', $projects->modelKeys())
            ->selectRaw('project_id, workflow_stage_id, status, count(*) as task_count')
            ->groupBy('project_id', 'workflow_stage_id', 'status');
        $rows = $visibility->scope($query, $user)->get();
        $stages = ProjectWorkflowStage::whereIn('id', $rows->pluck('workflow_stage_id')->filter()->unique())->get()->keyBy('id');
        $fallbacks = WorkflowTemplate::where('is_default', true)->where('is_active', true)->first()?->stages->groupBy('category')->map->first();
        $options = LookupOption::ofType('task_status')->get()->keyBy('value');
        $summaries = [];
        foreach ($rows as $row) {
            $value = $row->status?->value ?? 'todo';
            $category = match ($value) { 'in_progress' => 'active', 'on_hold' => 'paused', 'review', 'testing' => 'review', 'completed' => 'done', default => 'planned' };
            $stage = $stages->get($row->workflow_stage_id) ?? $fallbacks?->get($category);
            $option = $options->get($value);
            $name = $stage?->name ?? $option?->label ?? ucwords(str_replace('_', ' ', $value));
            $key = $name.'|'.($stage?->color ?? $option?->color ?? '');
            $projectId = $row->project_id;
            $summaries[$projectId]['total'] = ($summaries[$projectId]['total'] ?? 0) + (int) $row->task_count;
            if (! isset($summaries[$projectId]['statuses'][$key])) {
                $summaries[$projectId]['statuses'][$key] = ['name' => $name, 'color' => $stage?->color ?? $option?->color, 'count' => 0, 'position' => $stage?->position ?? PHP_INT_MAX];
            }
            $summaries[$projectId]['statuses'][$key]['count'] += (int) $row->task_count;
        }
        foreach ($projects as $project) {
            $summary = $summaries[$project->id] ?? ['total' => 0, 'statuses' => []];
            $summary['statuses'] = collect($summary['statuses'])->sortBy('position')->values()->all();
            $project->setAttribute('visible_task_summary', $summary);
        }
    }
}
