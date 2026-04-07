<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskDependency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Task Dependencies",
 *     description="API endpoints for managing task dependencies and blocking relationships"
 * )
 */
class TaskDependencyController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tasks/{task}/dependencies",
     *     summary="Get all dependencies for a task",
     *     tags={"Task Dependencies"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="task",
     *         in="path",
     *         required=true,
     *         description="Task ID",
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
     *         description="Task dependencies retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task dependencies retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="dependencies",
     *                     type="array",
     *
     *                     @OA\Items(
     *
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="taskId", type="integer", example=5),
     *                         @OA\Property(property="dependsOnTaskId", type="integer", example=3),
     *                         @OA\Property(property="dependencyType", type="string", example="finish_to_start"),
     *                         @OA\Property(property="lagDays", type="integer", example=0),
     *                         @OA\Property(property="isSatisfied", type="boolean", example=false),
     *                         @OA\Property(
     *                             property="dependsOnTask",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=3),
     *                             @OA\Property(property="title", type="string", example="Design Database Schema"),
     *                             @OA\Property(property="status", type="string", example="in_progress")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="blockedBy",
     *                     type="array",
     *                     description="Tasks that are blocking this task",
     *
     *                     @OA\Items(type="object")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Task not found")
     * )
     */
    public function index(Task $task): JsonResponse
    {
        Gate::authorize('view', $task);

        $dependencies = $task->dependencies()
            ->with('dependsOnTask:id,title,status')
            ->get()
            ->map(function ($dep) {
                return [
                    'id' => $dep->id,
                    'taskId' => $dep->task_id,
                    'dependsOnTaskId' => $dep->depends_on_task_id,
                    'dependencyType' => $dep->dependency_type,
                    'lagDays' => $dep->lag_days,
                    'isSatisfied' => $dep->isSatisfied(),
                    'availableStartDate' => $dep->getAvailableStartDate()?->toDateString(),
                    'dependsOnTask' => [
                        'id' => $dep->dependsOnTask->id,
                        'title' => $dep->dependsOnTask->title,
                        'status' => $dep->dependsOnTask->status,
                    ],
                ];
            });

        $blockedBy = $task->blockedBy()
            ->with('task:id,title,status')
            ->get()
            ->map(function ($dep) {
                return [
                    'id' => $dep->id,
                    'taskId' => $dep->task_id,
                    'dependencyType' => $dep->dependency_type,
                    'lagDays' => $dep->lag_days,
                    'blockingTask' => [
                        'id' => $dep->task->id,
                        'title' => $dep->task->title,
                        'status' => $dep->task->status,
                    ],
                ];
            });

        return ApiResponse::success([
            'dependencies' => $dependencies,
            'blockedBy' => $blockedBy,
        ], __('Task dependencies retrieved successfully'));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tasks/{task}/dependencies",
     *     summary="Create a new task dependency",
     *     tags={"Task Dependencies"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="task",
     *         in="path",
     *         required=true,
     *         description="Task ID",
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
     *             required={"dependsOnTaskId"},
     *
     *             @OA\Property(property="dependsOnTaskId", type="integer", example=3),
     *             @OA\Property(property="dependencyType", type="string", enum={"finish_to_start", "start_to_start", "finish_to_finish", "start_to_finish"}, example="finish_to_start"),
     *             @OA\Property(property="lagDays", type="integer", example=0, description="Number of days to wait after dependency is satisfied")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Task dependency created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task dependency created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request, Task $task): JsonResponse
    {
        Gate::authorize('update', $task);

        $validated = $request->validate([
            'dependsOnTaskId' => ['required', 'integer', 'exists:tasks,id'],
            'dependencyType' => ['nullable', 'string', Rule::in([
                TaskDependency::TYPE_FINISH_TO_START,
                TaskDependency::TYPE_START_TO_START,
                TaskDependency::TYPE_FINISH_TO_FINISH,
                TaskDependency::TYPE_START_TO_FINISH,
            ])],
            'lagDays' => ['nullable', 'integer', 'min:0'],
        ]);

        // Prevent self-dependency
        if ($validated['dependsOnTaskId'] == $task->id) {
            return ApiResponse::error(__('A task cannot depend on itself'), [], 422);
        }

        // Check for circular dependencies
        if ($this->wouldCreateCircularDependency($task->id, $validated['dependsOnTaskId'])) {
            return ApiResponse::error(__('This dependency would create a circular reference'), [], 422);
        }

        // Check if dependency already exists
        $exists = TaskDependency::where('task_id', $task->id)
            ->where('depends_on_task_id', $validated['dependsOnTaskId'])
            ->exists();

        if ($exists) {
            return ApiResponse::error(__('This dependency already exists'), [], 422);
        }

        DB::beginTransaction();
        try {
            $dependency = TaskDependency::create([
                'task_id' => $task->id,
                'depends_on_task_id' => $validated['dependsOnTaskId'],
                'dependency_type' => $validated['dependencyType'] ?? TaskDependency::TYPE_FINISH_TO_START,
                'lag_days' => $validated['lagDays'] ?? 0,
            ]);

            $dependency->load('dependsOnTask:id,title,status');

            DB::commit();

            return ApiResponse::success([
                'id' => $dependency->id,
                'taskId' => $dependency->task_id,
                'dependsOnTaskId' => $dependency->depends_on_task_id,
                'dependencyType' => $dependency->dependency_type,
                'lagDays' => $dependency->lag_days,
                'isSatisfied' => $dependency->isSatisfied(),
                'dependsOnTask' => [
                    'id' => $dependency->dependsOnTask->id,
                    'title' => $dependency->dependsOnTask->title,
                    'status' => $dependency->dependsOnTask->status,
                ],
            ], __('Task dependency created successfully'), 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::error(__('Failed to create task dependency'), ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/task-dependencies/{dependency}",
     *     summary="Delete a task dependency",
     *     tags={"Task Dependencies"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="dependency",
     *         in="path",
     *         required=true,
     *         description="Task Dependency ID",
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
     *         description="Task dependency deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task dependency deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Dependency not found")
     * )
     */
    public function destroy(TaskDependency $dependency): JsonResponse
    {
        Gate::authorize('update', $dependency->task);

        $dependency->delete();

        return ApiResponse::success([], __('Task dependency deleted successfully'));
    }

    /**
     * Check if adding a dependency would create a circular reference
     */
    protected function wouldCreateCircularDependency(int $taskId, int $dependsOnTaskId): bool
    {
        // If the task we want to depend on already depends on us (directly or indirectly), it's circular
        return $this->taskDependsOn($dependsOnTaskId, $taskId);
    }

    /**
     * Recursively check if taskA depends on taskB
     */
    protected function taskDependsOn(int $taskA, int $taskB, array $visited = []): bool
    {
        // Prevent infinite loops
        if (in_array($taskA, $visited)) {
            return false;
        }

        $visited[] = $taskA;

        // Direct dependency
        $directDependency = TaskDependency::where('task_id', $taskA)
            ->where('depends_on_task_id', $taskB)
            ->exists();

        if ($directDependency) {
            return true;
        }

        // Check indirect dependencies
        $dependencies = TaskDependency::where('task_id', $taskA)
            ->pluck('depends_on_task_id');

        foreach ($dependencies as $depId) {
            if ($this->taskDependsOn($depId, $taskB, $visited)) {
                return true;
            }
        }

        return false;
    }
}
