<?php

namespace App\Modules\Project\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Project\Services\ProjectTimelinePlannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProjectTimelineController extends Controller
{
    public function __construct(
        private readonly ProjectTimelinePlannerService $planner,
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function index(Request $request): JsonResponse
    {
        abort_if(! $request->user()->can('view_project_timeline'), 403, 'Unauthorized');

        $validated = $request->validate([
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'projectIds' => ['sometimes', 'array'],
            'projectIds.*' => ['integer', 'exists:projects,id'],
            'userIds' => ['sometimes', 'array'],
            'userIds.*' => ['integer', 'exists:users,id'],
        ]);

        $data = $this->planner->build(
            Carbon::parse($validated['startDate'])->startOfDay(),
            Carbon::parse($validated['endDate'])->startOfDay(),
            $request->user(),
            array_map('intval', $validated['projectIds'] ?? []),
            array_map('intval', $validated['userIds'] ?? []),
        );

        return ApiResponse::success($data, 'Project timeline retrieved successfully');
    }
}
