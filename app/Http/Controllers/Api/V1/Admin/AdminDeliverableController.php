<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Admin\AdminUpdateDeliverableRequest;
use App\Http\Requests\V1\Admin\AdminChangeDeliverableStatusRequest;
use App\Http\Requests\V1\Admin\AdminForceReviewDeliverableRequest;
use App\Http\Resources\V1\Tasks\TaskDeliverableResource;
use App\Models\TaskDeliverable;
use App\Services\Tasks\DeliverableService;
use App\Helpers\ApiResponse;
use App\Enums\DeliverableStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Admin - Deliverables",
 *     description="Admin endpoints for full control over deliverables"
 * )
 */
class AdminDeliverableController extends Controller
{
    public function __construct(
        private DeliverableService $deliverableService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
        // Only admins can access these endpoints
        $this->middleware('permission:manage_deliverables');
    }

    /**
     * @OA\Put(
     *     path="/api/v1/admin/deliverables/{deliverableId}",
     *     summary="Admin: Update deliverable (any status)",
     *     description="Admin can update any deliverable regardless of status",
     *     operationId="adminUpdateDeliverable",
     *     tags={"Admin - Deliverables"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *     @OA\Parameter(
     *         name="deliverableId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Updated Deliverable Title"),
     *             @OA\Property(property="description", type="string", example="Updated description"),
     *             @OA\Property(property="acceptanceCriteria", type="string", example="Updated criteria"),
     *             @OA\Property(property="submissionNotes", type="string", example="Updated submission notes"),
     *             @OA\Property(property="reviewNotes", type="string", example="Updated review notes"),
     *             @OA\Property(property="pointsAwarded", type="integer", example=15),
     *             @OA\Property(property="isBonus", type="boolean", example=true),
     *             @OA\Property(property="order", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Deliverable updated successfully by admin"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="deliverable", ref="#/components/schemas/TaskDeliverable")
     *             )
     *         )
     *     )
     * )
     */
    public function update(AdminUpdateDeliverableRequest $request, int $deliverableId): JsonResponse
    {
        $deliverable = TaskDeliverable::with('reviewer', 'task')->findOrFail($deliverableId);

        return DB::transaction(function () use ($request, $deliverable) {
            $updateData = array_filter([
                'title' => $request->title,
                'description' => $request->description,
                'acceptance_criteria' => $request->acceptanceCriteria,
                'submission_notes' => $request->submissionNotes,
                'review_notes' => $request->reviewNotes,
                'points_awarded' => $request->pointsAwarded,
                'is_bonus' => $request->isBonus,
                'order' => $request->order,
            ], fn($value) => $value !== null);

            $deliverable->update($updateData);
            $deliverable->load('reviewer', 'task');

            return ApiResponse::success([
                'deliverable' => new TaskDeliverableResource($deliverable)
            ], __('messages.admin_deliverable_updated'));
        });
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/admin/deliverables/{deliverableId}",
     *     summary="Admin: Delete deliverable (any status)",
     *     description="Admin can delete any deliverable regardless of status",
     *     operationId="adminDeleteDeliverable",
     *     tags={"Admin - Deliverables"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *     @OA\Parameter(
     *         name="deliverableId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Deliverable deleted successfully by admin")
     *         )
     *     )
     * )
     */
    public function destroy(int $deliverableId): JsonResponse
    {
        $deliverable = TaskDeliverable::findOrFail($deliverableId);
        
        $deliverable->delete();

        return ApiResponse::success(
            null,
            __('messages.admin_deliverable_deleted')
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/admin/deliverables/{deliverableId}/status",
     *     summary="Admin: Change deliverable status directly",
     *     description="Admin can change deliverable status to any status directly, bypassing normal workflow",
     *     operationId="adminChangeDeliverableStatus",
     *     tags={"Admin - Deliverables"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *     @OA\Parameter(
     *         name="deliverableId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 enum={"pending", "submitted", "under_review", "approved", "rejected"},
     *                 example="approved",
     *                 description="New status for the deliverable"
     *             ),
     *             @OA\Property(
     *                 property="reason",
     *                 type="string",
     *                 example="Admin override: Client requested immediate approval",
     *                 description="Reason for status change (optional but recommended)"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status changed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Deliverable status changed successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="deliverable", ref="#/components/schemas/TaskDeliverable"),
     *                 @OA\Property(property="oldStatus", type="string", example="pending"),
     *                 @OA\Property(property="newStatus", type="string", example="approved")
     *             )
     *         )
     *     )
     * )
     */
    public function changeStatus(AdminChangeDeliverableStatusRequest $request, int $deliverableId): JsonResponse
    {
        $deliverable = TaskDeliverable::with('reviewer', 'task')->findOrFail($deliverableId);
        $oldStatus = $deliverable->status->value;

        return DB::transaction(function () use ($request, $deliverable, $oldStatus) {
            $newStatus = DeliverableStatus::from($request->status);
            
            $updateData = ['status' => $newStatus];

            // Update timestamps based on new status
            if ($newStatus === DeliverableStatus::SUBMITTED && !$deliverable->submitted_at) {
                $updateData['submitted_at'] = now();
            }

            if (in_array($newStatus, [DeliverableStatus::APPROVED, DeliverableStatus::REJECTED])) {
                if (!$deliverable->reviewed_at) {
                    $updateData['reviewed_at'] = now();
                }
                if (!$deliverable->reviewed_by) {
                    $updateData['reviewed_by'] = auth()->id();
                }
            }

            // Add reason to review notes if provided
            if ($request->reason) {
                $existingNotes = $deliverable->review_notes ? $deliverable->review_notes . "\n\n" : '';
                $updateData['review_notes'] = $existingNotes . "[Admin Override] " . $request->reason;
            }

            $deliverable->update($updateData);
            $deliverable->load('reviewer', 'task');

            return ApiResponse::success([
                'deliverable' => new TaskDeliverableResource($deliverable),
                'oldStatus' => $oldStatus,
                'newStatus' => $newStatus->value,
            ], __('messages.admin_status_changed'));
        });
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/admin/deliverables/{deliverableId}/reset",
     *     summary="Admin: Reset deliverable to pending",
     *     description="Reset deliverable to pending status, clearing review data",
     *     operationId="adminResetDeliverable",
     *     tags={"Admin - Deliverables"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *     @OA\Parameter(
     *         name="deliverableId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="reason",
     *                 type="string",
     *                 example="Client requested changes, resetting for resubmission"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable reset successfully"
     *     )
     * )
     */
    public function reset(AdminChangeDeliverableStatusRequest $request, int $deliverableId): JsonResponse
    {
        $deliverable = TaskDeliverable::with('reviewer', 'task')->findOrFail($deliverableId);

        return DB::transaction(function () use ($request, $deliverable) {
            $updateData = [
                'status' => DeliverableStatus::PENDING,
                'submission_notes' => null,
                'review_notes' => $request->reason ? "[Admin Reset] " . $request->reason : null,
                'submitted_at' => null,
                'reviewed_at' => null,
                'reviewed_by' => null,
                'points_awarded' => 0,
            ];

            $deliverable->update($updateData);
            $deliverable->load('reviewer', 'task');

            return ApiResponse::success([
                'deliverable' => new TaskDeliverableResource($deliverable)
            ], __('messages.admin_deliverable_reset'));
        });
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/deliverables/{deliverableId}/force-approve",
     *     summary="Admin: Force approve deliverable",
     *     description="Directly approve deliverable with custom points and notes",
     *     operationId="adminForceApprove",
     *     tags={"Admin - Deliverables"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *     @OA\Parameter(
     *         name="deliverableId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="pointsAwarded", type="integer", example=20, description="Points to award (default: 1)"),
     *             @OA\Property(property="reviewNotes", type="string", example="Approved by admin due to urgent deadline")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable approved successfully"
     *     )
     * )
     */
    public function forceApprove(AdminForceReviewDeliverableRequest $request, int $deliverableId): JsonResponse
    {
        $deliverable = TaskDeliverable::with('reviewer', 'task')->findOrFail($deliverableId);

        try {
            $reviewData = [
                'reviewNotes' => $request->reviewNotes ?? '[Admin Force Approved]',
                'pointsAwarded' => $request->pointsAwarded ?? 1,
            ];

            $approvedDeliverable = $this->deliverableService->approveDeliverable(
                $deliverable,
                auth()->user(),
                $reviewData
            );

            return ApiResponse::success([
                'deliverable' => new TaskDeliverableResource($approvedDeliverable)
            ], __('messages.admin_deliverable_approved'));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/admin/deliverables/{deliverableId}/force-reject",
     *     summary="Admin: Force reject deliverable",
     *     description="Directly reject deliverable with custom notes",
     *     operationId="adminForceReject",
     *     tags={"Admin - Deliverables"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"en", "ar"}, default="en")
     *     ),
     *     @OA\Parameter(
     *         name="deliverableId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="reviewNotes", type="string", example="Rejected by admin: Does not meet quality standards")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deliverable rejected successfully"
     *     )
     * )
     */
    public function forceReject(AdminForceReviewDeliverableRequest $request, int $deliverableId): JsonResponse
    {
        $deliverable = TaskDeliverable::with('reviewer', 'task')->findOrFail($deliverableId);

        try {
            $reviewData = [
                'reviewNotes' => $request->reviewNotes ?? '[Admin Force Rejected]',
                'pointsDeducted' => 1, // Default deduction for admin rejection
            ];

            $rejectedDeliverable = $this->deliverableService->rejectDeliverable(
                $deliverable,
                auth()->user(),
                $reviewData
            );

            return ApiResponse::success([
                'deliverable' => new TaskDeliverableResource($rejectedDeliverable)
            ], __('messages.admin_deliverable_rejected'));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}
