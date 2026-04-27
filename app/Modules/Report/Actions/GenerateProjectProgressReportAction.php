<?php

namespace App\Modules\Report\Actions;

use App\Modules\Project\Enums\ProjectStatus;
use App\Modules\Project\Models\Project;
use App\Modules\Task\Models\Task;
use App\Modules\TimeEntry\Models\TimeEntry;
use App\Modules\Invoice\Models\Invoice;
use App\Enums\InvoiceStatus;

class GenerateProjectProgressReportAction
{
    public function execute(?string $status = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $query = Project::withoutGlobalScopes()
            ->with(['client']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo);
        }

        $projects = $query->orderBy('updated_at', 'desc')->get();

        $projectIds = $projects->pluck('id')->toArray();

        $taskStats = Task::selectRaw('project_id, count(*) as total, sum(case when status = ? then 1 else 0 end) as completed, sum(case when status = ? then 1 else 0 end) as in_progress, sum(case when status = ? then 1 else 0 end) as todo', ['completed', 'in_progress', 'todo'])
            ->whereIn('project_id', $projectIds)
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        $timeStats = TimeEntry::selectRaw('project_id, sum(duration_minutes) as total_minutes')
            ->whereIn('project_id', $projectIds)
            ->whereNotNull('end_time')
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        $invoiceStats = Invoice::selectRaw('project_id, sum(amount) as total_invoiced, sum(case when status = ? then amount else 0 end) as total_paid', [InvoiceStatus::PAID->value])
            ->whereIn('project_id', $projectIds)
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        $report = $projects->map(function ($project) use ($taskStats, $timeStats, $invoiceStats) {
            $tasks = $taskStats->get($project->id);
            $time = $timeStats->get($project->id);
            $invoices = $invoiceStats->get($project->id);

            $totalTasks = $tasks->total ?? 0;
            $completedTasks = $tasks->completed ?? 0;
            $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

            return [
                'projectId' => $project->id,
                'title' => $project->title,
                'status' => $project->status instanceof \BackedEnum ? $project->status->value : $project->status,
                'client' => $project->client?->name ?? 'N/A',
                'tasks' => [
                    'total' => $totalTasks,
                    'completed' => $completedTasks,
                    'inProgress' => (int) ($tasks->in_progress ?? 0),
                    'todo' => (int) ($tasks->todo ?? 0),
                ],
                'progress' => $progress,
                'totalHours' => round(($time->total_minutes ?? 0) / 60, 1),
                'financials' => [
                    'totalInvoiced' => round($invoices->total_invoiced ?? 0, 2),
                    'totalPaid' => round($invoices->total_paid ?? 0, 2),
                ],
            ];
        });

        $activeStatuses = [
            ProjectStatus::ACTIVE->value,
            ProjectStatus::UI_UX->value,
            ProjectStatus::DEVELOPMENT->value,
            ProjectStatus::TESTING->value,
        ];

        return [
            'summary' => [
                'totalProjects' => $projects->count(),
                'activeProjects' => $projects->whereIn('status', $activeStatuses)->count(),
                'completedProjects' => $projects->where('status', ProjectStatus::COMPLETED->value)->count(),
            ],
            'projects' => $report,
        ];
    }
}
