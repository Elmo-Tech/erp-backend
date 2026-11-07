<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectWorkflowStage;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GlobalWorkflowService
{
    public function snapshot(): array
    {
        $template = WorkflowTemplate::where('is_default', true)->where('is_active', true)->with('stages')->firstOrFail();
        $stages = DB::table('project_workflow_stages')->orderBy('id')->get();
        $counts = DB::table('tasks')->whereNotNull('workflow_stage_id')->selectRaw('workflow_stage_id, count(*) as total')->groupBy('workflow_stage_id')->pluck('total', 'workflow_stage_id');
        $sources = [];
        foreach ($stages as $stage) {
            $key = hash('sha256', $stage->slug.'|'.$stage->category);
            if (! isset($sources[$key])) $sources[$key] = ['key' => $key, 'name' => $stage->name, 'slug' => $stage->slug, 'category' => $stage->category, 'projects' => 0, 'tasks' => 0];
            $sources[$key]['projects']++;
            $sources[$key]['tasks'] += (int) ($counts[$stage->id] ?? 0);
        }
        $definitions = $template->stages->map(fn ($s) => ['stageId' => $s->id, 'name' => $s->name, 'slug' => $s->slug, 'color' => $s->color, 'category' => $s->category, 'isTerminal' => $s->is_terminal])->values()->all();
        return ['stages' => $definitions, 'sources' => array_values($sources), 'revision' => hash('sha256', json_encode([$definitions, $stages, $counts], JSON_THROW_ON_ERROR))];
    }

    public function apply(array $data, User $actor): void
    {
        DB::transaction(function () use ($data, $actor): void {
            $template = WorkflowTemplate::where('is_default', true)->where('is_active', true)->lockForUpdate()->firstOrFail();
            DB::table('projects')->orderBy('id')->lockForUpdate()->get(['id']);
            DB::table('project_workflow_stages')->orderBy('id')->lockForUpdate()->get(['id']);
            DB::table('tasks')->whereNotNull('workflow_stage_id')->orderBy('id')->lockForUpdate()->get(['id']);
            $snapshot = $this->snapshot();
            if (! hash_equals($snapshot['revision'], $data['revision'])) throw ValidationException::withMessages(['revision' => 'Workflow or task counts changed. Reload and review again.']);
            $definitions = collect($data['stages'])->keyBy('slug');
            foreach ($snapshot['sources'] as $source) {
                $target = $data['mapping'][$source['key']] ?? null;
                if (! is_string($target) || ! $definitions->has($target)) throw ValidationException::withMessages(['mapping' => 'Choose a replacement for every existing stage.']);
            }
            if ($definitions->map(fn ($stage) => mb_strtolower(trim($stage['name'])))->unique()->count() !== $definitions->count()) throw ValidationException::withMessages(['stages' => 'Stage names must be unique.']);
            foreach (['active', 'paused', 'done'] as $category) {
                if ($definitions->where('category', $category)->count() !== 1) throw ValidationException::withMessages(['stages' => "Exactly one {$category} stage is required."]);
            }
            if ($definitions->where('category', 'planned')->isEmpty()) throw ValidationException::withMessages(['stages' => 'At least one planned stage is required.']);
            foreach ($definitions as $definition) {
                if ((bool) $definition['isTerminal'] !== in_array($definition['category'], ['done', 'cancelled'], true)) throw ValidationException::withMessages(['stages' => 'Done and cancelled stages must be terminal; other stages must not be terminal.']);
            }
            $oldStageDefinitions = $template->stages()->get()->toArray();
            $oldProjectStages = DB::table('project_workflow_stages')->orderBy('id')->get()->toArray();
            DB::table('project_workflow_stages')->whereNotNull('workflow_template_stage_id')->update(['workflow_template_stage_id' => null]);
            $template->stages()->delete();
            $templateStages = [];
            foreach ($data['stages'] as $position => $item) {
                $templateStages[$item['slug']] = $template->stages()->create(['name' => $item['name'], 'slug' => $item['slug'], 'color' => $item['color'], 'category' => $item['category'], 'position' => $position, 'is_terminal' => $item['isTerminal']]);
            }
            $moves = [];
            foreach (Project::withoutGlobalScopes()->orderBy('id')->get() as $project) {
                $old = ProjectWorkflowStage::where('project_id', $project->id)->orderBy('id')->get();
                foreach ($old as $stage) {
                    DB::table('project_workflow_stages')->where('id', $stage->id)->update(['slug' => 'temporary_'.$stage->id, 'position' => $stage->position + 1000]);
                }
                $targets = [];
                foreach ($data['stages'] as $position => $item) {
                    $target = $project->workflowStages()->create(['workflow_template_stage_id' => $templateStages[$item['slug']]->id, 'name' => $item['name'], 'slug' => $item['slug'], 'color' => $item['color'], 'category' => $item['category'], 'position' => $position, 'is_terminal' => $item['isTerminal']]);
                    $targets[$item['slug']] = $target;
                }
                foreach ($old as $source) {
                    $sourceKey = hash('sha256', $source->slug.'|'.$source->category);
                    $target = $targets[$data['mapping'][$sourceKey]];
                    $tasks = Task::withTrashed()->where('workflow_stage_id', $source->id)->orderBy('pipeline_position')->orderBy('id')->get();
                    foreach ($tasks as $task) {
                        $moves[] = ['taskId' => $task->id, 'projectId' => $project->id, 'from' => $source->slug, 'fromCategory' => $source->category, 'to' => $target->slug, 'toCategory' => $target->category, 'status' => $task->status?->value, 'completedAt' => $task->completed_at?->toISOString(), 'holdReason' => $task->hold_reason];
                        if ($task->deleted_at === null && $task->is_pipeline_task && $source->category !== $target->category) {
                            app(ProjectWorkflowService::class)->moveTask($project, $task, $target, $actor);
                        } else {
                            $updates = ['workflow_stage_id' => $target->id, 'pipeline_position' => ((int) Task::withTrashed()->where('workflow_stage_id', $target->id)->max('pipeline_position')) + 1];
                            if ($source->category !== $target->category) {
                                $updates['status'] = match ($target->category) { 'active' => 'in_progress', 'paused' => 'on_hold', 'review' => 'review', 'done' => 'completed', default => 'todo' };
                                $updates['completed_at'] = $target->category === 'done' ? now() : null;
                                $updates['hold_reason'] = $target->category === 'paused' ? ($task->hold_reason ?: 'Moved to On Hold') : null;
                            }
                            DB::table('tasks')->where('id', $task->id)->update($updates);
                        }
                    }
                    $source->delete();
                }
            }
            app(AuditService::class)->log('projects', 'global_workflow_updated', $template, ['previous' => $oldStageDefinitions, 'previousProjectStages' => $oldProjectStages, 'mapping' => $data['mapping'], 'sources' => $snapshot['sources'], 'taskChanges' => $moves, 'new' => $data['stages']], 'warning', 'Global workflow updated');
        });
    }
}
