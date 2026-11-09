<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LookupOptionRequest;
use App\Models\LookupOption;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Support\PermissionCatalog;
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
     *
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         required=false,
     *         description="Filter by type (e.g., specialization, task_category)",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Lookup options retrieved successfully",
     *
     *         @OA\JsonContent(
     *
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
     *
     *     @OA\Response(
     *         response=201,
     *         description="Lookup option created successfully"
     *     )
     * )
     */
    public function store(LookupOptionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (! isset($validated['sort_order'])) {
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
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
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
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Lookup option deleted successfully")
     * )
     */
    public function destroy(LookupOption $lookupOption): JsonResponse
    {
        $permission = PermissionCatalog::lookupPermission($lookupOption->type);
        abort_unless(auth()->user()->hasAnyPermission(['manage_lookup_options', $permission, 'delete'.substr($permission, strlen('manage'))]), 403);
        // Prevent deleting lookup options that are currently in use, to avoid
        // breaking existing records and UI labels.
        $inUse = match ($lookupOption->type) {
            'specialization' => User::where('specialization', $lookupOption->value)->exists(),
            'project_status' => Project::withoutGlobalScopes()->withTrashed()
                ->where(function ($query) use ($lookupOption) {
                    $query->where('status', $lookupOption->value)
                        ->orWhere('previous_status', $lookupOption->value);
                })->exists(),
            'task_status' => Task::where('status', $lookupOption->value)->exists(),
            'task_category' => Task::where('task_category', $lookupOption->value)->exists(),
            'project_type' => Task::withTrashed()->whereHas('projectTypes', fn ($query) => $query->where('lookup_options.id', $lookupOption->id))->exists(),
            default => false,
        };

        if ($inUse) {
            return ApiResponse::error('Cannot delete "'.$lookupOption->label.'": it is currently in use by existing records. Deactivate it instead to hide it from new selections while preserving existing data.', 409);
        }

        if ($lookupOption->type === 'project_type' && Project::withoutGlobalScopes()
            ->withTrashed()
            ->where('project_type_id', $lookupOption->id)
            ->exists()) {
            $projects = Project::withoutGlobalScopes()->where('project_type_id', $lookupOption->id)->get(['id', 'title', 'deleted_at']);
            $names = $projects->take(8)->map(fn ($project) => '"'.$project->title.'"'.($project->deleted_at ? ' (in trash)' : ''))->implode(', ');

            return ApiResponse::error('Cannot delete "'.$lookupOption->label.'": it is linked to '.$projects->count().' project(s): '.$names.'. Deleting the type would break their classification and reports. Reassign those projects to another type first, including projects in trash, or deactivate this type to keep existing links and hide it from new selections.', 409);
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
     *
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

        foreach (LookupOption::whereIn('id', array_column($validated['items'], 'id'))->get() as $option) {
            $permission = PermissionCatalog::lookupPermission($option->type);
            abort_unless($request->user()->hasAnyPermission(['manage_lookup_options', $permission, 'reorder'.substr($permission, strlen('manage'))]), 403);
        }

        foreach ($validated['items'] as $item) {
            LookupOption::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return ApiResponse::success(null, 'Lookup options reordered successfully');
    }
}
