<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LookupOptionRequest;
use App\Models\LookupOption;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Settings - Lookup Options",
 *     description="Management of dynamic lookup options (specializations, categories)"
 * )
 */
class LookupOptionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/settings/lookup-options",
     *     operationId="getLookupOptions",
     *     tags={"Settings - Lookup Options"},
     *     summary="List lookup options",
     *     description="Get a list of lookup options optionally filtered by type",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         required=false,
     *         description="Filter by type (e.g., specialization, task_category)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lookup options retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Lookup options retrieved successfully")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = LookupOption::ordered();

        if ($request->has('type')) {
            $query->ofType($request->type);
        }

        $options = $query->get();

        return ApiResponse::success($options, 'Lookup options retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/settings/lookup-options",
     *     operationId="storeLookupOption",
     *     tags={"Settings - Lookup Options"},
     *     summary="Create a lookup option",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=201,
     *         description="Lookup option created successfully"
     *     )
     * )
     */
    public function store(LookupOptionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (!isset($validated['sort_order'])) {
            $maxOrder = LookupOption::ofType($validated['type'])->max('sort_order');
            $validated['sort_order'] = ($maxOrder !== null ? $maxOrder : 0) + 1;
        }

        $lookupOption = LookupOption::create($validated);

        return ApiResponse::success($lookupOption, 'Lookup option created successfully', 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/settings/lookup-options/{id}",
     *     operationId="updateLookupOption",
     *     tags={"Settings - Lookup Options"},
     *     summary="Update a lookup option",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Lookup option updated successfully")
     * )
     */
    public function update(LookupOptionRequest $request, LookupOption $lookupOption): JsonResponse
    {
        $lookupOption->update($request->validated());

        return ApiResponse::success($lookupOption, 'Lookup option updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/settings/lookup-options/{id}",
     *     operationId="deleteLookupOption",
     *     tags={"Settings - Lookup Options"},
     *     summary="Delete a lookup option",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Lookup option deleted successfully")
     * )
     */
    public function destroy(LookupOption $lookupOption): JsonResponse
    {
        if ($lookupOption->metadata['is_system'] ?? false) {
            return ApiResponse::forbidden('System-reserved lookup options cannot be deleted.');
        }

        if ($lookupOption->type === 'project_type' && Project::withoutGlobalScopes()
            ->withTrashed()
            ->where('project_type_id', $lookupOption->id)
            ->exists()) {
            return ApiResponse::error('Project types assigned to projects cannot be deleted.', 409);
        }

        $lookupOption->delete();

        return ApiResponse::success(null, 'Lookup option deleted successfully');
    }

    /**
     * @OA\Put(
     *     path="/api/v1/settings/lookup-options/reorder",
     *     operationId="reorderLookupOptions",
     *     tags={"Settings - Lookup Options"},
     *     summary="Reorder lookup options",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Lookup options reordered successfully")
     * )
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:lookup_options,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['items'] as $item) {
            LookupOption::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return ApiResponse::success(null, 'Lookup options reordered successfully');
    }
}
