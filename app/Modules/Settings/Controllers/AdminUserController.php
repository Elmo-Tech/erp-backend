<?php

namespace App\Modules\Settings\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Client\Actions\LogClientAuditAction;
use App\Modules\Settings\Models\User;
use App\Modules\Settings\Requests\BulkUpdateInternalCostsRequest;
use App\Modules\Settings\Requests\UpdateInternalCostRequest;
use App\Modules\Settings\Resources\AdminUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

class AdminUserController extends Controller
{
    public function __construct(
        private LogClientAuditAction $auditLogService
    ) {
        // Apply middleware using Laravel 12 approach
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/admin/users",
     *     operationId="getAdminUsers",
     *     tags={"Admin User Management"},
     *     summary="Get all users with internal cost information",
     *     description="Retrieve all users with their internal cost information. Internal costs are only visible to users with view_internal_costs permission. This endpoint requires manage_internal_costs permission.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Users retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Users retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="users",
     *                     type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/AdminUserResource")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User lacks manage_internal_costs permission",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to manage internal costs")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // Check permission
        if (! auth()->user()->can('manage_internal_costs')) {
            return ApiResponse::forbidden('You do not have permission to manage internal costs');
        }

        // Get all users with their roles
        $users = User::with('roles')->get();

        // Transform using AdminUserResource
        $usersData = AdminUserResource::collection($users);

        return ApiResponse::success([
            'users' => $usersData,
        ], 'Users retrieved successfully');
    }

    /**
     * @OA\Put(
     *     path="/api/v1/admin/users/{id}/internal-cost",
     *     operationId="updateUserInternalCost",
     *     tags={"Admin User Management"},
     *     summary="Update user's internal hourly cost",
     *     description="Update the internal hourly cost for a specific user. This endpoint requires manage_internal_costs permission and logs all changes to audit_logs. The actual cost value is never returned in the response for security.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Internal cost update data",
     *
     *         @OA\JsonContent(
     *             required={"internalHourlyCost"},
     *
     *             @OA\Property(
     *                 property="internalHourlyCost",
     *                 type="number",
     *                 format="float",
     *                 minimum=0.01,
     *                 example=75.50,
     *                 description="The internal hourly cost for the user (must be positive)"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Internal cost updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Internal hourly cost updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="userId", type="integer", example=1),
     *                 @OA\Property(property="userName", type="string", example="John Doe"),
     *                 @OA\Property(property="updated", type="boolean", example=true),
     *                 @OA\Property(property="updatedAt", type="string", format="date-time", example="2024-01-15T10:30:00Z")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User lacks manage_internal_costs permission",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to manage internal costs")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User not found")
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
     *                 @OA\Property(
     *                     property="internalHourlyCost",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The internal hourly cost must be a positive number")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function updateInternalCost(UpdateInternalCostRequest $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            // Find the user
            $user = User::find($id);
            if (! $user) {
                return ApiResponse::notFound('User not found');
            }

            $oldCost = $user->internal_hourly_cost;
            $newCost = $request->input('internalHourlyCost');

            // Update the internal cost
            $user->internal_hourly_cost = $newCost;
            $user->save();

            // Log the change to audit_logs
            $this->auditLogService->log(
                'update_internal_cost',
                $user,
                [
                    'old_cost' => $oldCost ? (string) $oldCost : null,
                    'new_cost' => (string) $newCost,
                    'updated_by' => auth()->user()->name,
                    'updated_by_id' => auth()->id(),
                ]
            );

            // Return success without exposing the actual cost value
            return ApiResponse::success([
                'userId' => $user->id,
                'userName' => $user->name,
                'updated' => true,
                'updatedAt' => $user->updated_at->toISOString(),
            ], 'Internal hourly cost updated successfully');
        });
    }

    /**
     * @OA\Put(
     *     path="/api/v1/admin/users/internal-costs",
     *     operationId="bulkUpdateUserInternalCosts",
     *     tags={"Admin User Management"},
     *     summary="Bulk update users' internal hourly costs",
     *     description="Update internal hourly costs for multiple users in a single operation. This endpoint requires manage_internal_costs permission and logs all changes to audit_logs. All costs are validated before any updates are made.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Bulk cost update data",
     *
     *         @OA\JsonContent(
     *             required={"users"},
     *
     *             @OA\Property(
     *                 property="users",
     *                 type="array",
     *                 description="Array of user cost updates",
     *
     *                 @OA\Items(
     *                     type="object",
     *                     required={"userId", "internalHourlyCost"},
     *
     *                     @OA\Property(property="userId", type="integer", example=1),
     *                     @OA\Property(property="internalHourlyCost", type="number", format="float", minimum=0.01, example=75.50)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Bulk internal costs updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bulk internal costs updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="totalUpdated", type="integer", example=3),
     *                 @OA\Property(property="updatedAt", type="string", format="date-time", example="2024-01-15T10:30:00Z"),
     *                 @OA\Property(
     *                     property="updatedUsers",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="object",
     *
     *                         @OA\Property(property="userId", type="integer", example=1),
     *                         @OA\Property(property="userName", type="string", example="John Doe"),
     *                         @OA\Property(property="updated", type="boolean", example=true)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User lacks manage_internal_costs permission",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="You do not have permission to manage internal costs")
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
     *                 @OA\Property(
     *                     property="users.0.internalHourlyCost",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The internal hourly cost must be a positive number")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function bulkUpdateInternalCosts(BulkUpdateInternalCostsRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $usersData = $request->input('users');
            $updatedUsers = [];
            $totalUpdated = 0;

            foreach ($usersData as $userData) {
                $userId = $userData['userId'];
                $newCost = $userData['internalHourlyCost'];

                // Find the user
                $user = User::find($userId);
                if (! $user) {
                    continue; // Skip if user not found (should not happen due to validation)
                }

                $oldCost = $user->internal_hourly_cost;

                // Update the internal cost
                $user->internal_hourly_cost = $newCost;
                $user->save();

                // Log the change to audit_logs
                $this->auditLogService->log(
                    'bulk_update_internal_cost',
                    $user,
                    [
                        'old_cost' => $oldCost ? (string) $oldCost : null,
                        'new_cost' => (string) $newCost,
                        'updated_by' => auth()->user()->name,
                        'updated_by_id' => auth()->id(),
                        'bulk_operation' => true,
                    ]
                );

                $updatedUsers[] = [
                    'userId' => $user->id,
                    'userName' => $user->name,
                    'updated' => true,
                ];

                $totalUpdated++;
            }

            // Return success without exposing actual cost values
            return ApiResponse::success([
                'totalUpdated' => $totalUpdated,
                'updatedAt' => now()->toISOString(),
                'updatedUsers' => $updatedUsers,
            ], 'Bulk internal costs updated successfully');
        });
    }
}
