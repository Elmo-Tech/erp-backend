<?php

namespace App\Modules\HR\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\HR\Models\Shift;
use App\Modules\HR\Requests\StoreShiftRequest;
use App\Modules\HR\Requests\UpdateShiftRequest;
use App\Modules\HR\Resources\ShiftResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * GET /hr/shifts
     * List all shifts (admin only).
     */
    public function index(Request $request): JsonResponse
    {
        abort_if(! $request->user()->can('manage_shifts'), 403, 'Unauthorized');

        $shifts = Shift::withCount('employeeProfiles')
            ->orderBy('name')
            ->get();

        return ApiResponse::success(
            ShiftResource::collection($shifts),
            'Shifts retrieved successfully'
        );
    }

    /**
     * POST /hr/shifts
     */
    public function store(StoreShiftRequest $request): JsonResponse
    {
        abort_if(! $request->user()->can('manage_shifts'), 403, 'Unauthorized');

        $shift = Shift::create($request->validated());

        return ApiResponse::success(
            new ShiftResource($shift),
            'Shift created successfully',
            201
        );
    }

    /**
     * GET /hr/shifts/{shift}
     */
    public function show(Request $request, Shift $shift): JsonResponse
    {
        abort_if(! $request->user()->can('manage_shifts'), 403, 'Unauthorized');

        $shift->loadCount('employeeProfiles');

        return ApiResponse::success(
            new ShiftResource($shift),
            'Shift retrieved successfully'
        );
    }

    /**
     * PUT /hr/shifts/{shift}
     */
    public function update(UpdateShiftRequest $request, Shift $shift): JsonResponse
    {
        abort_if(! $request->user()->can('manage_shifts'), 403, 'Unauthorized');

        $shift->update($request->validated());

        return ApiResponse::success(
            new ShiftResource($shift->fresh()),
            'Shift updated successfully'
        );
    }

    /**
     * DELETE /hr/shifts/{shift}
     */
    public function destroy(Request $request, Shift $shift): JsonResponse
    {
        abort_if(! $request->user()->can('manage_shifts'), 403, 'Unauthorized');

        $shift->delete();

        return ApiResponse::success(null, 'Shift deleted successfully');
    }
}
