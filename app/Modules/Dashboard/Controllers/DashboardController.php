<?php

namespace App\Modules\Dashboard\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Dashboard\Actions\GetAdminDashboardStatsAction;
use App\Modules\Dashboard\Actions\GetEmployeeDashboardStatsAction;
use App\Modules\Dashboard\Actions\GetManagerDashboardStatsAction;
use App\Modules\Dashboard\Resources\DashboardOverviewResource;
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
    public function overview(
        Request $request,
        GetAdminDashboardStatsAction $adminAction,
        GetManagerDashboardStatsAction $managerAction,
        GetEmployeeDashboardStatsAction $employeeAction
    ): JsonResponse {
        try {
            $user = $request->user();
            $isAdmin = $user->hasAnyRole(['super-admin', 'admin']);
            $isManager = $user->hasRole('manager');

            if ($isAdmin) {
                $data = $adminAction->execute();
            } elseif ($isManager) {
                $data = $managerAction->execute($user);
            } else {
                $data = $employeeAction->execute($user);
            }

            return ApiResponse::success(
                DashboardOverviewResource::make($data)->resolve(),
                'Dashboard overview retrieved successfully'
            );
        } catch (\Exception $e) {
            Log::error('Dashboard overview error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error('Failed to load dashboard data: '.$e->getMessage(), 500);
        }
    }
}
