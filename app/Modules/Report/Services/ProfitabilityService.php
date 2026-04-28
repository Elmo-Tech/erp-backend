<?php

namespace App\Modules\Report\Services;

use App\Modules\Project\Models\Project;
use App\Modules\TimeEntry\Models\TimeEntry;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ProfitabilityService
{
    /**
     * Default internal hourly cost when user has no cost set.
     */
    private const DEFAULT_INTERNAL_COST = 0.0;

    /**
     * Calculate profitability for a single project.
     *
     * @return array{
     *     projectId: int,
     *     title: string,
     *     budget: float,
     *     totalCost: float,
     *     profit: float,
     *     profitMargin: float
     * }
     */
    public function calculateProjectProfitability(Project $project): array
    {
        $totalCost = $this->calculateProjectCost($project);
        $budget = (float) $project->budget;
        $profit = $budget - $totalCost;
        $profitMargin = $budget > 0 ? ($profit / $budget) * 100 : 0;

        return [
            'projectId' => $project->id,
            'title' => $project->title,
            'budget' => number_format($budget, 2, '.', ''),
            'totalCost' => number_format($totalCost, 2, '.', ''),
            'profit' => number_format($profit, 2, '.', ''),
            'profitMargin' => number_format($profitMargin, 2, '.', ''),
        ];
    }

    /**
     * Calculate total cost for a project based on time entries and user costs.
     */
    public function calculateProjectCost(Project $project): float
    {
        // Get all time entries for the project and its tasks
        $projectTimeEntries = $this->getProjectTimeEntries($project);

        $totalCost = 0.0;

        foreach ($projectTimeEntries as $timeEntry) {
            $durationHours = $timeEntry->duration_minutes / 60;
            $userCost = $timeEntry->user->internal_hourly_cost ?? self::DEFAULT_INTERNAL_COST;
            $totalCost += $durationHours * $userCost;
        }

        return $totalCost;
    }

    /**
     * Get profitability report with filtering and pagination.
     *
     * @param  array  $filters  ['status' => string, 'dateFrom' => string, 'dateTo' => string]
     * @param  string  $sortBy  'profit' | 'margin' | 'budget'
     * @param  string  $sortDirection  'asc' | 'desc'
     */
    public function getProfitabilityReport(
        array $filters = [],
        string $sortBy = 'profit',
        string $sortDirection = 'desc',
        int $perPage = 15
    ): LengthAwarePaginator {
        // Validate sort parameters
        $allowedSortFields = ['profit', 'margin', 'budget'];
        if (! in_array($sortBy, $allowedSortFields)) {
            throw new InvalidArgumentException('Invalid sort field. Allowed: '.implode(', ', $allowedSortFields));
        }

        $allowedDirections = ['asc', 'desc'];
        if (! in_array($sortDirection, $allowedDirections)) {
            throw new InvalidArgumentException('Invalid sort direction. Allowed: asc, desc');
        }

        // Build query with filters
        $query = Project::with(['client', 'timeEntries.user']);

        // Apply status filter
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply date range filter
        if (! empty($filters['dateFrom'])) {
            $query->where('created_at', '>=', $filters['dateFrom']);
        }
        if (! empty($filters['dateTo'])) {
            $query->where('created_at', '<=', $filters['dateTo']);
        }

        // Get projects and calculate profitability
        $projects = $query->get();
        $profitabilityData = collect();

        foreach ($projects as $project) {
            $profitabilityData->push($this->calculateProjectProfitability($project));
        }

        // Sort the collection
        $profitabilityData = $profitabilityData->sortBy([
            [$sortBy, $sortDirection === 'desc' ? 'desc' : 'asc'],
        ])->values();

        // Manual pagination
        $currentPage = Paginator::resolveCurrentPage();
        $offset = ($currentPage - 1) * $perPage;
        $itemsForCurrentPage = $profitabilityData->slice($offset, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $itemsForCurrentPage,
            $profitabilityData->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
    }

    /**
     * Get overview statistics for dashboard.
     *
     * @return array{
     *     totalRevenue: float,
     *     totalProfit: float,
     *     overallMargin: float,
     *     activeProjects: int,
     *     completedProjects: int
     * }
     */
    public function getOverviewStats(): array
    {
        $projects = Project::with(['timeEntries.user'])->get();

        $totalRevenue = 0.0;
        $totalCost = 0.0;
        $activeProjects = 0;
        $completedProjects = 0;

        foreach ($projects as $project) {
            $budget = (float) $project->budget;
            $cost = $this->calculateProjectCost($project);

            $totalRevenue += $budget;
            $totalCost += $cost;

            // Count project statuses
            if ($project->status->value === 'active' || $project->status->value === 'in_progress') {
                $activeProjects++;
            } elseif ($project->status->value === 'completed') {
                $completedProjects++;
            }
        }

        $totalProfit = $totalRevenue - $totalCost;
        $overallMargin = $totalRevenue > 0 ? ($totalProfit / $totalRevenue) * 100 : 0;

        return [
            'totalRevenue' => number_format($totalRevenue, 2, '.', ''),
            'totalProfit' => number_format($totalProfit, 2, '.', ''),
            'overallMargin' => number_format($overallMargin, 2, '.', ''),
            'activeProjects' => $activeProjects,
            'completedProjects' => $completedProjects,
        ];
    }

    /**
     * Get all time entries for a project including task time entries.
     */
    private function getProjectTimeEntries(Project $project): Collection
    {
        // Get direct project time entries
        $projectTimeEntries = TimeEntry::with('user')
            ->where('trackable_type', $project->getMorphClass())
            ->where('trackable_id', $project->id)
            ->whereNotNull('end_time') // Only completed time entries
            ->get();

        // Get task time entries for all tasks in this project
        $taskTimeEntries = TimeEntry::with('user')
            ->where('trackable_type', \App\Modules\Task\Models\Task::class)
            ->whereIn('trackable_id', function ($query) use ($project) {
                $query->select('id')
                    ->from('tasks')
                    ->where('project_id', $project->id);
            })
            ->whereNotNull('end_time') // Only completed time entries
            ->get();

        return $projectTimeEntries->merge($taskTimeEntries);
    }
}
