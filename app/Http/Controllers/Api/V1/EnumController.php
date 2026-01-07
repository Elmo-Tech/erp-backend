<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskCategory;
use App\Enums\UserSpecialization;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Enums",
 *     description="Enum values for dropdowns and selections"
 * )
 */
class EnumController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/enums/user-specializations",
     *     operationId="getUserSpecializations",
     *     tags={"Enums"},
     *     summary="Get all user specializations",
     *     description="Get list of all available user specializations with labels and colors",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User specializations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User specializations retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="value", type="string", example="backend"),
     *                     @OA\Property(property="label", type="string", example="Backend Developer"),
     *                     @OA\Property(property="color", type="string", example="bg-blue-100 text-blue-800")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getUserSpecializations(): JsonResponse
    {
        return ApiResponse::success(
            UserSpecialization::getAllOptions(),
            'User specializations retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/enums/task-categories",
     *     operationId="getTaskCategories",
     *     tags={"Enums"},
     *     summary="Get all task categories",
     *     description="Get list of all available task categories with labels and colors",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Task categories retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task categories retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="value", type="string", example="backend"),
     *                     @OA\Property(property="label", type="string", example="Backend Development"),
     *                     @OA\Property(property="color", type="string", example="bg-blue-100 text-blue-800")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getTaskCategories(): JsonResponse
    {
        return ApiResponse::success(
            TaskCategory::getAllOptions(),
            'Task categories retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/enums/compatible-task-categories/{specialization}",
     *     operationId="getCompatibleTaskCategories",
     *     tags={"Enums"},
     *     summary="Get task categories compatible with a specialization",
     *     description="Get list of task categories that are compatible with a specific user specialization",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="specialization",
     *         in="path",
     *         required=true,
     *         description="User specialization",
     *         @OA\Schema(type="string", enum={"backend", "frontend", "fullstack", "ui_ux", "mobile", "devops", "qa"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Compatible task categories retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Compatible task categories retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="value", type="string", example="backend"),
     *                     @OA\Property(property="label", type="string", example="Backend Development"),
     *                     @OA\Property(property="color", type="string", example="bg-blue-100 text-blue-800")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid specialization",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid specialization provided")
     *         )
     *     )
     * )
     */
    public function getCompatibleTaskCategories(string $specialization): JsonResponse
    {
        try {
            $userSpecialization = UserSpecialization::from($specialization);
            $compatibleCategories = TaskCategory::getCompatibleCategories($userSpecialization);
            
            $options = array_map(fn($category) => [
                'value' => $category->value,
                'label' => $category->label(),
                'color' => $category->color(),
            ], $compatibleCategories);

            return ApiResponse::success(
                $options,
                'Compatible task categories retrieved successfully'
            );
        } catch (\ValueError $e) {
            return ApiResponse::error(
                'Invalid specialization provided',
                400
            );
        }
    }
}