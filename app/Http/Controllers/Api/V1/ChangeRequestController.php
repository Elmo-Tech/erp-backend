<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\ChangeRequests\StoreChangeRequestRequest;
use App\Http\Requests\V1\ChangeRequests\UpdateChangeRequestRequest;
use App\Http\Resources\V1\ChangeRequests\ChangeRequestCollection;
use App\Http\Resources\V1\ChangeRequests\ChangeRequestResource;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Services\Projects\ChangeRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class ChangeRequestController extends Controller
{
    public function __construct(
        private ChangeRequestService $changeRequestService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/projects/{projectId}/change-requests",
     *     operationId="getChangeRequests",
     *     tags={"Change Requests"},
     *     summary="Get change requests for a project",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Change requests retrieved successfully")
     * )
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        $changeRequests = $this->changeRequestService->getChangeRequests($project, $request->all());

        return ApiResponse::success(
            new ChangeRequestCollection($changeRequests),
            __('change_requests.retrieved')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/projects/{projectId}/change-requests",
     *     operationId="createChangeRequest",
     *     tags={"Change Requests"},
     *     summary="Create a change request",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=201, description="Change request created successfully")
     * )
     */
    public function store(StoreChangeRequestRequest $request, Project $project): JsonResponse
    {
        $changeRequest = $this->changeRequestService->createChangeRequest(
            $project,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new ChangeRequestResource($changeRequest),
            __('change_requests.created'),
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/change-requests/{id}",
     *     operationId="getChangeRequest",
     *     tags={"Change Requests"},
     *     summary="Get a single change request",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Change request retrieved successfully")
     * )
     */
    public function show(ChangeRequest $changeRequest): JsonResponse
    {
        $changeRequest->load(['project', 'requester', 'approver']);

        return ApiResponse::success(
            new ChangeRequestResource($changeRequest),
            __('change_requests.retrieved')
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/change-requests/{id}",
     *     operationId="updateChangeRequest",
     *     tags={"Change Requests"},
     *     summary="Update a change request",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Change request updated successfully")
     * )
     */
    public function update(UpdateChangeRequestRequest $request, ChangeRequest $changeRequest): JsonResponse
    {
        $changeRequest = $this->changeRequestService->updateChangeRequest(
            $changeRequest,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new ChangeRequestResource($changeRequest),
            __('change_requests.updated')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/change-requests/{id}/submit",
     *     operationId="submitChangeRequest",
     *     tags={"Change Requests"},
     *     summary="Submit change request for review",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Change request submitted successfully")
     * )
     */
    public function submit(ChangeRequest $changeRequest): JsonResponse
    {
        $changeRequest = $this->changeRequestService->submitForReview(
            $changeRequest,
            request()->user()
        );

        return ApiResponse::success(
            new ChangeRequestResource($changeRequest),
            __('change_requests.submitted')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/change-requests/{id}/approve",
     *     operationId="approveChangeRequest",
     *     tags={"Change Requests"},
     *     summary="Approve a change request",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Change request approved successfully")
     * )
     */
    public function approve(Request $request, ChangeRequest $changeRequest): JsonResponse
    {
        $changeRequest = $this->changeRequestService->approve(
            $changeRequest,
            $request->user(),
            $request->input('approvedCost')
        );

        return ApiResponse::success(
            new ChangeRequestResource($changeRequest),
            __('change_requests.approved')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/change-requests/{id}/reject",
     *     operationId="rejectChangeRequest",
     *     tags={"Change Requests"},
     *     summary="Reject a change request",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Change request rejected successfully")
     * )
     */
    public function reject(Request $request, ChangeRequest $changeRequest): JsonResponse
    {
        $request->validate(['reason' => 'required|string']);

        $changeRequest = $this->changeRequestService->reject(
            $changeRequest,
            $request->user(),
            $request->input('reason')
        );

        return ApiResponse::success(
            new ChangeRequestResource($changeRequest),
            __('change_requests.rejected')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/change-requests/{id}/implement",
     *     operationId="implementChangeRequest",
     *     tags={"Change Requests"},
     *     summary="Mark change request as implemented",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Change request marked as implemented")
     * )
     */
    public function implement(ChangeRequest $changeRequest): JsonResponse
    {
        $changeRequest = $this->changeRequestService->markImplemented(
            $changeRequest,
            request()->user()
        );

        return ApiResponse::success(
            new ChangeRequestResource($changeRequest),
            __('change_requests.implemented')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/projects/{projectId}/change-requests/summary",
     *     operationId="getChangeRequestSummary",
     *     tags={"Change Requests"},
     *     summary="Get CR summary for project",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Summary retrieved successfully")
     * )
     */
    public function summary(Project $project): JsonResponse
    {
        $summary = $this->changeRequestService->getProjectCRSummary($project);

        return ApiResponse::success($summary, __('change_requests.summary_retrieved'));
    }
}
