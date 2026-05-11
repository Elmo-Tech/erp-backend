<?php

namespace App\Modules\HR\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\HR\Models\LeaveRequest;
use App\Modules\HR\Requests\StoreLeaveRequestRequest;
use App\Modules\HR\Requests\UpdateLeaveRequestRequest;
use App\Modules\HR\Resources\LeaveRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * GET /hr/leave-requests
     * List all leave requests (paginated). Requires manage_hr permission.
     */
    public function index(Request $request): JsonResponse
    {
        abort_if(! $request->user()->can('manage_hr'), 403, 'Unauthorized');

        $leaveRequests = LeaveRequest::with('user:id,name,email')
            ->when($request->filled('user_id'), fn ($q) => $q->forUser((int) $request->user_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->orderByDesc('start_date')
            ->paginate($request->integer('perPage', 15));

        return ApiResponse::success(
            LeaveRequestResource::collection($leaveRequests)->response()->getData(true),
            'Leave requests retrieved successfully'
        );
    }

    /**
     * POST /hr/leave-requests
     */
    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
        abort_if(! $request->user()->can('manage_hr'), 403, 'Unauthorized');

        $leaveRequest = LeaveRequest::create($request->validated());
        $leaveRequest->load('user:id,name,email');

        return ApiResponse::success(
            new LeaveRequestResource($leaveRequest),
            'Leave request created successfully',
            201
        );
    }

    /**
     * GET /hr/leave-requests/{leaveRequest}
     */
    public function show(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        abort_if(! $request->user()->can('manage_hr'), 403, 'Unauthorized');

        $leaveRequest->load('user:id,name,email');

        return ApiResponse::success(
            new LeaveRequestResource($leaveRequest),
            'Leave request retrieved successfully'
        );
    }

    /**
     * PUT /hr/leave-requests/{leaveRequest}
     */
    public function update(UpdateLeaveRequestRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        abort_if(! $request->user()->can('manage_hr'), 403, 'Unauthorized');

        $leaveRequest->update($request->validated());

        return ApiResponse::success(
            new LeaveRequestResource($leaveRequest->fresh('user')),
            'Leave request updated successfully'
        );
    }

    /**
     * DELETE /hr/leave-requests/{leaveRequest}
     */
    public function destroy(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        abort_if(! $request->user()->can('manage_hr'), 403, 'Unauthorized');

        $leaveRequest->delete();

        return ApiResponse::success(null, 'Leave request deleted successfully');
    }
}
