<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Dashboard', description: 'Dashboard statistics endpoints')]
class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    #[OA\Get(
        path: '/api/v1/dashboard/overview',
        summary: 'Get dashboard overview',
        description: 'Get dashboard statistics including projects, sales, work hours, and recent activities',
        security: [['sanctum' => []]],
        tags: ['Dashboard'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dashboard overview retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Dashboard overview retrieved successfully'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'stats',
                                    properties: [
                                        new OA\Property(property: 'totalProjects', type: 'integer', example: 24),
                                        new OA\Property(property: 'projectsChange', type: 'number', example: 12.5),
                                        new OA\Property(property: 'monthlySales', type: 'number', example: 45231.00),
                                        new OA\Property(property: 'salesChange', type: 'number', example: 8.2),
                                        new OA\Property(property: 'workHours', type: 'integer', example: 1284),
                                        new OA\Property(property: 'workHoursChange', type: 'number', example: -3.0),
                                        new OA\Property(property: 'activeClients', type: 'integer', example: 18),
                                        new OA\Property(property: 'clientsChange', type: 'integer', example: 2),
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(
                                    property: 'recentProjects',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'projectId', type: 'integer', example: 1),
                                            new OA\Property(property: 'name', type: 'string', example: 'E-commerce Platform'),
                                            new OA\Property(property: 'status', type: 'string', example: 'in_progress'),
                                            new OA\Property(property: 'progress', type: 'integer', example: 75),
                                        ],
                                        type: 'object'
                                    )
                                ),
                                new OA\Property(
                                    property: 'recentActivities',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'activityId', type: 'integer', example: 1),
                                            new OA\Property(property: 'action', type: 'string', example: 'New task added'),
                                            new OA\Property(property: 'user', type: 'string', example: 'John Doe'),
                                            new OA\Property(property: 'time', type: 'string', example: '5 minutes ago'),
                                            new OA\Property(property: 'type', type: 'string', example: 'task'),
                                        ],
                                        type: 'object'
                                    )
                                ),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
        ]
    )]
    public function overview(Request $request): JsonResponse
    {
        try {
            $now = Carbon::now();
            $startOfMonth = $now->copy()->startOfMonth();
            $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
            $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

            // Active statuses for filtering
            $activeStatuses = [
                ProjectStatus::ACTIVE->value,
                ProjectStatus::UI_UX->value,
                ProjectStatus::DEVELOPMENT->value,
                ProjectStatus::TESTING->value,
            ];

            // Projects Stats
            $totalProjects = Project::withoutGlobalScopes()->count();
            $projectsThisMonth = Project::withoutGlobalScopes()->where('created_at', '>=', $startOfMonth)->count();
            $projectsLastMonth = Project::withoutGlobalScopes()->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();
            $projectsChange = $projectsLastMonth > 0 
                ? round((($projectsThisMonth - $projectsLastMonth) / $projectsLastMonth) * 100, 1)
                : ($projectsThisMonth > 0 ? 100 : 0);

            // Sales Stats (from quotations with status 'approved')
            $salesThisMonth = Quotation::where('status', 'approved')
                ->where('created_at', '>=', $startOfMonth)
                ->sum('final_price') ?? 0;
            $salesLastMonth = Quotation::where('status', 'approved')
                ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
                ->sum('final_price') ?? 0;
            $salesChange = $salesLastMonth > 0 
                ? round((($salesThisMonth - $salesLastMonth) / $salesLastMonth) * 100, 1)
                : ($salesThisMonth > 0 ? 100 : 0);

            // Work Hours Stats (from time entries)
            $workHoursThisMonth = (TimeEntry::where('start_time', '>=', $startOfMonth)
                ->whereNotNull('end_time')
                ->sum('duration') ?? 0) / 60;
            $workHoursLastMonth = (TimeEntry::whereBetween('start_time', [$startOfLastMonth, $endOfLastMonth])
                ->whereNotNull('end_time')
                ->sum('duration') ?? 0) / 60;
            $workHoursChange = $workHoursLastMonth > 0 
                ? round((($workHoursThisMonth - $workHoursLastMonth) / $workHoursLastMonth) * 100, 1)
                : ($workHoursThisMonth > 0 ? 100 : 0);

            // Active Clients Stats - count from projects directly
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

            // Recent Projects (last 4)
            $recentProjects = Project::withoutGlobalScopes()
                ->select('id', 'title', 'status')
                ->orderBy('updated_at', 'desc')
                ->limit(4)
                ->get()
                ->map(function ($project) {
                    // Calculate progress based on completed tasks
                    $totalTasks = Task::where('project_id', $project->id)->count();
                    $completedTasks = Task::where('project_id', $project->id)
                        ->where('status', 'completed')
                        ->count();
                    $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

                    return [
                        'projectId' => $project->id,
                        'name' => $project->title,
                        'status' => $this->formatStatus($project->status),
                        'progress' => $progress,
                    ];
                });

            // Recent Activities (from various sources)
            $recentActivities = $this->getRecentActivities();

            return ApiResponse::success([
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
            ], 'Dashboard overview retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Dashboard overview error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return ApiResponse::error('Failed to load dashboard data: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Format status for display
     */
    private function formatStatus($status): string
    {
        // Handle both enum and string values
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

    /**
     * Get recent activities from various sources
     */
    private function getRecentActivities(): array
    {
        $activities = collect();

        try {
            // Recent Tasks
            $recentTasks = Task::with('assignee')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($task) {
                    return [
                        'activityId' => $task->id,
                        'action' => 'New task added: ' . $task->title,
                        'user' => $task->assignee?->name ?? 'System',
                        'time' => $task->created_at->diffForHumans(),
                        'type' => 'task',
                        'timestamp' => $task->created_at,
                    ];
                });
            $activities = $activities->merge($recentTasks);

            // Recent Projects
            $recentProjectActivities = Project::withoutGlobalScopes()
                ->orderBy('updated_at', 'desc')
                ->limit(3)
                ->get()
                ->map(function ($project) {
                    return [
                        'activityId' => $project->id + 1000,
                        'action' => 'Project updated: ' . $project->title,
                        'user' => 'System',
                        'time' => $project->updated_at->diffForHumans(),
                        'type' => 'project',
                        'timestamp' => $project->updated_at,
                    ];
                });
            $activities = $activities->merge($recentProjectActivities);

            // Recent Quotations
            $recentQuotations = Quotation::with('lead')
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get()
                ->map(function ($quotation) {
                    return [
                        'activityId' => $quotation->id + 2000,
                        'action' => 'Quotation created for ' . ($quotation->lead?->name ?? 'Unknown'),
                        'user' => 'System',
                        'time' => $quotation->created_at->diffForHumans(),
                        'type' => 'quotation',
                        'timestamp' => $quotation->created_at,
                    ];
                });
            $activities = $activities->merge($recentQuotations);
        } catch (\Exception $e) {
            Log::warning('Error fetching recent activities: ' . $e->getMessage());
        }

        // Sort by timestamp and take top 4
        return $activities
            ->sortByDesc('timestamp')
            ->take(4)
            ->map(function ($activity) {
                unset($activity['timestamp']);
                return $activity;
            })
            ->values()
            ->toArray();
    }
}
