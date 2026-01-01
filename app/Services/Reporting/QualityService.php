<?php

namespace App\Services\Reporting;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class QualityService
{
    /**
     * Calculate bounce rate statistics for a project.
     *
     * @param Project $project
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
     * Get bounce rate per developer.
     *
     * @param array $filters ['dateFrom' => string, 'dateTo' => string]
     * @return Collection
     */
    public function getBounceRateByDeveloper(array $filters = []): Collection
    {
        $query = Task::with('assignee')
            ->whereNotNull('assigned_to');

        // Apply date range filtering
        $this->applyDateFilters($query, $filters);

        $tasks = $query->get();

        // Group by developer and calculate bounce rates
        $developerStats = $tasks->groupBy('assigned_to')->map(function ($developerTasks, $userId) {
            $user = $developerTasks->first()->assignee;
            $totalBounces = $developerTasks->sum('reopen_count');
            $totalTasks = $developerTasks->count();
            $averageBounceRate = $totalTasks > 0 ? $totalBounces / $totalTasks : 0;

            return [
                'userId' => $userId,
                'userName' => $user ? $user->name : 'Unknown',
                'totalBounces' => $totalBounces,
                'totalTasks' => $totalTasks,
                'averageBounceRate' => number_format($averageBounceRate, 2, '.', ''),
            ];
        });

        return $developerStats->values();
    }

    /**
     * Get top N tasks with highest bounce rates.
     *
     * @param int $limit
     * @param array $filters ['dateFrom' => string, 'dateTo' => string]
     * @return Collection
     */
    public function getTopBouncingTasks(int $limit = 10, array $filters = []): Collection
    {
        if ($limit <= 0) {
            throw new InvalidArgumentException("Limit must be greater than 0");
        }

        $query = Task::with(['project', 'assignee'])
            ->where('reopen_count', '>', 0);

        // Apply date range filtering
        $this->applyDateFilters($query, $filters);

        $tasks = $query->orderBy('reopen_count', 'desc')
            ->limit($limit)
            ->get();

        return $tasks->map(function ($task) {
            return [
                'taskId' => $task->id,
                'title' => $task->title,
                'projectTitle' => $task->project ? $task->project->title : 'Unknown',
                'assigneeName' => $task->assignee ? $task->assignee->name : 'Unassigned',
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
     *
     * @param Builder $query
     * @param array $filters
     * @return void
     */
    private function applyDateFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['dateFrom'])) {
            $query->where('created_at', '>=', $filters['dateFrom']);
        }
        
        if (!empty($filters['dateTo'])) {
            $query->where('created_at', '<=', $filters['dateTo']);
        }
    }
}