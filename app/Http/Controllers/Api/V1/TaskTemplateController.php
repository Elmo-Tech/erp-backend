<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\TaskTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Tag(
 *     name="Task Templates",
 *     description="API endpoints for managing reusable task templates"
 * )
 */
class TaskTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/task-templates",
     *     summary="Get all task templates",
     *     tags={"Task Templates"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         description="Language preference (en or ar)",
     *
     *         @OA\Schema(type="string", default="en")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[category]",
     *         in="query",
     *         required=false,
     *         description="Filter by category",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[is_active]",
     *         in="query",
     *         required=false,
     *         description="Filter by active status",
     *
     *         @OA\Schema(type="boolean")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         required=false,
     *         description="Search in name and description",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Items per page",
     *
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Task templates retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task templates retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="templates",
     *                     type="array",
     *
     *                     @OA\Items(
     *
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Bug Fix Template"),
     *                         @OA\Property(property="code", type="string", example="BUG_FIX"),
     *                         @OA\Property(property="description", type="string", example="Standard template for bug fixes"),
     *                         @OA\Property(property="category", type="string", example="development"),
     *                         @OA\Property(property="isActive", type="boolean", example=true),
     *                         @OA\Property(property="usageCount", type="integer", example=15)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=50),
     *                     @OA\Property(property="perPage", type="integer", example=15),
     *                     @OA\Property(property="currentPage", type="integer", example=1),
     *                     @OA\Property(property="totalPages", type="integer", example=4)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $templates = QueryBuilder::for(TaskTemplate::class)
            ->allowedFilters([
                'category',
                AllowedFilter::exact('is_active'),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                            ->orWhere('description', 'like', "%{$value}%");
                    });
                }),
            ])
            ->withCount('tasks')
            ->latest()
            ->paginate($request->get('per_page', $request->get('perPage', 15)));

        $items = $templates->map(function ($template) {
            return [
                'id' => $template->id,
                'name' => $template->name,
                'code' => $template->code,
                'description' => $template->description,
                'category' => $template->category,
                'defaultValues' => $template->default_values,
                'subtasks' => $template->subtasks,
                'isActive' => $template->is_active,
                'usageCount' => $template->tasks_count,
                'createdAt' => $template->created_at->toDateTimeString(),
                'updatedAt' => $template->updated_at->toDateTimeString(),
                'createdBy' => $template->created_by,
                'updatedBy' => $template->updated_by,
            ];
        });

        $data = [
            'items' => $items,
            'templates' => $items,
            'pagination' => [
                'total' => $templates->total(),
                'count' => $templates->count(),
                'perPage' => $templates->perPage(),
                'currentPage' => $templates->currentPage(),
                'totalPages' => $templates->lastPage(),
            ],
        ];

        return ApiResponse::success($data, __('Task templates retrieved successfully'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/task-templates/{template}",
     *     summary="Get a specific task template",
     *     tags={"Task Templates"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="template",
     *         in="path",
     *         required=true,
     *         description="Task Template ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         description="Language preference (en or ar)",
     *
     *         @OA\Schema(type="string", default="en")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Task template retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task template retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Bug Fix Template"),
     *                 @OA\Property(property="code", type="string", example="BUG_FIX"),
     *                 @OA\Property(property="description", type="string", example="Standard template for bug fixes"),
     *                 @OA\Property(property="category", type="string", example="development"),
     *                 @OA\Property(property="defaultValues", type="object"),
     *                 @OA\Property(property="subtasks", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="isActive", type="boolean", example=true),
     *                 @OA\Property(property="usageCount", type="integer", example=15)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Template not found")
     * )
     */
    public function show(TaskTemplate $template): JsonResponse
    {
        $template->loadCount('tasks');

        return ApiResponse::success([
            'id' => $template->id,
            'name' => $template->name,
            'code' => $template->code,
            'description' => $template->description,
            'category' => $template->category,
            'defaultValues' => $template->default_values,
            'subtasks' => $template->subtasks,
            'isActive' => $template->is_active,
            'usageCount' => $template->tasks_count,
            'createdAt' => $template->created_at->toDateTimeString(),
            'updatedAt' => $template->updated_at->toDateTimeString(),
        ], __('Task template retrieved successfully'));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/task-templates",
     *     summary="Create a new task template",
     *     tags={"Task Templates"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         description="Language preference (en or ar)",
     *
     *         @OA\Schema(type="string", default="en")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name", "code"},
     *
     *             @OA\Property(property="name", type="string", example="Bug Fix Template"),
     *             @OA\Property(property="code", type="string", example="BUG_FIX"),
     *             @OA\Property(property="description", type="string", example="Standard template for bug fixes"),
     *             @OA\Property(property="category", type="string", example="development"),
     *             @OA\Property(property="defaultValues", type="object", example={"priority": "high", "type": "bug"}),
     *             @OA\Property(
     *                 property="subtasks",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="title", type="string", example="Reproduce the bug"),
     *                     @OA\Property(property="description", type="string", example="Document steps to reproduce"),
     *                     @OA\Property(property="priority", type="string", example="high")
     *                 )
     *             ),
     *             @OA\Property(property="isActive", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Task template created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task template created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:task_templates,code'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'defaultValues' => ['nullable', 'array'],
            'subtasks' => ['nullable', 'array'],
            'subtasks.*.title' => ['required_with:subtasks', 'string'],
            'subtasks.*.description' => ['nullable', 'string'],
            'subtasks.*.priority' => ['nullable', 'string'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        DB::beginTransaction();
        try {
            $template = TaskTemplate::create([
                'name' => $validated['name'],
                'code' => $validated['code'],
                'description' => $validated['description'] ?? null,
                'category' => $validated['category'] ?? null,
                'default_values' => $validated['defaultValues'] ?? null,
                'subtasks' => $validated['subtasks'] ?? null,
                'is_active' => $validated['isActive'] ?? true,
            ]);

            DB::commit();

            return ApiResponse::success([
                'id' => $template->id,
                'name' => $template->name,
                'code' => $template->code,
                'description' => $template->description,
                'category' => $template->category,
                'defaultValues' => $template->default_values,
                'subtasks' => $template->subtasks,
                'isActive' => $template->is_active,
            ], __('Task template created successfully'), 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::error(__('Failed to create task template'), ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/task-templates/{template}",
     *     summary="Update a task template",
     *     tags={"Task Templates"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="template",
     *         in="path",
     *         required=true,
     *         description="Task Template ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         description="Language preference (en or ar)",
     *
     *         @OA\Schema(type="string", default="en")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="name", type="string", example="Bug Fix Template"),
     *             @OA\Property(property="description", type="string", example="Updated description"),
     *             @OA\Property(property="category", type="string", example="development"),
     *             @OA\Property(property="defaultValues", type="object"),
     *             @OA\Property(property="subtasks", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="isActive", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Task template updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task template updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Template not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, TaskTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'defaultValues' => ['nullable', 'array'],
            'subtasks' => ['nullable', 'array'],
            'subtasks.*.title' => ['required_with:subtasks', 'string'],
            'subtasks.*.description' => ['nullable', 'string'],
            'subtasks.*.priority' => ['nullable', 'string'],
            'isActive' => ['nullable', 'boolean'],
        ]);

        DB::beginTransaction();
        try {
            $updateData = [];
            if (isset($validated['name'])) {
                $updateData['name'] = $validated['name'];
            }
            if (isset($validated['description'])) {
                $updateData['description'] = $validated['description'];
            }
            if (isset($validated['category'])) {
                $updateData['category'] = $validated['category'];
            }
            if (isset($validated['defaultValues'])) {
                $updateData['default_values'] = $validated['defaultValues'];
            }
            if (isset($validated['subtasks'])) {
                $updateData['subtasks'] = $validated['subtasks'];
            }
            if (isset($validated['isActive'])) {
                $updateData['is_active'] = $validated['isActive'];
            }

            $template->update($updateData);

            DB::commit();

            return ApiResponse::success([
                'id' => $template->id,
                'name' => $template->name,
                'code' => $template->code,
                'description' => $template->description,
                'category' => $template->category,
                'defaultValues' => $template->default_values,
                'subtasks' => $template->subtasks,
                'isActive' => $template->is_active,
            ], __('Task template updated successfully'));
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::error(__('Failed to update task template'), ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/task-templates/{template}",
     *     summary="Delete a task template",
     *     tags={"Task Templates"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="template",
     *         in="path",
     *         required=true,
     *         description="Task Template ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         description="Language preference (en or ar)",
     *
     *         @OA\Schema(type="string", default="en")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Task template deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task template deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Template not found")
     * )
     */
    public function destroy(TaskTemplate $template): JsonResponse
    {
        $template->delete();

        return ApiResponse::success([], __('Task template deleted successfully'));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/task-templates/{template}/instantiate",
     *     summary="Create a task from a template",
     *     tags={"Task Templates"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="template",
     *         in="path",
     *         required=true,
     *         description="Task Template ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=false,
     *         description="Language preference (en or ar)",
     *
     *         @OA\Schema(type="string", default="en")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"projectId", "title"},
     *
     *             @OA\Property(property="projectId", type="integer", example=5),
     *             @OA\Property(property="title", type="string", example="Fix login bug"),
     *             @OA\Property(property="description", type="string", example="Users cannot login with email"),
     *             @OA\Property(property="assignedTo", type="integer", example=3),
     *             @OA\Property(property="dueDate", type="string", format="date", example="2026-03-01")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Task created from template successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task created from template successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Template not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function instantiate(Request $request, TaskTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'projectId' => ['required', 'integer', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assignedTo' => ['nullable', 'integer', 'exists:users,id'],
            'dueDate' => ['nullable', 'date'],
        ]);

        DB::beginTransaction();
        try {
            $task = $template->createTask([
                'project_id' => $validated['projectId'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'assigned_to' => $validated['assignedTo'] ?? null,
                'due_date' => $validated['dueDate'] ?? null,
            ]);

            DB::commit();

            return ApiResponse::success([
                'id' => $task->id,
                'title' => $task->title,
                'projectId' => $task->project_id,
                'templateId' => $task->template_id,
            ], __('Task created from template successfully'), 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::error(__('Failed to create task from template'), ['error' => $e->getMessage()], 500);
        }
    }
}
