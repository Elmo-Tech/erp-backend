<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectHandover;
use App\Services\Projects\HandoverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class HandoverController extends Controller
{
    public function __construct(
        private HandoverService $handoverService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/handovers",
     *     operationId="getHandovers",
     *     tags={"Handovers"},
     *     summary="Get all handovers with filters",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Handovers retrieved successfully")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $handovers = $this->handoverService->getHandovers($request->all());

        return ApiResponse::success($handovers, __('handovers.retrieved'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/handovers/pending",
     *     operationId="getPendingHandovers",
     *     tags={"Handovers"},
     *     summary="Get pending handovers",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Pending handovers retrieved successfully")
     * )
     */
    public function pending(): JsonResponse
    {
        $handovers = $this->handoverService->getPendingHandovers();

        return ApiResponse::success($handovers, __('handovers.retrieved'));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/projects/{projectId}/handover",
     *     operationId="createHandover",
     *     tags={"Handovers"},
     *     summary="Create handover for project",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Handover created successfully")
     * )
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'salesRepId' => 'required|exists:users,id',
            'projectManagerId' => 'required|exists:users,id',
            'contractId' => 'nullable|exists:contracts,id',
            'quotationId' => 'nullable|exists:quotations,id',
            'handoverDate' => 'nullable|date',
            'scopeConfirmed' => 'nullable|boolean',
            'requirementsClear' => 'nullable|boolean',
            'timelineAgreed' => 'nullable|boolean',
            'budgetConfirmed' => 'nullable|boolean',
            'risksIdentified' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'attachments' => 'nullable|array',
        ]);

        $handover = $this->handoverService->createHandover($project, $validated, $request->user());

        return ApiResponse::success($handover, __('handovers.created'), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/handovers/{id}",
     *     operationId="getHandover",
     *     tags={"Handovers"},
     *     summary="Get a single handover",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Handover retrieved successfully")
     * )
     */
    public function show(ProjectHandover $handover): JsonResponse
    {
        $handover->load(['project', 'contract', 'quotation', 'salesRep', 'projectManager']);

        return ApiResponse::success($handover, __('handovers.retrieved'));
    }

    /**
     * @OA\Put(
     *     path="/api/v1/handovers/{id}",
     *     operationId="updateHandover",
     *     tags={"Handovers"},
     *     summary="Update handover checklist",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Handover updated successfully")
     * )
     */
    public function update(Request $request, ProjectHandover $handover): JsonResponse
    {
        $validated = $request->validate([
            'scopeConfirmed' => 'nullable|boolean',
            'requirementsClear' => 'nullable|boolean',
            'timelineAgreed' => 'nullable|boolean',
            'budgetConfirmed' => 'nullable|boolean',
            'risksIdentified' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'attachments' => 'nullable|array',
        ]);

        $handover = $this->handoverService->updateChecklist($handover, $validated, $request->user());

        return ApiResponse::success($handover, __('handovers.updated'));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/handovers/{id}/complete",
     *     operationId="completeHandover",
     *     tags={"Handovers"},
     *     summary="Complete handover",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Handover completed successfully")
     * )
     */
    public function complete(ProjectHandover $handover): JsonResponse
    {
        try {
            $handover = $this->handoverService->completeHandover($handover, request()->user());

            return ApiResponse::success($handover, __('handovers.completed'));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/handovers/{id}/dispute",
     *     operationId="disputeHandover",
     *     tags={"Handovers"},
     *     summary="Dispute handover",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Handover disputed successfully")
     * )
     */
    public function dispute(Request $request, ProjectHandover $handover): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string',
        ]);

        $handover = $this->handoverService->disputeHandover(
            $handover,
            $validated['reason'],
            $request->user()
        );

        return ApiResponse::success($handover, __('handovers.disputed'));
    }
}
