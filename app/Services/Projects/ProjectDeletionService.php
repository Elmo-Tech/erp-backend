<?php

namespace App\Services\Projects;

use App\Jobs\DeleteProjectUploads;
use App\Models\CodeReview;
use App\Models\InnovationLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDeliverable;
use App\Models\TaskTestScenario;
use App\Models\TimeEntry;
use App\Services\Tasks\WorkActivityPublisher;
use Illuminate\Support\Facades\DB;

class ProjectDeletionService
{
    public function permanentlyDelete(Project $project): void
    {
        DB::transaction(function () use ($project): void {
            $project = Project::withoutGlobalScopes()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $tasks = Task::withTrashed()->where('project_id', $project->id)->lockForUpdate()->get();
            $taskIds = $tasks->pluck('id');
            $paths = $project->files()->whereNotNull('file_path')->pluck('file_path')
                ->merge($tasks->pluck('cover_image_path')->filter())
                ->merge($tasks->flatMap(fn ($task) => $task->reference_images ?? []))->unique()->values()->all();
            $sources = [Project::class => [$project->id], Task::class => $taskIds];
            foreach ([
                CodeReview::class => 'code_reviews',
                TaskTestScenario::class => 'task_test_scenarios',
                TaskDeliverable::class => 'task_deliverables',
            ] as $model => $table) {
                $sources[$model] = DB::table($table)->whereIn('task_id', $taskIds)->pluck('id');
            }
            $innovationLogs = DB::table('innovation_logs')->where(fn ($query) => $query->where('project_id', $project->id)->orWhereIn('task_id', $taskIds));
            $sources[InnovationLog::class] = $innovationLogs->pluck('id');
            $entries = TimeEntry::where(function ($query) use ($project, $taskIds): void {
                $query->where(fn ($entry) => $entry->where('trackable_type', Project::class)->where('trackable_id', $project->id))
                    ->orWhere(fn ($entry) => $entry->where('trackable_type', Task::class)->whereIn('trackable_id', $taskIds));
            });
            $timerUsers = (clone $entries)->where(function ($query): void {
                $query->whereNull('end_time')->orWhereIn('stop_reason', ['pause', 'hold', 'connection_lost']);
            })->distinct()->pluck('user_id')->all();
            $entries->delete();
            // These relations use SET NULL, whereas task-owned child tables use CASCADE.
            $innovationLogs->delete();
            DB::table('contracts')->where('project_id', $project->id)->delete();
            $this->deleteNotifications($project->id, $taskIds->all());
            // Polymorphic work entries and point sources do not have FK cascades.
            $points = DB::table('point_transactions')->where(function ($query) use ($sources): void {
                foreach ($sources as $type => $ids) {
                    $query->orWhere(fn ($source) => $source->where('source_type', $type)->whereIn('source_id', $ids));
                }
            })->get();
            foreach ($points->groupBy('user_id') as $userId => $transactions) {
                DB::table('users')->where('id', $userId)->decrement('total_points', $transactions->sum('points'));
            }
            DB::table('point_transactions')->whereIn('id', $points->pluck('id'))->delete();
            DB::table('tasks')->whereIn('id', $taskIds)->delete();
            // Project-owned foreign keys cascade (files, wiki, members, workflow,
            // history, invoices, change requests, handovers and pins).
            $project->forceDelete();
            app(WorkActivityPublisher::class)->changed('project_deleted', $timerUsers);
            DB::afterCommit(function () use ($paths): void {
                DeleteProjectUploads::dispatch($paths);
            });
        });
    }

    private function deleteNotifications(int $projectId, array $taskIds): void
    {
        $taskIds = array_fill_keys(array_map('strval', $taskIds), true);
        // Laravel's notifications.data is TEXT, not a PostgreSQL JSON column.
        // Decode in bounded batches so legacy malformed/unrelated data is retained,
        // and numeric/string reference IDs behave consistently on every driver.
        DB::table('notifications')->select(['id', 'data'])->chunkById(500, function ($rows) use ($projectId, $taskIds): void {
            $ids = [];
            foreach ($rows as $row) {
                $data = json_decode($row->data, true);
                if (! is_array($data)) {
                    continue;
                }
                foreach (['project_id', 'projectId', 'task_id', 'taskId'] as $key) {
                    $value = $data[$key] ?? null;
                    if (! is_int($value) && ! is_string($value)) {
                        continue;
                    }
                    $matches = str_starts_with($key, 'project')
                        ? (string) $value === (string) $projectId
                        : isset($taskIds[(string) $value]);
                    if ($matches) {
                        $ids[] = $row->id;
                        break;
                    }
                }
            }
            if ($ids !== []) {
                DB::table('notifications')->whereIn('id', $ids)->delete();
            }
        });
    }
}
