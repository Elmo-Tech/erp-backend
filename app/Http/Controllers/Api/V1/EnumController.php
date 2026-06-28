<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TaskCategory;
use App\Enums\UserSpecialization;
use App\Models\LookupOption;
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
     *         description="User specializations retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User specializations retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *
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
        $options = LookupOption::getActiveByType('specialization')->map(function ($option) {
            return [
                'value' => $option->value,
                'label' => $option->label,
                'color' => $option->color,
                'metadata' => $option->metadata,
            ];
        })->toArray();

        return ApiResponse::success(
            $options,
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
     *         description="Task categories retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task categories retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *
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
        $options = LookupOption::getActiveByType('task_category')->map(function ($option) {
            return [
                'value' => $option->value,
                'label' => $option->label,
                'color' => $option->color,
            ];
        })->toArray();

        return ApiResponse::success(
            $options,
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
     *         name="specialization",
     *         in="path",
     *         required=true,
     *         description="User specialization",
     *
     *         @OA\Schema(type="string", enum={"backend", "frontend", "fullstack", "ui_ux", "mobile", "devops", "qa"})
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Compatible task categories retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Compatible task categories retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="value", type="string", example="backend"),
     *                     @OA\Property(property="label", type="string", example="Backend Development"),
     *                     @OA\Property(property="color", type="string", example="bg-blue-100 text-blue-800")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Invalid specialization",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid specialization provided")
     *         )
     *     )
     * )
     */
    public function getCompatibleTaskCategories(string $specialization): JsonResponse
    {
        try {
            $userSpec = LookupOption::ofType('specialization')->where('value', $specialization)->first();
            
            if (!$userSpec) {
                return ApiResponse::error('Invalid specialization provided', 400);
            }

            $compatibleTypes = $userSpec->metadata['compatible_task_types'] ?? [];

            $options = LookupOption::getActiveByType('task_category')
                ->filter(function ($category) use ($compatibleTypes) {
                    return in_array($category->value, $compatibleTypes);
                })
                ->map(function ($category) {
                    return [
                        'value' => $category->value,
                        'label' => $category->label,
                        'color' => $category->color,
                    ];
                })->values()->toArray();

            return ApiResponse::success(
                $options,
                'Compatible task categories retrieved successfully'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Error retrieving compatible categories: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get all active project statuses from lookup options.
     */
    public function getProjectStatuses(): JsonResponse
    {
        $options = LookupOption::getActiveByType('project_status')->map(function ($option) {
            return [
                'value' => $option->value,
                'label' => $option->label,
                'color' => $option->color,
            ];
        })->toArray();

        return ApiResponse::success(
            $options,
            'Project statuses retrieved successfully'
        );
    }

    /**
     * Get all active task statuses from lookup options.
     */
    public function getTaskStatuses(): JsonResponse
    {
        $options = LookupOption::getActiveByType('task_status')->map(function ($option) {
            return [
                'value' => $option->value,
                'label' => $option->label,
                'color' => $option->color,
            ];
        })->toArray();

        return ApiResponse::success(
            $options,
            'Task statuses retrieved successfully'
        );
    }
}
