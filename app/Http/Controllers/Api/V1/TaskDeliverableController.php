<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\TaskDeliverables\StoreTaskDeliverableRequest;
use App\Http\Requests\V1\TaskDeliverables\UpdateTaskDeliverableRequest;
use App\Http\Requests\V1\TaskDeliverables\SubmitDeliverableRequest;
use App\Http\Requests\V1\TaskDeliverables\ReviewDeliverableRequest;
use App\Http\Requests\V1\TaskDeliverables\ReorderDeliverablesRequest;
use App\Http\Resources\V1\TaskDeliverables\TaskDeliverableResource;
use App\Http\Resources\V1\TaskDeliverables\TaskDeliverableCollection;
use App\Models\Task;
use App\Models\TaskDeliverable;
use App\Services\Tasks\DeliverableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class TaskDeliverableController extends Controller
{
    public function __construct(
        private DeliverableService $deliverableService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tasks/{taskId}/deliverables",
     *     operationId="getTaskDeliverables",
     *     tags={"Task Deliverables"},
     *     summary="Get all deliverables for a task",
     *     description="Retrieve all deliverables for a specific task with their current status",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="taskId",
     *         in="path",
     *         required=true,
     *         description="Task ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deliverables retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Deliverables retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="deliverables",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/TaskDeliverableResource")
     *                 ),
     *                 @OA\Property(
     *                     property="summary",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer"),
     *                     @OA\Property(property="pending", type="integer"),
     *                     @OA\Property(property="submitted", type="integer"),
     *                     @OA\Property(property="approved", type="integer"),
     *                     @OA\Property(property="rejected", type="integer"),
     *                     @OA\Property(property="totalPointsAwarded", type="integer"),
     *                     @OA\Property(property="totalPointsDeducted", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Task not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request, int $taskId): JsonResponse
    {
        $task = Task::findOrFail($taskId);
        
        // Check permissions
        $this->authorize('view', $task);

        $deliverables = $task->deliverables()->with(['reviewer'])->get();
        $summary = $this->deliverableService->getDeliverablesSummary($task);

        return ApiResponse::success([
            'deliverables' => TaskDeliverableResource::collection($deliverables),
            'summary' => [
                'total' => $summary['total'],
                'pending' => $summary['pending'],
                'submitted' => $summary['submitted'],
                'underReview' => $summary['under_review'],
                'approved' => $summary['approved'],
                'rejected' => $summary['rejected'],
                'totalPointsAwarded' => $summary['total_points_awarded'],
                'totalPointsDeducted' => $summary['total_points_deducted'],
            ]
        ], __('messages.deliverables_retrieved'));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tasks/{taskId}/deliverables",
     *     operationId="createTaskDeliverable",
     *     tags={"Task Deliverables"},
     *     summary="Create a new deliverable for a task",
     *     description="Create a new deliverable checkpoint for a weekly task",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="taskId",
     *         in="path",
     *         required=true,
     *         description="Task ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Complete user authentication"),
     *             @OA\Property(property="description", type="string", example="Implement login and registration functionality"),
     *             @OA\Property(property="acceptanceCriteria", type="string", example="- Login form validation\n- Password encryption\n- Session management"),
     *             @OA\Property(property="isBonus", type="boolean", example=false),
     *             @OA\Property(property="order", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Deliverable created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Deliverable created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/TaskDeliverableResource")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=404, description="Task not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function store(StoreTaskDeliverableRequest $request, int $taskId): JsonResponse
    {
        $task = Task::findOrFail($taskId);
        
        // Check permissions
        $this->authorize('update', $task);

        $deliverable = $this->deliverableService->createDeliverable(
            $task,
            $request->validated()
        );

        return ApiResponse::success(
            new TaskDeliverableResource($deliverable),
            __('messages.deliverable_created'),
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/deliverables/{id}",
     *     operationId="updateTaskDeliverable",
     *     tags={"Task Deliverables"},
     *     summary="Update a deliverable",
     *     description="Update deliverable details (only if not yet completed)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Deliverable ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Complete user authentication"),
     *             @OA\Property(property="description", type="string", example="Implement login and registration functionality"),
     *             @OA\Property(property="acceptanceCriteria", type="string", example="- Login form validation\n- Password encryption\n- Session management"),
     *             @OA\Property(property="isBonus", type="boolean", example=false),
     *             @OA\Property(property="order", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Deliverable updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/TaskDeliverableResource")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=404, description="Deliverable not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function update(UpdateTaskDeliverableRequest $request, int $id): JsonResponse
    {
        $deliverable = TaskDeliverable::findOrFail($id);
        
        // Check permissions
        $this->authorize('update', $deliverable->task);

        $deliverable = $this->deliverableService->updateDeliverable(
            $deliverable,
            $request->validated()
        );

        return ApiResponse::success(
            new TaskDeliverableResource($deliverable),
            __('messages.deliverable_updated')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/deliverables/{id}/submit",
     *     operationId="submitDeliverable",
     *     tags={"Task Deliverables"},
     *     summary="Submit a deliverable for review",
     *     description="Developer submits completed deliverable for review",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Deliverable ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="submissionNotes", type="string", example="Completed all requirements. Added extra validation.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Deliverable submitted for review"),
     *             @OA\Property(property="data", ref="#/components/schemas/TaskDeliverableResource")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Cannot submit deliverable in current state"),
     *     @OA\Response(response=404, description="Deliverable not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function submit(SubmitDeliverableRequest $request, int $id): JsonResponse
    {
        $deliverable = TaskDeliverable::findOrFail($id);
        
        $deliverable = $this->deliverableService->submitDeliverable(
            $deliverable,
            $request->user(),
            $request->validated()
        );

        return ApiResponse::success(
            new TaskDeliverableResource($deliverable),
            __('messages.deliverable_submitted')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/deliverables/{id}/start-review",
     *     operationId="startDeliverableReview",
     *     tags={"Task Deliverables"},
     *     summary="Start reviewing a deliverable",
     *     description="Reviewer starts reviewing a submitted deliverable",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Deliverable ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Review started successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Review started"),
     *             @OA\Property(property="data", ref="#/components/schemas/TaskDeliverableResource")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Cannot review deliverable in current state"),
     *     @OA\Response(response=404, description="Deliverable not found"),
     *     @OA\Response(response=403, description="No permission to review")
     * )
     */
    public function startReview(Request $request, int $id): JsonResponse
    {
        $deliverable = TaskDeliverable::findOrFail($id);
        
        $deliverable = $this->deliverableService->startReview(
            $deliverable,
            $request->user()
        );

        return ApiResponse::success(
            new TaskDeliverableResource($deliverable),
            __('messages.review_started')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/deliverables/{id}/approve",
     *     operationId="approveDeliverable",
     *     tags={"Task Deliverables"},
     *     summary="Approve a deliverable",
     *     description="Reviewer approves deliverable and awards points",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Deliverable ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="pointsAwarded", type="integer", example=1, description="Points to award (default: 1)"),
     *             @OA\Property(property="reviewNotes", type="string", example="Excellent work! All requirements met.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable approved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Deliverable approved and points awarded"),
     *             @OA\Property(property="data", ref="#/components/schemas/TaskDeliverableResource")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Cannot approve deliverable in current state"),
     *     @OA\Response(response=404, description="Deliverable not found"),
     *     @OA\Response(response=403, description="No permission to review")
     * )
     */
    public function approve(ReviewDeliverableRequest $request, int $id): JsonResponse
    {
        $deliverable = TaskDeliverable::findOrFail($id);
        
        $deliverable = $this->deliverableService->approveDeliverable(
            $deliverable,
            $request->user(),
            $request->validated()
        );

        return ApiResponse::success(
            new TaskDeliverableResource($deliverable),
            __('messages.deliverable_approved')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/deliverables/{id}/reject",
     *     operationId="rejectDeliverable",
     *     tags={"Task Deliverables"},
     *     summary="Reject a deliverable",
     *     description="Reviewer rejects deliverable and deducts points",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Deliverable ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="pointsDeducted", type="integer", example=1, description="Points to deduct (default: 1)"),
     *             @OA\Property(property="reviewNotes", type="string", example="Requirements not met. Please fix validation issues.", required=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable rejected successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Deliverable rejected and points deducted"),
     *             @OA\Property(property="data", ref="#/components/schemas/TaskDeliverableResource")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Cannot reject deliverable in current state"),
     *     @OA\Response(response=404, description="Deliverable not found"),
     *     @OA\Response(response=403, description="No permission to review")
     * )
     */
    public function reject(ReviewDeliverableRequest $request, int $id): JsonResponse
    {
        $deliverable = TaskDeliverable::findOrFail($id);
        
        $deliverable = $this->deliverableService->rejectDeliverable(
            $deliverable,
            $request->user(),
            $request->validated()
        );

        return ApiResponse::success(
            new TaskDeliverableResource($deliverable),
            __('messages.deliverable_rejected')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tasks/{taskId}/deliverables/reorder",
     *     operationId="reorderDeliverables",
     *     tags={"Task Deliverables"},
     *     summary="Reorder deliverables",
     *     description="Change the display order of deliverables for a task",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="taskId",
     *         in="path",
     *         required=true,
     *         description="Task ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="deliverableIds",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={3, 1, 2},
     *                 description="Array of deliverable IDs in the desired order"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deliverables reordered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Deliverables reordered successfully")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=404, description="Task not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function reorder(ReorderDeliverablesRequest $request, int $taskId): JsonResponse
    {
        $task = Task::findOrFail($taskId);
        
        // Check permissions
        $this->authorize('update', $task);

        $this->deliverableService->reorderDeliverables(
            $task,
            $request->validated()['deliverableIds']
        );

        return ApiResponse::success(
            null,
            __('messages.deliverables_reordered')
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/deliverables/{id}",
     *     operationId="deleteTaskDeliverable",
     *     tags={"Task Deliverables"},
     *     summary="Delete a deliverable",
     *     description="Delete a deliverable (only if not yet submitted)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Deliverable ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Deliverable deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Cannot delete submitted deliverable"),
     *     @OA\Response(response=404, description="Deliverable not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $deliverable = TaskDeliverable::findOrFail($id);
        
        // Check permissions
        $this->authorize('update', $deliverable->task);

        $this->deliverableService->deleteDeliverable($deliverable);

        return ApiResponse::success(
            null,
            __('messages.deliverable_deleted')
        );
    }
}