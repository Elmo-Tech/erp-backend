<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Tasks\AddAssigneeRequest;
use App\Http\Requests\V1\Tasks\AddCommentRequest;
use App\Http\Requests\V1\Tasks\MarkCompleteRequest;
use App\Http\Requests\V1\Tasks\StoreTaskRequest;
use App\Http\Requests\V1\Tasks\StoreTestScenarioRequest;
use App\Http\Requests\V1\Tasks\UpdateTaskRequest;
use App\Http\Requests\V1\Tasks\UpdateTaskStatusRequest;
use App\Http\Requests\V1\Tasks\VerifyTestScenarioRequest;
use App\Http\Resources\V1\Tasks\TagResource;
use App\Http\Resources\V1\Tasks\TaskActivityResource;
use App\Http\Resources\V1\Tasks\TaskAssigneeResource;
use App\Http\Resources\V1\Tasks\TaskCollection;
use App\Http\Resources\V1\Tasks\TaskResource;
use App\Http\Resources\V1\Tasks\TaskTestScenarioResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskTestScenario;
use App\Services\Projects\ProjectAccess;
use App\Services\Tasks\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Tasks",
 *     description="Task management endpoints"
 * )
 */
class TaskController extends Controller
{
    public function __construct(
        private TaskService $taskService,
        private ProjectAccess $projectAccess
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
        $this->middleware(function ($request, $next) {
            if ($request->user()?->client()->exists()) {
                return ApiResponse::error(__('messages.unauthorized'), 403);
            }

            $task = $request->route('task');
            if ($task instanceof Task) {
                $task->loadMissing('project');
                $project = $task->project;
                if ($project instanceof Project && ! $this->projectAccess->canView($request->user(), $project)) {
                    return ApiResponse::forbidden('You do not have access to this task project.');
                }
            }

            return $next($request);
        });
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tasks",
     *     operationId="getTasks",
     *     tags={"Tasks"},
     *     summary="Get all tasks with pagination and filtering",
     *     description="Retrieve a paginated list of tasks with optional filtering by project, status, assignee, and search",
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
     *         name="filter[projectId]",
     *         in="query",
     *         description="Filter tasks by project ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[status]",
     *         in="query",
     *         description="Filter tasks by status",
     *
     *         @OA\Schema(type="string", enum={"todo", "in_progress", "review", "testing", "completed"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[assignedTo]",
     *         in="query",
     *         description="Filter tasks by assigned user ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[parentId]",
     *         in="query",
     *         description="Filter tasks by parent ID (use null for root tasks)",
     *
     *         @OA\Schema(type="integer", nullable=true)
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Search tasks by title",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[type]",
     *         in="query",
     *         description="Filter tasks by type",
     *
     *         @OA\Schema(type="string", enum={"task", "bug", "feature", "improvement"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[priority]",
     *         in="query",
     *         description="Filter tasks by priority",
     *
     *         @OA\Schema(type="string", enum={"low", "medium", "high", "urgent"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[tagId]",
     *         in="query",
     *         description="Filter tasks by tag ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Number of items per page",
     *
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=15)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tasks retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tasks retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="tasks",
     *                     type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/TaskResource")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=50),
     *                     @OA\Property(property="count", type="integer", example=15),
     *                     @OA\Property(property="perPage", type="integer", example=15),
     *                     @OA\Property(property="currentPage", type="integer", example=1),
     *                     @OA\Property(property="totalPages", type="integer", example=4)
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
    public function index(Request $request): JsonResponse
    {
        $filters = [];

        if ($request->has('filter.projectId')) {
            $filters['projectId'] = $request->input('filter.projectId');
        }

        if ($request->has('filter.status')) {
            $filters['status'] = $request->input('filter.status');
        }

        if ($request->has('filter.assignedTo')) {
            $filters['assignedTo'] = $request->input('filter.assignedTo');
        }

        if ($request->has('filter.parentId')) {
            $parentId = $request->input('filter.parentId');
            // Convert string "null" to actual null for root tasks query
            $filters['parentId'] = ($parentId === 'null' || $parentId === '') ? null : $parentId;
        }

        if ($request->has('filter.search')) {
            $filters['search'] = $request->input('filter.search');
        }

        // Handle type filter
        if ($request->has('filter.type')) {
            $filters['type'] = $request->input('filter.type');
        }

        // Handle priority filter
        if ($request->has('filter.priority')) {
            $filters['priority'] = $request->input('filter.priority');
        }

        // Handle tagId filter
        if ($request->has('filter.tagId')) {
            $filters['tagId'] = $request->input('filter.tagId');
        }

        $perPage = $request->get('perPage', 15);
        $tasks = $this->taskService->getAllTasks($filters, $perPage);

        return ApiResponse::success(
            new TaskCollection($tasks),
            'Tasks retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tasks",
     *     operationId="createTask",
     *     tags={"Tasks"},
     *     summary="Create a new task",
     *     description="Create a new task within a project",
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
     *
     *         @OA\JsonContent(
     *             required={"projectId", "title"},
     *
     *             @OA\Property(property="projectId", type="integer", example=1),
     *             @OA\Property(property="parentId", type="integer", nullable=true, example=null),
     *             @OA\Property(property="assignedTo", type="integer", nullable=true, example=2),
     *             @OA\Property(property="title", type="string", example="Implement user authentication"),
     *             @OA\Property(property="description", type="string", example="Add JWT-based authentication to the API"),
     *             @OA\Property(property="status", type="string", enum={"todo", "in_progress", "review", "testing", "completed"}, example="todo"),
     *             @OA\Property(property="estimatedHours", type="number", format="float", example=8.5),
     *             @OA\Property(property="internalReviewerNotes", type="string", example="Review security implementation carefully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Task created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/TaskResource")
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
     *             @OA\Property(property="message", type="string", example="Validation failed for task creation"),
     *             @OA\Property(property="errors", type="object")
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
    public function store(StoreTaskRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();
            $user = $request->user();

            $task = $this->taskService->createTask($data, $user);

            return ApiResponse::success(
                new TaskResource($task),
                'Task created successfully',
                201
            );
        });
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tasks/{id}",
     *     operationId="getTask",
     *     tags={"Tasks"},
     *     summary="Get a single task",
     *     description="Retrieve a specific task by ID with related data",
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
     *         description="Task ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Task retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/TaskResource")
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
    public function show(Task $task): JsonResponse
    {
        $task->load(['assignee', 'supervisor', 'project', 'parent', 'children.assignee', 'children.supervisor', 'assignees.user', 'tags']);

        return ApiResponse::success(
            new TaskResource($task),
            'Task retrieved successfully'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/tasks/{id}",
     *     operationId="updateTask",
     *     tags={"Tasks"},
     *     summary="Update an existing task",
     *     description="Update a task with status transition validation",
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
     *         description="Task ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="parentId", type="integer", nullable=true, example=null),
     *             @OA\Property(property="assignedTo", type="integer", nullable=true, example=2),
     *             @OA\Property(property="title", type="string", example="Updated task title"),
     *             @OA\Property(property="description", type="string", example="Updated task description"),
     *             @OA\Property(property="status", type="string", enum={"todo", "in_progress", "review", "testing", "completed"}, example="in_progress"),
     *             @OA\Property(property="estimatedHours", type="number", format="float", example=10.0),
     *             @OA\Property(property="internalReviewerNotes", type="string", example="Updated internal notes")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Task updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/TaskResource")
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
     *             @OA\Property(property="message", type="string", example="Validation failed for task update"),
     *             @OA\Property(property="errors", type="object")
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
    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        return DB::transaction(function () use ($request, $task) {
            $data = $request->validated();
            $user = $request->user();

            $task = $this->taskService->updateTask($task, $data, $user);

            return ApiResponse::success(
                new TaskResource($task),
                'Task updated successfully'
            );
        });
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tasks/{id}",
     *     operationId="deleteTask",
     *     tags={"Tasks"},
     *     summary="Delete a task",
     *     description="Delete a specific task by ID. Child tasks will also be deleted (cascade).",
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
     *         description="Task ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Task deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task deleted successfully"),
     *             @OA\Property(property="data", type="null")
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
    public function destroy(Task $task): JsonResponse
    {
        $this->taskService->deleteTask($task);

        return ApiResponse::success(
            null,
            'Task deleted successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tasks/{id}/children",
     *     operationId="getTaskChildren",
     *     tags={"Tasks"},
     *     summary="Get children of a task",
     *     description="Retrieve all direct children of a specific task for lazy loading",
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
     *         description="Parent Task ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Task children retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task children retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/TaskResource")
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
    public function children(Task $task): JsonResponse
    {
        $children = $this->taskService->getTaskChildren($task->id);

        return ApiResponse::success(
            TaskResource::collection($children),
            'Task children retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/projects/{projectId}/tasks",
     *     operationId="getProjectTasks",
     *     tags={"Tasks"},
     *     summary="Get tasks for a project with hierarchy",
     *     description="Retrieve root-level tasks for a project with their children loaded",
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
     *         name="projectId",
     *         in="path",
     *         required=true,
     *         description="Project ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Number of items per page",
     *
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=15)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Project tasks retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Project tasks retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="tasks",
     *                     type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/TaskResource")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=50),
     *                     @OA\Property(property="count", type="integer", example=15),
     *                     @OA\Property(property="perPage", type="integer", example=15),
     *                     @OA\Property(property="currentPage", type="integer", example=1),
     *                     @OA\Property(property="totalPages", type="integer", example=4)
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
    public function projectTasks(Request $request, int $projectId): JsonResponse
    {
        $project = Project::withoutGlobalScopes()->findOrFail($projectId);
        abort_unless($this->projectAccess->canViewTasks($request->user(), $project), 403);

        $perPage = $request->get('perPage', 15);
        $tasks = $this->taskService->getTasksForProject($projectId, $perPage);

        return ApiResponse::success(
            new TaskCollection($tasks),
            'Project tasks retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tags",
     *     operationId="getTags",
     *     tags={"Tasks"},
     *     summary="Get all available tags",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Tags retrieved successfully")
     * )
     */
    public function tags(): JsonResponse
    {
        $tags = $this->taskService->getAllTags();

        return ApiResponse::success(
            TagResource::collection($tags),
            'Tags retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tasks/{task}/assignees",
     *     operationId="addTaskAssignee",
     *     tags={"Tasks"},
     *     summary="Add an assignee to a task",
     *     description="Add a team member to any task type. When adding to Main Task, propagates to Weekly Tasks only (not Subtasks). Weekly/Subtasks can be managed independently. Requires manage_task_team permission.",
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
     *         name="task",
     *         in="path",
     *         required=true,
     *         description="Task ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"userId", "role"},
     *
     *             @OA\Property(property="userId", type="integer", example=2),
     *             @OA\Property(property="role", type="string", enum={"developer", "reviewer", "lead"}, example="developer"),
     *             @OA\Property(property="isPrimary", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Assignee added successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Assignee added successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/TaskAssigneeResource")
     *         )
     *     ),
     *
     *     @OA\Response(response=400, description="User not in team or already assigned"),
     *     @OA\Response(response=403, description="Unauthorized to manage team")
     * )
     */
    public function addAssignee(AddAssigneeRequest $request, Task $task): JsonResponse
    {
        // Check authorization using policy
        $user = $request->user();
        if (! $user->can('manageTeam', $task)) {
            return ApiResponse::error(
                __('messages.no_permission_manage_team'),
                403
            );
        }

        try {
            return DB::transaction(function () use ($request, $task) {
                $data = $request->validated();
                $user = $request->user();

                $assignee = $this->taskService->addAssignee(
                    $task,
                    $data['userId'],
                    $data['role'],
                    $data['isPrimary'] ?? false,
                    $user
                );

                return ApiResponse::success(
                    new TaskAssigneeResource($assignee),
                    'Assignee added successfully',
                    201
                );
            });
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tasks/{task}/assignees/{userId}",
     *     operationId="removeTaskAssignee",
     *     tags={"Tasks"},
     *     summary="Remove an assignee from a task",
     *     description="Remove an assignee from any task type. When removing from Main Task, also removes from Weekly Tasks only (not Subtasks). Weekly/Subtasks can be managed independently. Requires manage_task_team permission.",
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
     *         name="task",
     *         in="path",
     *         required=true,
     *         description="Task ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="User ID to remove",
     *
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Assignee removed successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Assignee removed successfully")
     *         )
     *     ),
     *
     *     @OA\Response(response=403, description="Unauthorized to manage team"),
     *     @OA\Response(response=404, description="Assignee not found")
     * )
     */
    public function removeAssignee(Request $request, Task $task, int $userId): JsonResponse
    {
        // Check authorization using policy
        $user = $request->user();
        if (! $user->can('manageTeam', $task)) {
            return ApiResponse::error(
                __('messages.no_permission_manage_team'),
                403
            );
        }

        try {
            $removed = $this->taskService->removeAssignee($task, $userId, $user);

            if (! $removed) {
                return ApiResponse::error('Assignee not found', 404);
            }

            return ApiResponse::success(null, 'Assignee removed successfully');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tasks/{task}/test-scenarios",
     *     operationId="getTaskTestScenarios",
     *     tags={"Tasks"},
     *     summary="Get test scenarios for a task",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Test scenarios retrieved successfully")
     * )
     */
    public function testScenarios(Task $task): JsonResponse
    {
        $task->load(['testScenarios.completedBy', 'testScenarios.verifiedBy']);

        return ApiResponse::success(
            TaskTestScenarioResource::collection($task->testScenarios),
            'Test scenarios retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tasks/{task}/test-scenarios",
     *     operationId="addTaskTestScenario",
     *     tags={"Tasks"},
     *     summary="Add a test scenario to a task",
     *     description="Add a test scenario to a Weekly Task or Subtask. Main Tasks cannot have test scenarios. Weekly tasks require manage_task_test_scenarios permission. Subtasks allow assigned users to manage.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"title"},
     *
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="expectedResult", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Test scenario added successfully"),
     *     @OA\Response(response=400, description="Cannot add test scenario to Main Task"),
     *     @OA\Response(response=403, description="Unauthorized to manage test scenarios on this task type")
     * )
     */
    public function addTestScenario(StoreTestScenarioRequest $request, Task $task): JsonResponse
    {
        // Check authorization using policy
        $user = $request->user();
        if (! $user->can('manageTestScenarios', $task)) {
            return ApiResponse::error(
                __('messages.no_permission_manage_test_scenarios'),
                403
            );
        }

        try {
            return DB::transaction(function () use ($request, $task) {
                $data = $request->validated();
                $user = $request->user();

                $scenario = $this->taskService->addTestScenario($task, $data, $user);

                return ApiResponse::success(
                    new TaskTestScenarioResource($scenario->load(['completedBy', 'verifiedBy'])),
                    'Test scenario added successfully',
                    201
                );
            });
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/test-scenarios/{scenario}/complete",
     *     operationId="completeTestScenario",
     *     tags={"Tasks"},
     *     summary="Mark a test scenario as completed (check) - Available to all users",
     *     description="Any user can check/complete a test scenario. This marks it as checked by the developer.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Test scenario completed successfully")
     * )
     */
    public function completeTestScenario(Request $request, TaskTestScenario $scenario): JsonResponse
    {
        // No authorization check - all authenticated users can check/complete test scenarios
        return DB::transaction(function () use ($request, $scenario) {
            $user = $request->user();
            $scenario = $this->taskService->completeTestScenario($scenario, $user);

            return ApiResponse::success(
                new TaskTestScenarioResource($scenario->load(['completedBy', 'verifiedBy'])),
                'Test scenario completed successfully'
            );
        });
    }

    /**
     * @OA\Post(
     *     path="/api/v1/test-scenarios/{scenario}/verify",
     *     operationId="verifyTestScenario",
     *     tags={"Tasks"},
     *     summary="Verify a test scenario - Only for Admin and Task Reviewers",
     *     description="Only users with Admin role or assigned as Reviewer in the task team can verify test scenarios. This is the final approval step.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"passed"},
     *
     *             @OA\Property(property="passed", type="boolean"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Test scenario verified successfully"),
     *     @OA\Response(response=403, description="Unauthorized - Only Admin and Task Reviewers can verify")
     * )
     */
    public function verifyTestScenario(VerifyTestScenarioRequest $request, TaskTestScenario $scenario): JsonResponse
    {
        $user = $request->user();

        // Load the task with assignees
        $task = $scenario->task()->with('assignees')->first();

        // Check if user is Admin/Super Admin
        $isAdmin = $user->hasAnyRole(['admin', 'super-admin']);

        // Check if user is assigned as reviewer in this task
        $isTaskReviewer = $task->assignees()
            ->where('user_id', $user->id)
            ->where('role', 'reviewer')
            ->exists();

        // Only Admin or Task Reviewer can verify
        if (! $isAdmin && ! $isTaskReviewer) {
            return ApiResponse::error(
                __('messages.no_permission_verify_test_scenarios'),
                403
            );
        }

        return DB::transaction(function () use ($request, $scenario) {
            $data = $request->validated();
            $user = $request->user();

            $scenario = $this->taskService->verifyTestScenario(
                $scenario,
                $user,
                $data['passed'],
                $data['notes'] ?? null
            );

            return ApiResponse::success(
                new TaskTestScenarioResource($scenario->load(['completedBy', 'verifiedBy'])),
                'Test scenario verified successfully'
            );
        });
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tasks/{task}/activities",
     *     operationId="getTaskActivities",
     *     tags={"Tasks"},
     *     summary="Get activity log for a task",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Activities retrieved successfully")
     * )
     */
    public function activities(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();
        $includeInternal = $user && $user->can('view_internal_notes');

        $activities = $this->taskService->getActivities($task, $includeInternal);

        return ApiResponse::success(
            TaskActivityResource::collection($activities),
            'Activities retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tasks/{task}/comments",
     *     operationId="addTaskComment",
     *     tags={"Tasks"},
     *     summary="Add a comment to a task",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"comment"},
     *
     *             @OA\Property(property="comment", type="string"),
     *             @OA\Property(property="isInternal", type="boolean")
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Comment added successfully")
     * )
     */
    public function addComment(AddCommentRequest $request, Task $task): JsonResponse
    {
        return DB::transaction(function () use ($request, $task) {
            $data = $request->validated();
            $user = $request->user();

            $activity = $this->taskService->addComment(
                $task,
                $user,
                $data['comment'],
                $data['isInternal'] ?? false
            );

            return ApiResponse::success(
                new TaskActivityResource($activity->load('user')),
                'Comment added successfully',
                201
            );
        });
    }

    /**
     * @OA\Get(
     *     path="/api/v1/projects/{projectId}/points-summary",
     *     operationId="getProjectPointsSummary",
     *     tags={"Tasks"},
     *     summary="Get user points summary for a project",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Points summary retrieved successfully")
     * )
     */
    public function pointsSummary(int $projectId): JsonResponse
    {
        $project = Project::withoutGlobalScopes()->findOrFail($projectId);
        abort_unless($this->projectAccess->canViewTasks(request()->user(), $project), 403);

        if (config('simplification.enabled')) {
            return ApiResponse::forbidden('Points are disabled in the simplified ERP version.');
        }

        $summary = $this->taskService->getUserPointsSummary($projectId);

        return ApiResponse::success(
            $summary->map(fn ($item) => [
                'userId' => $item->user_id,
                'userName' => $item->user?->name ?? 'Unknown',
                'totalPoints' => (int) $item->total_points,
            ]),
            'Points summary retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tasks/my-assigned",
     *     operationId="getMyAssignedTasks",
     *     tags={"Tasks"},
     *     summary="Get tasks where current user is assigned as team member",
     *     description="Retrieve tasks where the authenticated user is assigned as a team member (in task_assignees table)",
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
     *         name="filter[projectId]",
     *         in="query",
     *         description="Filter tasks by project ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[status]",
     *         in="query",
     *         description="Filter tasks by status",
     *
     *         @OA\Schema(type="string", enum={"todo", "in_progress", "review", "testing", "completed"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[type]",
     *         in="query",
     *         description="Filter tasks by type",
     *
     *         @OA\Schema(type="string", enum={"main", "weekly", "subtask"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[priority]",
     *         in="query",
     *         description="Filter tasks by priority",
     *
     *         @OA\Schema(type="string", enum={"low", "medium", "high", "urgent"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Search tasks by title",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Number of items per page",
     *
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=15)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="My assigned tasks retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="My assigned tasks retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="tasks",
     *                     type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/TaskResource")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=25),
     *                     @OA\Property(property="count", type="integer", example=15),
     *                     @OA\Property(property="perPage", type="integer", example=15),
     *                     @OA\Property(property="currentPage", type="integer", example=1),
     *                     @OA\Property(property="totalPages", type="integer", example=2)
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
    public function myAssignedTasks(Request $request): JsonResponse
    {
        $user = $request->user();
        $filters = [];

        if ($request->has('filter.projectId')) {
            $filters['projectId'] = $request->input('filter.projectId');
        }

        if ($request->has('filter.status')) {
            $filters['status'] = $request->input('filter.status');
        }

        if ($request->has('filter.type')) {
            $filters['type'] = $request->input('filter.type');
        }

        if ($request->has('filter.priority')) {
            $filters['priority'] = $request->input('filter.priority');
        }

        if ($request->has('filter.search')) {
            $filters['search'] = $request->input('filter.search');
        }

        $perPage = $request->get('perPage', 15);
        $tasks = $this->taskService->getMyAssignedTasks($user, $filters, $perPage);

        return ApiResponse::success(
            new TaskCollection($tasks),
            'My assigned tasks retrieved successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tasks/{id}/status",
     *     operationId="updateTaskStatus",
     *     tags={"Tasks"},
     *     summary="Update task status",
     *     description="Update task status with flexible transitions - allows changing to any status directly",
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
     *         description="Task ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"status"},
     *
     *             @OA\Property(property="status", type="string", enum={"todo", "in_progress", "review", "testing", "completed"}, example="completed"),
     *             @OA\Property(property="reason", type="string", example="Task completed ahead of schedule")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Task status updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task status updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/TaskResource")
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
     *             @OA\Property(property="message", type="string", example="Validation failed for task status update"),
     *             @OA\Property(property="errors", type="object")
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
    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): JsonResponse
    {
        return DB::transaction(function () use ($request, $task) {
            $data = $request->validated();
            $user = $request->user();

            $task = $this->taskService->updateTaskStatus(
                $task,
                $data['status'],
                $user,
                $data['reason'] ?? null
            );

            return ApiResponse::success(
                new TaskResource($task->load(['assignee', 'supervisor', 'project', 'parent', 'children.assignee', 'children.supervisor', 'assignees.user', 'tags'])),
                __('messages.task_status_updated')
            );
        });
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tasks/{id}/mark-complete",
     *     operationId="markTaskComplete",
     *     tags={"Tasks"},
     *     summary="Mark a task as complete",
     *     description="Mark a task as complete. Only primary assignees can mark tasks as complete, and active timers must be stopped first.",
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
     *         description="Task ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=false,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="reason", type="string", example="Task completed successfully", maxLength=500)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Task marked as complete successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Task marked as complete successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/TaskResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Validation error - Timer active or already completed",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Please stop the active timer before marking task as complete")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - Only primary assignees can mark tasks as complete",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Only primary assignees can mark tasks as complete")
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
    public function markComplete(MarkCompleteRequest $request, Task $task): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request, $task) {
                $data = $request->validated();
                $user = $request->user();

                $task = $this->taskService->markAsComplete(
                    $task,
                    $user,
                    $data['reason'] ?? null
                );

                return ApiResponse::success(
                    new TaskResource($task->load(['assignee', 'supervisor', 'project', 'parent', 'children.assignee', 'children.supervisor', 'assignees.user', 'tags'])),
                    __('messages.task_marked_complete')
                );
            });
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }
    }
}
