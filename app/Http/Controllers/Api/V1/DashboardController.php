<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        description: 'Get dashboard operational statistics including projects, tasks, work hours, attendance, and recent activities',
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
            $user = $request->user();
            $now = Carbon::now();
            $startOfMonth = $now->copy()->startOfMonth();
            $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
            $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

            // Determine user role for different stats
            $isAdmin = $user->hasAnyRole(['super-admin', 'admin']);
            $isManager = $user->hasRole('manager');
            $isDeveloper = $user->hasRole('developer');

            if ($isAdmin) {
                return $this->getAdminDashboard($startOfMonth, $startOfLastMonth, $endOfLastMonth);
            } elseif ($isManager) {
                return $this->getManagerDashboard($user, $startOfMonth, $startOfLastMonth, $endOfLastMonth);
            } else {
                // Developer or other roles
                return $this->getDeveloperDashboard($user, $startOfMonth, $startOfLastMonth, $endOfLastMonth);
            }
        } catch (\Exception $e) {
            Log::error('Dashboard overview error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to load dashboard data: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get Admin Dashboard - Full company overview
     */
    private function getAdminDashboard($startOfMonth, $startOfLastMonth, $endOfLastMonth): JsonResponse
    {
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
        $projectsChange = $this->calculateChange($projectsThisMonth, $projectsLastMonth);

        // Work Hours Stats (all team)
        $workHoursThisMonth = (TimeEntry::where('start_time', '>=', $startOfMonth)
            ->whereNotNull('end_time')
            ->sum('duration_minutes') ?? 0) / 60;
        $workHoursLastMonth = (TimeEntry::whereBetween('start_time', [$startOfLastMonth, $endOfLastMonth])
            ->whereNotNull('end_time')
            ->sum('duration_minutes') ?? 0) / 60;
        $workHoursChange = $this->calculateChange($workHoursThisMonth, $workHoursLastMonth);

        // Active Clients
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
        $completedProjects = Project::withoutGlobalScopes()
            ->where('status', ProjectStatus::COMPLETED->value)
            ->count();
        $openTasks = Task::whereIn('status', ['todo', 'in_progress'])->count();
        $overdueTasks = Task::whereNotNull('due_date')
            ->whereDate('due_date', '<', Carbon::today())
            ->where('status', '!=', 'completed')
            ->count();
        $employeesCount = User::role(['admin', 'manager', 'developer'])->count();
        $todayAttendance = Attendance::whereDate('clock_in', Carbon::today())->count();

        // Recent Projects
        $recentProjects = $this->getRecentProjects();
        $recentActivities = $this->getRecentActivities();

        return ApiResponse::success([
            'role' => 'admin',
            'stats' => [
                'totalProjects' => $totalProjects,
                'projectsChange' => $projectsChange,
                'activeProjects' => Project::withoutGlobalScopes()->whereIn('status', $activeStatuses)->count(),
                'completedProjects' => $completedProjects,
                'workHours' => round($workHoursThisMonth),
                'workHoursChange' => $workHoursChange,
                'activeClients' => $activeClients,
                'clientsChange' => $clientsChange,
                'openTasks' => $openTasks,
                'overdueTasks' => $overdueTasks,
                'employeesCount' => $employeesCount,
                'todayAttendance' => $todayAttendance,
            ],
            'recentProjects' => $recentProjects,
            'recentActivities' => $recentActivities,
        ], 'Dashboard overview retrieved successfully');
    }

    /**
     * Get Manager Dashboard - Team overview
     */
    private function getManagerDashboard($user, $startOfMonth, $startOfLastMonth, $endOfLastMonth): JsonResponse
    {
        $activeStatuses = [
            ProjectStatus::ACTIVE->value,
            ProjectStatus::UI_UX->value,
            ProjectStatus::DEVELOPMENT->value,
            ProjectStatus::TESTING->value,
        ];

        // Get team members (users managed by this manager or all developers if no specific team)
        $teamMemberIds = User::role('developer')->pluck('id')->toArray();

        // Active Projects (projects with active status)
        $activeProjects = Project::withoutGlobalScopes()
            ->whereIn('status', $activeStatuses)
            ->count();

        // Team Tasks Stats
        $totalTasks = Task::whereIn('assigned_to', $teamMemberIds)->count();
        $completedTasks = Task::whereIn('assigned_to', $teamMemberIds)
            ->where('status', 'completed')
            ->count();
        $pendingTasks = Task::whereIn('assigned_to', $teamMemberIds)
            ->whereIn('status', ['todo', 'in_progress'])
            ->count();

        // Team Work Hours
        $teamHoursThisMonth = (TimeEntry::whereIn('user_id', $teamMemberIds)
            ->where('start_time', '>=', $startOfMonth)
            ->whereNotNull('end_time')
            ->sum('duration_minutes') ?? 0) / 60;
        $teamHoursLastMonth = (TimeEntry::whereIn('user_id', $teamMemberIds)
            ->whereBetween('start_time', [$startOfLastMonth, $endOfLastMonth])
            ->whereNotNull('end_time')
            ->sum('duration_minutes') ?? 0) / 60;
        $teamHoursChange = $this->calculateChange($teamHoursThisMonth, $teamHoursLastMonth);

        // Team Members Count
        $teamMembersCount = count($teamMemberIds);

        // Recent Team Tasks
        $recentTasks = Task::with(['assignee', 'project'])
            ->whereIn('assigned_to', $teamMemberIds)
            ->orderBy('updated_at', 'desc')
            ->limit(4)
            ->get()
            ->map(function ($task) {
                return [
                    'taskId' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'assignee' => $task->assignee?->name ?? 'Unassigned',
                    'project' => $task->project?->title ?? 'No Project',
                ];
        });

        $recentActivities = $this->getRecentActivitiesForTeam($teamMemberIds);

        return ApiResponse::success([
            'role' => 'manager',
            'stats' => [
                'activeProjects' => $activeProjects,
                'totalTasks' => $totalTasks,
                'completedTasks' => $completedTasks,
                'pendingTasks' => $pendingTasks,
                'teamHours' => round($teamHoursThisMonth),
                'teamHoursChange' => $teamHoursChange,
                'teamMembers' => $teamMembersCount,
            ],
            'recentTasks' => $recentTasks,
            'recentActivities' => $recentActivities,
        ], 'Dashboard overview retrieved successfully');
    }

    /**
     * Get Developer Dashboard - Personal stats only
     */
    private function getDeveloperDashboard($user, $startOfMonth, $startOfLastMonth, $endOfLastMonth): JsonResponse
    {
        $userId = $user->id;

        // My Tasks Stats
        $myTotalTasks = Task::where('assigned_to', $userId)->count();
        $myCompletedTasks = Task::where('assigned_to', $userId)
            ->where('status', 'completed')
            ->count();
        $myInProgressTasks = Task::where('assigned_to', $userId)
            ->where('status', 'in_progress')
            ->count();
        $myTodoTasks = Task::where('assigned_to', $userId)
            ->where('status', 'todo')
            ->count();

        // My Work Hours
        $myHoursThisMonth = (TimeEntry::where('user_id', $userId)
            ->where('start_time', '>=', $startOfMonth)
            ->whereNotNull('end_time')
            ->sum('duration_minutes') ?? 0) / 60;
        $myHoursLastMonth = (TimeEntry::where('user_id', $userId)
            ->whereBetween('start_time', [$startOfLastMonth, $endOfLastMonth])
            ->whereNotNull('end_time')
            ->sum('duration_minutes') ?? 0) / 60;
        $myHoursChange = $this->calculateChange($myHoursThisMonth, $myHoursLastMonth);

        // My Projects (projects I have tasks in)
        $myProjectIds = Task::where('assigned_to', $userId)
            ->distinct('project_id')
            ->pluck('project_id');
        $myActiveProjects = Project::withoutGlobalScopes()
            ->whereIn('id', $myProjectIds)
            ->whereIn('status', [
                ProjectStatus::ACTIVE->value,
                ProjectStatus::UI_UX->value,
                ProjectStatus::DEVELOPMENT->value,
                ProjectStatus::TESTING->value,
            ])
            ->count();

        // Today's attendance
        $todayAttendance = Attendance::where('user_id', $userId)
            ->whereDate('clock_in', Carbon::today())
            ->first();
        $clockedIn = $todayAttendance !== null;
        $todayHours = 0;
        if ($todayAttendance) {
            $todayHours = (TimeEntry::where('user_id', $userId)
                ->whereDate('start_time', Carbon::today())
                ->whereNotNull('end_time')
                ->sum('duration_minutes') ?? 0) / 60;
        }

        // My Recent Tasks
        $myRecentTasks = Task::with('project')
            ->where('assigned_to', $userId)
            ->orderBy('updated_at', 'desc')
            ->limit(4)
            ->get()
            ->map(function ($task) {
                return [
                    'taskId' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'project' => $task->project?->title ?? 'No Project',
                    'priority' => $task->priority ?? 'medium',
                ];
            });

        // My Recent Activities
        $myRecentActivities = $this->getRecentActivitiesForUser($userId);

        return ApiResponse::success([
            'role' => 'developer',
            'stats' => [
                'myTotalTasks' => $myTotalTasks,
                'myCompletedTasks' => $myCompletedTasks,
                'myInProgressTasks' => $myInProgressTasks,
                'myTodoTasks' => $myTodoTasks,
                'myHoursThisMonth' => round($myHoursThisMonth, 1),
                'myHoursChange' => $myHoursChange,
                'myActiveProjects' => $myActiveProjects,
                'clockedIn' => $clockedIn,
                'todayHours' => round($todayHours, 1),
            ],
            'recentTasks' => $myRecentTasks,
            'recentActivities' => $myRecentActivities,
        ], 'Dashboard overview retrieved successfully');
    }

    /**
     * Calculate percentage change
     */
    private function calculateChange($current, $previous): float
    {
        if ($previous > 0) {
            return round((($current - $previous) / $previous) * 100, 1);
        }

        return $current > 0 ? 100 : 0;
    }

    /**
     * Get recent projects for admin
     */
    private function getRecentProjects(): array
    {
        return Project::withoutGlobalScopes()
            ->select('id', 'title', 'status')
            ->orderBy('updated_at', 'desc')
            ->limit(4)
            ->get()
            ->map(function ($project) {
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
            })
            ->toArray();
    }

    /**
     * Get recent activities for team members
     */
    private function getRecentActivitiesForTeam(array $teamMemberIds): array
    {
        $activities = collect();

        try {
            $recentTasks = Task::with('assignee')
                ->whereIn('assigned_to', $teamMemberIds)
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($task) {
                    return [
                        'activityId' => $task->id,
                        'action' => 'Task updated: '.$task->title,
                        'user' => $task->assignee?->name ?? 'System',
                        'time' => $task->updated_at->diffForHumans(),
                        'type' => 'task',
                        'timestamp' => $task->updated_at,
                    ];
                });
            $activities = $activities->merge($recentTasks);
        } catch (\Exception $e) {
            Log::warning('Error fetching team activities: '.$e->getMessage());
        }

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

    /**
     * Get recent activities for a specific user
     */
    private function getRecentActivitiesForUser(int $userId): array
    {
        $activities = collect();

        try {
            // My recent tasks
            $recentTasks = Task::where('assigned_to', $userId)
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($task) {
                    return [
                        'activityId' => $task->id,
                        'action' => 'Task: '.$task->title,
                        'user' => 'You',
                        'time' => $task->updated_at->diffForHumans(),
                        'type' => 'task',
                        'timestamp' => $task->updated_at,
                    ];
                });
            $activities = $activities->merge($recentTasks);

            // My recent time entries
            $recentTimeEntries = TimeEntry::where('user_id', $userId)
                ->whereNotNull('end_time')
                ->orderBy('end_time', 'desc')
                ->limit(3)
                ->get()
                ->map(function ($entry) {
                    $duration = round($entry->duration_minutes / 60, 1);

                    return [
                        'activityId' => $entry->id + 5000,
                        'action' => "Logged {$duration}h of work",
                        'user' => 'You',
                        'time' => $entry->end_time->diffForHumans(),
                        'type' => 'time',
                        'timestamp' => $entry->end_time,
                    ];
                });
            $activities = $activities->merge($recentTimeEntries);
        } catch (\Exception $e) {
            Log::warning('Error fetching user activities: '.$e->getMessage());
        }

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
                        'action' => 'New task added: '.$task->title,
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
                        'action' => 'Project updated: '.$project->title,
                        'user' => 'System',
                        'time' => $project->updated_at->diffForHumans(),
                        'type' => 'project',
                        'timestamp' => $project->updated_at,
                    ];
                });
            $activities = $activities->merge($recentProjectActivities);

            // Recent Clients
            $recentClients = Client::orderBy('updated_at', 'desc')
                ->limit(3)
                ->get()
                ->map(function ($client) {
                    return [
                        'activityId' => $client->id + 2000,
                        'action' => 'Client updated: '.$client->name,
                        'user' => 'System',
                        'time' => $client->updated_at->diffForHumans(),
                        'type' => 'client',
                        'timestamp' => $client->updated_at,
                    ];
                });
            $activities = $activities->merge($recentClients);
        } catch (\Exception $e) {
            Log::warning('Error fetching recent activities: '.$e->getMessage());
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
