<?php

namespace App\Services\Reporting;

use App\Modules\Project\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class QualityService
{
    /**
     * Calculate bounce rate statistics for a project.
     *
     * @return array{
     *     totalBounces: int,
     *     totalTasks: int,
     *     averageBounceRate: float
     * }
     */
    public function getProjectBounceRate(Project $project): array
    {
        $tasks = Task::where('project_id', $project->id)->get();

        $totalBounces = $tasks->sum('reopen_count');
        $totalTasks = $tasks->count();
        $averageBounceRate = $totalTasks > 0 ? $totalBounces / $totalTasks : 0;

        return [
            'totalBounces' => $totalBounces,
            'totalTasks' => $totalTasks,
            'averageBounceRate' => number_format($averageBounceRate, 2, '.', ''),
        ];
    }

    /**
     * Get bounce rate per developer (PRIMARY assignees only).
     * This ensures accurate quality metrics by only counting tasks developers are primarily responsible for.
     *
     * @param  array  $filters  ['dateFrom' => string, 'dateTo' => string]
     */
    public function getBounceRateByDeveloper(array $filters = []): Collection
    {
        $query = Task::with(['assignees.user'])
            ->whereHas('assignees', function ($q) {
                $q->where('is_primary', true); // Only primary assignees
            });

        // Apply date range filtering
        $this->applyDateFilters($query, $filters);

        $tasks = $query->get();

        // Group by primary developer and calculate bounce rates
        $developerStats = collect();

        foreach ($tasks as $task) {
            $primaryAssignees = $task->assignees()->where('is_primary', true)->get();

            foreach ($primaryAssignees as $assignee) {
                $userId = $assignee->user_id;
                $userName = $assignee->user->name;

                if (! $developerStats->has($userId)) {
                    $developerStats[$userId] = [
                        'userId' => $userId,
                        'userName' => $userName,
                        'tasks' => collect(),
                    ];
                }

                $developerStats[$userId]['tasks']->push($task);
            }
        }

        // Calculate statistics for each developer
        return $developerStats->map(function ($developer) {
            $tasks = $developer['tasks'];
            $totalBounces = $tasks->sum('reopen_count');
            $totalTasks = $tasks->count();
            $averageBounceRate = $totalTasks > 0 ? $totalBounces / $totalTasks : 0;

            return [
                'userId' => $developer['userId'],
                'userName' => $developer['userName'],
                'totalBounces' => $totalBounces,
                'totalTasks' => $totalTasks,
                'averageBounceRate' => number_format($averageBounceRate, 2, '.', ''),
            ];
        })->values();
    }

    /**
     * Get top N tasks with highest bounce rates (PRIMARY assignees only).
     *
     * @param  array  $filters  ['dateFrom' => string, 'dateTo' => string]
     */
    public function getTopBouncingTasks(int $limit = 10, array $filters = []): Collection
    {
        if ($limit <= 0) {
            throw new InvalidArgumentException('Limit must be greater than 0');
        }

        $query = Task::with(['project', 'assignees.user'])
            ->where('reopen_count', '>', 0);

        // Apply date range filtering
        $this->applyDateFilters($query, $filters);

        $tasks = $query->orderBy('reopen_count', 'desc')
            ->limit($limit)
            ->get();

        return $tasks->map(function ($task) {
            // Get primary assignee name
            $primaryAssignee = $task->assignees()->where('is_primary', true)->with('user')->first();
            $assigneeName = $primaryAssignee ? $primaryAssignee->user->name : 'Unassigned';

            return [
                'taskId' => $task->id,
                'title' => $task->title,
                'projectTitle' => $task->project ? $task->project->title : 'Unknown',
                'assigneeName' => $assigneeName,
                'reopenCount' => $task->reopen_count,
                'status' => $task->status->value,
            ];
        });
    }

    /**
     * Get quality overview for dashboard.
     *
     * @return array{
     *     overallBounceRate: float,
     *     qualityScore: float,
     *     totalBounces: int,
     *     tasksWithBounces: int
     * }
     */
    public function getQualityOverview(): array
    {
        $allTasks = Task::all();

        $totalBounces = $allTasks->sum('reopen_count');
        $totalTasks = $allTasks->count();
        $tasksWithBounces = $allTasks->where('reopen_count', '>', 0)->count();

        $overallBounceRate = $totalTasks > 0 ? $totalBounces / $totalTasks : 0;

        // Quality score is inverse of bounce rate (higher is better)
        // Scale from 0-100, where 100 is perfect (no bounces)
        $qualityScore = $totalTasks > 0 ? max(0, 100 - ($overallBounceRate * 100)) : 100;

        return [
            'overallBounceRate' => number_format($overallBounceRate, 2, '.', ''),
            'qualityScore' => number_format($qualityScore, 2, '.', ''),
            'totalBounces' => $totalBounces,
            'tasksWithBounces' => $tasksWithBounces,
        ];
    }

    /**
     * Apply date range filters to the query.
     */
    private function applyDateFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['dateFrom'])) {
            $query->where('created_at', '>=', $filters['dateFrom']);
        }

        if (! empty($filters['dateTo'])) {
            $query->where('created_at', '<=', $filters['dateTo']);
        }
    }
}
