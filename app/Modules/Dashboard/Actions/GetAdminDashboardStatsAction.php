<?php

namespace App\Modules\Dashboard\Actions;

use App\Enums\InvoiceStatus;
use App\Modules\Gamification\Models\PointTransaction;
use App\Modules\Attendance\Models\Attendance;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Project\Enums\ProjectStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Task\Models\Task;
use App\Modules\TimeEntry\Models\TimeEntry;
use App\Modules\User\Models\User;
use Carbon\Carbon;

class GetAdminDashboardStatsAction
{
    public function execute(): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        $activeStatuses = [
            ProjectStatus::ACTIVE->value,
            ProjectStatus::UI_UX->value,
            ProjectStatus::DEVELOPMENT->value,
            ProjectStatus::TESTING->value,
        ];

        $totalProjects = Project::withoutGlobalScopes()->count();
        $projectsThisMonth = Project::withoutGlobalScopes()->where('created_at', '>=', $startOfMonth)->count();
        $projectsLastMonth = Project::withoutGlobalScopes()->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();
        $projectsChange = $this->calculateChange($projectsThisMonth, $projectsLastMonth);

        $salesThisMonth = Invoice::where('status', InvoiceStatus::PAID)
            ->where('paid_at', '>=', $startOfMonth)
            ->sum('amount') ?? 0;
        $salesLastMonth = Invoice::where('status', InvoiceStatus::PAID)
            ->whereBetween('paid_at', [$startOfLastMonth, $endOfLastMonth])
            ->sum('amount') ?? 0;
        $salesChange = $this->calculateChange($salesThisMonth, $salesLastMonth);

        $workHoursThisMonth = (TimeEntry::where('start_time', '>=', $startOfMonth)
            ->whereNotNull('end_time')
            ->sum('duration_minutes') ?? 0) / 60;
        $workHoursLastMonth = (TimeEntry::whereBetween('start_time', [$startOfLastMonth, $endOfLastMonth])
            ->whereNotNull('end_time')
            ->sum('duration_minutes') ?? 0) / 60;
        $workHoursChange = $this->calculateChange($workHoursThisMonth, $workHoursLastMonth);

        $activeClients = Project::withoutGlobalScopes()
            ->whereIn('status', $activeStatuses)
            ->whereNotNull('client_id')
            ->distinct('client_id')
            ->count('client_id');
        $clientsLastMonth = Project::withoutGlobalScopes()
            ->whereIn('status', $activeStatuses)
            ->where('created_at', '<', $startOfMonth)
            ->whereNotNull('client_id')
            ->distinct('client_id')
            ->count('client_id');
        $clientsChange = $activeClients - $clientsLastMonth;

        $recentProjects = $this->getRecentProjects();
        $recentActivities = $this->getRecentActivities();

        return [
            'role' => 'admin',
            'stats' => [
                'totalProjects' => $totalProjects,
                'projectsChange' => $projectsChange,
                'monthlySales' => round($salesThisMonth, 2),
                'salesChange' => $salesChange,
                'workHours' => round($workHoursThisMonth),
                'workHoursChange' => $workHoursChange,
                'activeClients' => $activeClients,
                'clientsChange' => $clientsChange,
            ],
            'recentProjects' => $recentProjects,
            'recentActivities' => $recentActivities,
        ];
    }

    private function calculateChange(float|int $current, float|int $previous): float
    {
        if ($previous > 0) {
            return round((($current - $previous) / $previous) * 100, 1);
        }

        return $current > 0 ? 100 : 0;
    }

    private function getRecentProjects(): array
    {
        $projects = Project::withoutGlobalScopes()
            ->select('id', 'title', 'status')
            ->orderBy('updated_at', 'desc')
            ->limit(4)
            ->get();

        $taskCounts = Task::selectRaw('project_id, count(*) as total, sum(case when status = ? then 1 else 0 end) as completed', ['completed'])
            ->whereIn('project_id', $projects->pluck('id'))
            ->groupBy('project_id')
            ->pluck('total', 'project_id')
            ->toArray();

        $completedCounts = Task::selectRaw('project_id, count(*) as completed')
            ->where('status', 'completed')
            ->whereIn('project_id', $projects->pluck('id'))
            ->groupBy('project_id')
            ->pluck('completed', 'project_id')
            ->toArray();

        return $projects->map(function ($project) use ($taskCounts, $completedCounts) {
            $totalTasks = $taskCounts[$project->id] ?? 0;
            $completedTasks = $completedCounts[$project->id] ?? 0;
            $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

            return [
                'projectId' => $project->id,
                'name' => $project->title,
                'status' => $this->formatStatus($project->status),
                'progress' => $progress,
            ];
        })->toArray();
    }

    private function getRecentActivities(): array
    {
        $activities = collect();

        try {
            $recentTasks = Task::with('assignee')
                ->select('id', 'title', 'assignee_id', 'created_at')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(fn ($task) => [
                    'activityId' => $task->id,
                    'action' => 'New task added: '.$task->title,
                    'user' => $task->assignee?->name ?? 'System',
                    'time' => $task->created_at->diffForHumans(),
                    'type' => 'task',
                    'timestamp' => $task->created_at,
                ]);
            $activities = $activities->merge($recentTasks);

            $recentProjectActivities = Project::withoutGlobalScopes()
                ->select('id', 'title', 'updated_at')
                ->orderBy('updated_at', 'desc')
                ->limit(3)
                ->get()
                ->map(fn ($project) => [
                    'activityId' => $project->id + 1000,
                    'action' => 'Project updated: '.$project->title,
                    'user' => 'System',
                    'time' => $project->updated_at->diffForHumans(),
                    'type' => 'project',
                    'timestamp' => $project->updated_at,
                ]);
            $activities = $activities->merge($recentProjectActivities);

            $recentQuotations = \App\Modules\Quotation\Models\Quotation::with('lead')
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get()
                ->map(fn ($quotation) => [
                    'activityId' => $quotation->id + 2000,
                    'action' => 'Quotation created for '.($quotation->lead?->name ?? 'Unknown'),
                    'user' => 'System',
                    'time' => $quotation->created_at->diffForHumans(),
                    'type' => 'quotation',
                    'timestamp' => $quotation->created_at,
                ]);
            $activities = $activities->merge($recentQuotations);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Error fetching recent activities: '.$e->getMessage());
        }

        return $activities
            ->sortByDesc('timestamp')
            ->take(4)
            ->map(fn ($activity) => collect($activity)->except('timestamp')->toArray())
            ->values()
            ->toArray();
    }

    private function formatStatus($status): string
    {
        $statusValue = $status instanceof \BackedEnum ? $status->value : $status;

        return match ($statusValue) {
            'draft' => 'Draft',
            'active' => 'Active',
            'ui_ux' => 'UI/UX Phase',
            'development' => 'Development',
            'testing' => 'Testing',
            'completed' => 'Completed',
            'on_hold' => 'On Hold',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $statusValue)),
        };
    }
}
