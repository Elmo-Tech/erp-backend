<?php

namespace App\Modules\Dashboard\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Actions\EscalateBlockerAction;
use App\Modules\Dashboard\Actions\GetEscalatedBlockersAction;
use App\Modules\Dashboard\Actions\GetDashboardOverviewAction;
use App\Modules\Dashboard\Actions\ResolveEscalatedBlockerAction;
use App\Modules\Task\Models\Task;
use App\Modules\Task\Models\TaskDependency;
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
    public function index(Request $request, GetDashboardOverviewAction $dashboardOverview): JsonResponse
    {
        return response()->json($dashboardOverview->execute($request->user()));
    }

    public function escalateBlocker(
        Request $request,
        Task $task,
        EscalateBlockerAction $escalateBlocker,
        GetEscalatedBlockersAction $escalatedBlockers
    ): JsonResponse {
        abort_unless($request->user()?->can('view_all_blockers'), 403);

        $dependency = $escalateBlocker->execute($task, $request->user());

        return response()->json([
            'data' => $escalatedBlockers->format($dependency),
        ]);
    }

    public function resolveEscalatedBlocker(
        Request $request,
        TaskDependency $dependency,
        ResolveEscalatedBlockerAction $resolveEscalatedBlocker,
        GetEscalatedBlockersAction $escalatedBlockers
    ): JsonResponse {
        abort_unless($request->user()?->can('view_escalated_blockers'), 403);

        $resolvedDependency = $resolveEscalatedBlocker->execute($dependency);

        return response()->json([
            'data' => $escalatedBlockers->format($resolvedDependency),
        ]);
    }
}
