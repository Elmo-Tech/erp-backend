<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Settings - Tags",
 *     description="Management of system tags"
 * )
 */
class TagController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
        $this->middleware('permission:manage_tags')->except('index');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/settings/tags",
     *     operationId="getSettingsTags",
     *     tags={"Settings - Tags"},
     *     summary="List all tags",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Tags retrieved successfully")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tag::query();

        if ($request->has('type')) {
            $query->ofType($request->type);
        }

        $tags = $query->orderBy('name')->get();

        return ApiResponse::success($tags, 'Tags retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/settings/tags",
     *     operationId="storeSettingsTag",
     *     tags={"Settings - Tags"},
     *     summary="Create a tag",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Tag created successfully")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:tags,name',
            'color' => 'nullable|string|max:20',
            'type' => 'nullable|string|max:50',
        ]);

        if (! isset($validated['type'])) {
            $validated['type'] = 'task';
        }

        $tag = Tag::create($validated);

        return ApiResponse::success($tag, 'Tag created successfully', 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/settings/tags/{id}",
     *     operationId="updateSettingsTag",
     *     tags={"Settings - Tags"},
     *     summary="Update a tag",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Tag updated successfully")
     * )
     */
    public function update(Request $request, Tag $tag): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('tags')->ignore($tag->id)],
            'color' => 'nullable|string|max:20',
            'type' => 'nullable|string|max:50',
        ]);

        $tag->update($validated);

        return ApiResponse::success($tag, 'Tag updated successfully');
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/settings/tags/{id}",
     *     operationId="deleteSettingsTag",
     *     tags={"Settings - Tags"},
     *     summary="Delete a tag",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Tag deleted successfully")
     * )
     */
    public function destroy(Tag $tag): JsonResponse
    {
        $tag->delete();

        return ApiResponse::success(null, 'Tag deleted successfully');
    }
}
