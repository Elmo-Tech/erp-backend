<?php

namespace App\Modules\Dashboard\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Actions\GetFinancialStatsAction;
use App\Modules\Dashboard\Actions\GetPersonalBlockersAction;
use App\Modules\Dashboard\Actions\GetPersonalTasksAction;
use App\Modules\Dashboard\Actions\GetProjectTimelineAction;
use App\Modules\Dashboard\Actions\GetTeamStatsAction;
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
        description: 'Get dashboard statistics including projects, sales, work hours, and recent activities',
        security: [['sanctum' => []]],
        tags: ['Dashboard'],
        responses: [
            new OA\Response(response: 200, description: 'Dashboard overview retrieved successfully'),
        ]
    )]
    public function index(
        Request $request,
        GetFinancialStatsAction $financialStats,
        GetProjectTimelineAction $projectTimeline,
        GetTeamStatsAction $teamStats,
        GetPersonalTasksAction $personalTasks,
        GetPersonalBlockersAction $personalBlockers
    ): JsonResponse {
        try {
            $user = $request->user();
            $dashboardData = [];

            if ($user->can('view_financial_stats')) {
                $dashboardData['financial'] = $financialStats->execute();
            }

            if ($user->can('view_project_timeline')) {
                $dashboardData['timeline'] = $projectTimeline->execute();
            }

            if ($user->can('view_team_performance')) {
                $dashboardData['team'] = $teamStats->execute();
            }

            if ($user->can('view_personal_tasks')) {
                $dashboardData['personal_tasks'] = $personalTasks->execute($user);
                $dashboardData['blockers'] = $personalBlockers->execute($user);
            }

            return response()->json($dashboardData);
        } catch (\Exception $e) {
            Log::error('Dashboard overview error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to load dashboard data: '.$e->getMessage(), 500);
        }
    }
}
