<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Tasks\CreateTaskDeliverableRequest;
use App\Http\Requests\V1\Tasks\ReviewTaskDeliverableRequest;
use App\Http\Requests\V1\Tasks\SubmitTaskDeliverableRequest;
use App\Http\Requests\V1\Tasks\UpdateTaskDeliverableRequest;
use App\Http\Resources\V1\Tasks\TaskDeliverableResource;
use App\Models\Task;
use App\Models\TaskDeliverable;
use App\Services\Tasks\DeliverableService;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Task Deliverables",
 *     description="API endpoints for managing task deliverables"
 * )
 */
class TaskDeliverableController extends Controller
{
    public function __construct(
        private DeliverableService $deliverableService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
        $this->middleware(function ($request, $next) {
            if ($id = $request->route('taskId')) {
                $task = Task::findOrFail($id);
                abort_unless(app(\App\Services\Tasks\TaskVisibility::class)->canView($request->user(), $task), 403);
            }
            return $next($request);
        });
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tasks/{taskId}/deliverables",
     *     summary="Get task deliverables",
     *     description="Retrieve all deliverables for a specific task",
     *     operationId="getTaskDeliverables",
     *     tags={"Task Deliverables"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="taskId",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Task deliverables retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task deliverables retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="deliverables",
     *                     type="array",
     *
     *                     @OA\Items(
     *
     *                         @OA\Property(property="deliverableId", type="integer", example=1),
     *                         @OA\Property(property="taskId", type="integer", example=1),
     *                         @OA\Property(property="title", type="string", example="User Interface Design"),
     *                         @OA\Property(property="description", type="string", example="Create mockups for the login page"),
     *                         @OA\Property(property="acceptanceCriteria", type="string", example="Must include responsive design"),
     *                         @OA\Property(property="status", type="string", enum={"pending", "submitted", "under_review", "approved", "rejected"}),
     *                         @OA\Property(property="submissionNotes", type="string", example="Completed as requested"),
     *                         @OA\Property(property="reviewNotes", type="string", example="Looks good, approved"),
     *                         @OA\Property(property="submittedAt", type="string", format="date-time", nullable=true),
     *                         @OA\Property(property="reviewedAt", type="string", format="date-time", nullable=true),
     *                         @OA\Property(
     *                             property="reviewedBy",
     *                             type="object",
     *                             nullable=true,
     *                             @OA\Property(property="userId", type="integer", example=2),
     *                             @OA\Property(property="name", type="string", example="John Reviewer")
     *                         ),
     *                         @OA\Property(property="pointsAwarded", type="integer", example=10),
     *                         @OA\Property(property="isBonus", type="boolean", example=false),
     *                         @OA\Property(property="order", type="integer", example=1),
     *                         @OA\Property(property="createdAt", type="string", format="date-time"),
     *                         @OA\Property(property="updatedAt", type="string", format="date-time")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Task not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Task not found")
     *         )
     *     )
     * )
     */
    public function index(int $taskId): JsonResponse
    {
        $task = Task::findOrFail($taskId);

        $deliverables = $task->deliverables()
            ->with('reviewer')
            ->orderBy('order')
            ->orderBy('created_at')
            ->get();

        return ApiResponse::success([
            'deliverables' => TaskDeliverableResource::collection($deliverables),
        ], __('messages.deliverables_retrieved'));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tasks/{taskId}/deliverables",
     *     summary="Create task deliverable",
     *     description="Create a new deliverable for a specific task",
     *     operationId="createTaskDeliverable",
     *     tags={"Task Deliverables"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="taskId",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="title", type="string", example="User Interface Design"),
     *             @OA\Property(property="description", type="string", example="Create mockups for the login page"),
     *             @OA\Property(property="acceptanceCriteria", type="string", example="Must include responsive design"),
     *             @OA\Property(property="isBonus", type="boolean", example=false),
     *             @OA\Property(property="order", type="integer", example=1)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Deliverable created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Deliverable created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="deliverable", ref="#/components/schemas/TaskDeliverable")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="title", type="array", @OA\Items(type="string", example="The title field is required."))
     *             )
     *         )
     *     )
     * )
     */
    public function store(CreateTaskDeliverableRequest $request, int $taskId): JsonResponse
    {
        $task = Task::findOrFail($taskId);

        try {
            $deliverable = $this->deliverableService->createDeliverable($task, $request->validated());

            return ApiResponse::success([
                'deliverable' => new TaskDeliverableResource($deliverable),
            ], __('messages.deliverable_created'), 201);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tasks/{taskId}/deliverables/{deliverableId}",
     *     summary="Get task deliverable",
     *     description="Retrieve a specific deliverable",
     *     operationId="getTaskDeliverable",
     *     tags={"Task Deliverables"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="taskId",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="deliverableId",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Deliverable retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="deliverable", ref="#/components/schemas/TaskDeliverable")
     *             )
     *         )
     *     )
     * )
     */
    public function show(int $taskId, int $deliverableId): JsonResponse
    {
        $task = Task::findOrFail($taskId);
        $deliverable = $task->deliverables()->with('reviewer')->findOrFail($deliverableId);

        return ApiResponse::success([
            'deliverable' => new TaskDeliverableResource($deliverable),
        ], __('messages.deliverable_retrieved'));
    }

    /**
     * @OA\Put(
     *     path="/api/v1/tasks/{taskId}/deliverables/{deliverableId}",
     *     summary="Update task deliverable",
     *     description="Update a specific deliverable (only allowed in pending status)",
     *     operationId="updateTaskDeliverable",
     *     tags={"Task Deliverables"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="taskId",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="deliverableId",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="title", type="string", example="Updated UI Design"),
     *             @OA\Property(property="description", type="string", example="Updated description"),
     *             @OA\Property(property="acceptanceCriteria", type="string", example="Updated criteria"),
     *             @OA\Property(property="isBonus", type="boolean", example=true),
     *             @OA\Property(property="order", type="integer", example=2)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable updated successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cannot update deliverable in current status"
     *     )
     * )
     */
    public function update(UpdateTaskDeliverableRequest $request, int $taskId, int $deliverableId): JsonResponse
    {
        $task = Task::findOrFail($taskId);
        $deliverable = $task->deliverables()->findOrFail($deliverableId);

        try {
            $updatedDeliverable = $this->deliverableService->updateDeliverable($deliverable, $request->validated());

            return ApiResponse::success([
                'deliverable' => new TaskDeliverableResource($updatedDeliverable),
            ], __('messages.deliverable_updated'));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tasks/{taskId}/deliverables/{deliverableId}",
     *     summary="Delete task deliverable",
     *     description="Delete a specific deliverable (only allowed in pending status)",
     *     operationId="deleteTaskDeliverable",
     *     tags={"Task Deliverables"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="taskId",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="deliverableId",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cannot delete deliverable in current status"
     *     )
     * )
     */
    public function destroy(int $taskId, int $deliverableId): JsonResponse
    {
        $task = Task::findOrFail($taskId);
        $deliverable = $task->deliverables()->findOrFail($deliverableId);

        try {
            $this->deliverableService->deleteDeliverable($deliverable);

            return ApiResponse::success(
                null,
                __('messages.deliverable_deleted')
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tasks/{taskId}/deliverables/{deliverableId}/submit",
     *     summary="Submit task deliverable",
     *     description="Submit a deliverable for review",
     *     operationId="submitTaskDeliverable",
     *     tags={"Task Deliverables"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="taskId",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="deliverableId",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=false,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="submissionNotes", type="string", example="Completed as requested with additional improvements")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable submitted successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cannot submit deliverable in current status"
     *     )
     * )
     */
    public function submit(SubmitTaskDeliverableRequest $request, int $taskId, int $deliverableId): JsonResponse
    {
        $task = Task::findOrFail($taskId);
        $deliverable = $task->deliverables()->findOrFail($deliverableId);

        try {
            $submittedDeliverable = $this->deliverableService->submitDeliverable(
                $deliverable,
                auth()->user(),
                $request->validated()
            );

            return ApiResponse::success([
                'deliverable' => new TaskDeliverableResource($submittedDeliverable),
            ], __('messages.deliverable_submitted'));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tasks/{taskId}/deliverables/{deliverableId}/review",
     *     summary="Review task deliverable",
     *     description="Review and approve/reject a submitted deliverable",
     *     operationId="reviewTaskDeliverable",
     *     tags={"Task Deliverables"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="taskId",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="deliverableId",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", enum={"approved", "rejected"}, example="approved"),
     *             @OA\Property(property="reviewNotes", type="string", example="Great work, meets all requirements"),
     *             @OA\Property(property="pointsAwarded", type="integer", example=10)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable reviewed successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cannot review deliverable in current status"
     *     )
     * )
     */
    public function review(ReviewTaskDeliverableRequest $request, int $taskId, int $deliverableId): JsonResponse
    {
        $task = Task::findOrFail($taskId);
        $deliverable = $task->deliverables()->findOrFail($deliverableId);

        try {
            $reviewData = [
                'reviewNotes' => $request->reviewNotes,
                'pointsAwarded' => $request->pointsAwarded ?? 1,
            ];

            if ($request->status === 'approved') {
                $reviewedDeliverable = $this->deliverableService->approveDeliverable(
                    $deliverable,
                    auth()->user(),
                    $reviewData
                );
            } else {
                $reviewData['pointsDeducted'] = $request->pointsAwarded ?? 1;
                $reviewedDeliverable = $this->deliverableService->rejectDeliverable(
                    $deliverable,
                    auth()->user(),
                    $reviewData
                );
            }

            return ApiResponse::success([
                'deliverable' => new TaskDeliverableResource($reviewedDeliverable),
            ], __('messages.deliverable_reviewed'));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}
