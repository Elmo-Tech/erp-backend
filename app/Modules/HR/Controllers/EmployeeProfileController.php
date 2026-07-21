<?php

namespace App\Modules\HR\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\HR\Models\EmployeeProfile;
use App\Modules\HR\Requests\StoreEmployeeProfileRequest;
use App\Modules\HR\Requests\UpdateEmployeeProfileRequest;
use App\Modules\HR\Resources\EmployeeProfileResource;
use App\Modules\Settings\Models\User;
use App\Modules\Settings\Policies\UserAdministrationPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeProfileController extends Controller
{
    public function __construct(
        private readonly UserAdministrationPolicy $administrationPolicy
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * GET /hr/employee-profiles
     * List all employee profiles (admin only).
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['view_employees', 'manage_employees']), 403);

        $profiles = EmployeeProfile::with(['user:id,name,email,avatar', 'shift:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success(
            EmployeeProfileResource::collection($profiles),
            'Employee profiles retrieved successfully'
        );
    }

    /**
     * POST /hr/employee-profiles
     */
    public function store(StoreEmployeeProfileRequest $request): JsonResponse
    {
        $target = User::query()->findOrFail($request->integer('user_id'));
        $this->administrationPolicy->authorizeEmployeeMutation(
            $request->user(),
            $target,
            'create_employees'
        );

        $profile = EmployeeProfile::create($request->validated());
        $profile->load(['user:id,name,email,avatar', 'shift:id,name']);

        return ApiResponse::success(
            new EmployeeProfileResource($profile),
            'Employee profile created successfully',
            201
        );
    }

    /**
     * GET /hr/employee-profiles/{profile}
     */
    public function show(Request $request, EmployeeProfile $employeeProfile): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(['view_employees', 'manage_employees']), 403);

        $employeeProfile->load(['user:id,name,email,avatar', 'shift:id,name,start_time,end_time,working_days,grace_period_minutes']);

        return ApiResponse::success(
            new EmployeeProfileResource($employeeProfile),
            'Employee profile retrieved successfully'
        );
    }

    /**
     * PUT /hr/employee-profiles/{profile}
     */
    public function update(UpdateEmployeeProfileRequest $request, EmployeeProfile $employeeProfile): JsonResponse
    {
        $this->administrationPolicy->authorizeEmployeeMutation(
            $request->user(),
            $employeeProfile->user,
            'edit_employees'
        );

        $employeeProfile->update($request->validated());
        $employeeProfile->load(['user:id,name,email,avatar', 'shift:id,name']);

        return ApiResponse::success(
            new EmployeeProfileResource($employeeProfile),
            'Employee profile updated successfully'
        );
    }

    /**
     * DELETE /hr/employee-profiles/{profile}
     */
    public function destroy(Request $request, EmployeeProfile $employeeProfile): JsonResponse
    {
        $this->administrationPolicy->authorizeEmployeeMutation(
            $request->user(),
            $employeeProfile->user,
            'delete_employees'
        );

        $employeeProfile->delete();

        return ApiResponse::success(null, 'Employee profile deleted successfully');
    }
}
