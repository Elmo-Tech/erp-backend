<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\ChangeRequest;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectHistory;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Tasks\ActiveWorkService;
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
        $this->middleware('permission:view_dashboard|view_dashboard_overview');
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
                                    property: 'timeline',
                                    description: 'Upcoming project and task delivery dates',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'string', example: 'task-12'),
                                            new OA\Property(property: 'entityType', type: 'string', enum: ['project', 'task']),
                                            new OA\Property(property: 'title', type: 'string', example: 'Build reporting dashboard'),
                                            new OA\Property(property: 'startDate', type: 'string', format: 'date'),
                                            new OA\Property(property: 'endDate', type: 'string', format: 'date'),
                                            new OA\Property(property: 'projectId', type: 'integer', example: 3),
                                            new OA\Property(property: 'projectName', type: 'string', example: 'ERP rollout'),
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
        $totalProjects = Project::withoutGlobalScopes()->withoutTrashed()->count();
        $projectsThisMonth = Project::withoutGlobalScopes()->withoutTrashed()->where('created_at', '>=', $startOfMonth)->count();
        $projectsLastMonth = Project::withoutGlobalScopes()->withoutTrashed()->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();
        $projectsChange = $this->calculateChange($projectsThisMonth, $projectsLastMonth);

        // Work Hours Stats (all team)
        $workHoursThisMonth = (TimeEntry::where('start_time', '>=', $startOfMonth)
            ->whereNotNull('end_time')
            ->sum('duration_minutes') ?? 0) / 60;
        $workHoursLastMonth = (TimeEntry::whereBetween('start_time', [$startOfLastMonth, $endOfLastMonth])
            ->whereNotNull('end_time')
            ->sum('duration_minutes') ?? 0) / 60;
        $workHoursChange = $this->calculateChange($workHoursThisMonth, $workHoursLastMonth);

        // Active Clients (clients with active status)
        $activeClients = Client::where('status', 'active')->count();
        $clientsLastMonth = Client::where('status', 'active')
            ->where('created_at', '<', $startOfMonth)
            ->count();
        $clientsChange = $activeClients - $clientsLastMonth;
        $completedProjects = Project::withoutGlobalScopes()->withoutTrashed()
            ->where('status', ProjectStatus::COMPLETED->value)
            ->count();
        $openTasks = Task::whereIn('status', ['todo', 'in_progress'])->count();
        $overdueTasks = Task::whereNotNull('due_date')
            ->whereDate('due_date', '<', Carbon::today())
            ->where('status', '!=', 'completed')
            ->count();
        $employeesCount = User::where('is_employee', true)->where('is_active', true)->count();
        $todayAttendance = Attendance::whereDate('clock_in', Carbon::today())->count();

        // Recent Projects
        $recentProjects = $this->getRecentProjects();
        $recentActivities = $this->getRecentActivities();

        return ApiResponse::success([
            'role' => 'admin',
            'stats' => [
                'totalProjects' => $totalProjects,
                'projectsChange' => $projectsChange,
                'activeProjects' => Project::withoutGlobalScopes()->withoutTrashed()->whereIn('status', $activeStatuses)->count(),
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
            'timeline' => $this->getDeliveryTimeline(),
            'liveWork' => $this->liveWork(),
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
        $activeProjects = Project::withoutGlobalScopes()->withoutTrashed()
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
        $myActiveProjects = Project::withoutGlobalScopes()->withoutTrashed()
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
        $clockedIn = $todayAttendance?->clock_in !== null && $todayAttendance?->clock_out === null;
        $clockedOut = $todayAttendance?->clock_out !== null;
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
                'clockedOut' => $clockedOut,
                'todayHours' => round($todayHours, 1),
            ],
            'recentTasks' => $myRecentTasks,
            'projects' => Project::withoutGlobalScopes()->whereNull('projects.deleted_at')
                ->where(function ($query) use ($userId): void {
                    $query->whereHas('members', fn ($members) => $members->where('user_id', $userId))
                        ->orWhereHas('tasks', fn ($tasks) => $tasks->where('assigned_to', $userId)
                            ->orWhereHas('assignees', fn ($assignees) => $assignees->where('user_id', $userId)));
                })->orderBy('deadline')->get(['id', 'title', 'deadline', 'status'])
                ->map(fn ($project) => ['projectId' => $project->id, 'name' => $project->title, 'deadline' => $project->deadline?->toDateString()]),
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

    private function liveWork(): array
    {
        $work = app(ActiveWorkService::class);
        $timers = $work->openTimers();
        $workingIds = $timers->pluck('user_id')->unique();
        // Only the latest session can describe a person's current paused state.
        $paused = TimeEntry::with('user:id,name')
            ->whereNotNull('end_time')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('time_entries as later')
                    ->whereColumn('later.user_id', 'time_entries.user_id')->whereNotNull('later.end_time')
                    ->where(function ($later): void {
                        $later->whereColumn('later.end_time', '>', 'time_entries.end_time')
                            ->orWhere(function ($tie): void {
                                $tie->whereColumn('later.end_time', 'time_entries.end_time')
                                    ->whereColumn('later.id', '>', 'time_entries.id');
                            });
                    });
            })
            ->where('trackable_type', Task::class)
            ->whereIn('stop_reason', ['pause', 'hold'])
            ->whereNotIn('user_id', $workingIds)->get();
        $pausedIds = $paused->pluck('user_id');

        return [
            'activeTimerCount' => $timers->count(),
            'working' => $timers->map(function ($timer) use ($work): array {
                $target = $work->target($timer);

                return [
                    'userId' => $timer->user_id, 'name' => $timer->user?->name ?? 'Unavailable employee',
                    'taskId' => $timer->trackable_id, 'taskTitle' => $target['task']?->title ?? 'Deleted task #'.$timer->trackable_id,
                    'projectId' => $target['task']?->project_id, 'startedAt' => $timer->start_time->toISOString(),
                    'targetStatus' => $target['status'], 'targetMessage' => $target['message'],
                ];
            })->values(),
            'paused' => $paused->map(function ($timer) use ($work): array {
                $target = $work->target($timer);
                return [
                    'userId' => $timer->user_id, 'name' => $timer->user?->name ?? 'Unavailable employee',
                    'taskId' => $timer->trackable_id, 'taskTitle' => $target['task']?->title ?? 'Deleted task #'.$timer->trackable_id,
                    'projectId' => $target['task']?->project_id, 'reason' => $timer->stopReason()?->value,
                    'pausedAt' => $timer->end_time?->toISOString(), 'targetStatus' => $target['status'],
                ];
            })->values(),
            'idle' => User::where('is_employee', true)->where('is_active', true)->whereNotIn('id', $workingIds->merge($pausedIds))
                ->orderBy('name')->get(['id', 'name'])->map(fn ($user) => ['userId' => $user->id, 'name' => $user->name]),
        ];
    }

    /**
     * Get recent projects for admin
     */
    private function getRecentProjects(): array
    {
        return Project::withoutGlobalScopes()->withoutTrashed()
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

    /** @return array<int, array<string, int|string>> */
    private function getDeliveryTimeline(): array
    {
        return collect($this->getProjectTimelineEntries())
            ->concat($this->getTaskTimelineEntries())
            ->sortBy('endDate')
            ->take(20)
            ->values()
            ->toArray();
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function getProjectTimelineEntries(): array
    {
        return Project::withoutGlobalScopes()->withoutTrashed()
            ->select('id', 'title', 'deadline', 'created_at')
            ->whereNotNull('deadline')
            ->orderBy('deadline')
            ->limit(8)
            ->get()
            ->map(fn (Project $project) => $this->projectTimelineEntry($project))
            ->toArray();
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    private function getTaskTimelineEntries(): array
    {
        return Task::query()
            ->join('projects', 'projects.id', '=', 'tasks.project_id')
            ->select('tasks.id', 'tasks.project_id', 'tasks.title', 'tasks.start_date', 'tasks.due_date', 'projects.title as project_title')
            ->whereNotNull('tasks.due_date')
            ->whereNull('projects.deleted_at')
            ->orderBy('tasks.due_date')
            ->limit(12)
            ->get()
            ->map(fn (Task $task) => $this->taskTimelineEntry($task))
            ->toArray();
    }

    /**
     * @return array<string, int|string>
     */
    private function projectTimelineEntry(Project $project): array
    {
        $endDate = $project->deadline->copy()->startOfDay();
        $startDate = $this->timelineStartDate($project->created_at, $endDate);

        return [
            'id' => "project-{$project->id}",
            'entityType' => 'project',
            'title' => $project->title,
            'startDate' => $startDate->toDateString(),
            'endDate' => $endDate->toDateString(),
            'projectId' => $project->id,
            'projectName' => $project->title,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function taskTimelineEntry(Task $task): array
    {
        $endDate = Carbon::parse((string) $task->getRawOriginal('due_date'))->startOfDay();
        $rawStartDate = $task->getRawOriginal('start_date');
        $startDate = $this->timelineStartDate(is_string($rawStartDate) ? Carbon::parse($rawStartDate) : null, $endDate);

        return [
            'id' => "task-{$task->id}",
            'entityType' => 'task',
            'title' => $task->title,
            'startDate' => $startDate->toDateString(),
            'endDate' => $endDate->toDateString(),
            'projectId' => $task->project_id,
            'projectName' => (string) $task->getAttribute('project_title'),
        ];
    }

    private function timelineStartDate(?Carbon $requestedStartDate, Carbon $endDate): Carbon
    {
        $startDate = $requestedStartDate?->copy()->startOfDay() ?? $endDate->copy();

        return $startDate->greaterThan($endDate) ? $endDate->copy() : $startDate;
    }

    /**
     * Get recent activities for team members
     */
    private function getRecentActivitiesForTeam(array $teamMemberIds): array
    {
        $activities = collect();

        try {
            $recentTaskActivities = TaskActivity::with(['user:id,name', 'task:id,title'])
                ->whereIn('user_id', $teamMemberIds)
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($activity) {
                    return [
                        'activityId' => $activity->id,
                        'action' => $activity->description.($activity->task ? ': '.$activity->task->title : ''),
                        'user' => $activity->user?->name ?? 'System',
                        'time' => $activity->created_at->diffForHumans(),
                        'type' => 'task',
                        'timestamp' => $activity->created_at,
                    ];
                });
            $activities = $activities->merge($recentTaskActivities);
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
            $recentTaskActivities = TaskActivity::with('task:id,title')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($activity) {
                    return [
                        'activityId' => $activity->id,
                        'action' => $activity->description.($activity->task ? ': '.$activity->task->title : ''),
                        'user' => 'You',
                        'time' => $activity->created_at->diffForHumans(),
                        'type' => 'task',
                        'timestamp' => $activity->created_at,
                    ];
                });
            $activities = $activities->merge($recentTaskActivities);

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
            $recentTasks = TaskActivity::with(['user:id,name', 'task:id,title'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($activity) {
                    return [
                        'activityId' => $activity->id,
                        'action' => $activity->description.($activity->task ? ': '.$activity->task->title : ''),
                        'user' => $activity->user?->name ?? 'System',
                        'time' => $activity->created_at->diffForHumans(),
                        'type' => 'task',
                        'timestamp' => $activity->created_at,
                    ];
                });
            $activities = $activities->merge($recentTasks);

            $recentProjectActivities = ProjectHistory::with(['project:id,title', 'changedByUser:id,name'])
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get()
                ->map(function ($history) {
                    return [
                        'activityId' => $history->id + 100000,
                        'action' => $this->formatProjectHistoryActivity($history),
                        'user' => $history->changedByUser?->name ?? 'System',
                        'time' => $history->created_at->diffForHumans(),
                        'type' => 'project',
                        'timestamp' => $history->created_at,
                    ];
                });
            $activities = $activities->merge($recentProjectActivities);

            $recentFiles = ProjectFile::with(['project:id,title', 'createdBy:id,name'])
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get()
                ->map(function ($file) {
                    return [
                        'activityId' => $file->id + 200000,
                        'action' => 'File added: '.$file->name,
                        'user' => $file->createdBy?->name ?? 'System',
                        'time' => $file->created_at->diffForHumans(),
                        'type' => 'file',
                        'timestamp' => $file->created_at,
                    ];
                });
            $activities = $activities->merge($recentFiles);

            $recentChangeRequests = ChangeRequest::with(['project:id,title', 'requester:id,name'])
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get()
                ->map(function ($changeRequest) {
                    return [
                        'activityId' => $changeRequest->id + 300000,
                        'action' => 'Change request: '.$changeRequest->title,
                        'user' => $changeRequest->requester?->name ?? 'System',
                        'time' => $changeRequest->created_at->diffForHumans(),
                        'type' => 'change_request',
                        'timestamp' => $changeRequest->created_at,
                    ];
                });
            $activities = $activities->merge($recentChangeRequests);
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

    private function formatProjectHistoryActivity(ProjectHistory $history): string
    {
        $projectTitle = $history->project?->title ?? 'Unknown project';
        $fieldLabel = ucfirst(str_replace('_', ' ', $history->field_name));

        if ($history->old_value !== null && $history->new_value !== null) {
            return "{$fieldLabel} changed from {$history->old_value} to {$history->new_value}: {$projectTitle}";
        }

        if ($history->new_value !== null) {
            return "{$fieldLabel} updated to {$history->new_value}: {$projectTitle}";
        }

        return "{$fieldLabel} updated: {$projectTitle}";
    }
}
