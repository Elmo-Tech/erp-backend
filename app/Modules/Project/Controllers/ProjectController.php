<?php

namespace App\Modules\Project\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Project\Actions\ApproveProjectUiAction;
use App\Modules\Project\Actions\ChangeProjectStatusAction;
use App\Modules\Project\Actions\CreateDirectProjectAction;
use App\Modules\Project\Actions\CreateProjectFromQuotationAction;
use App\Modules\Project\Actions\DeleteProjectAction;
use App\Modules\Project\Actions\ListPinnedProjectsAction;
use App\Modules\Project\Actions\ListProjectsAction;
use App\Modules\Project\Actions\PinProjectAction;
use App\Modules\Project\Actions\UnpinProjectAction;
use App\Modules\Project\Actions\UpdateProjectAction;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Requests\ConvertQuotationRequest;
use App\Modules\Project\Requests\StoreDirectProjectRequest;
use App\Modules\Project\Requests\StoreProjectRequest;
use App\Modules\Project\Requests\UpdateProjectRequest;
use App\Modules\Project\Requests\UpdateProjectStatusRequest;
use App\Modules\Project\Resources\ProjectCollection;
use App\Modules\Project\Resources\ProjectResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use OpenApi\Annotations as OA;
use RuntimeException;

class ProjectController extends Controller
{
    public function __construct(
        private UpdateProjectAction $updateProjectAction,
        private ApproveProjectUiAction $approveProjectUiAction,
        private PinProjectAction $pinProjectAction,
        private UnpinProjectAction $unpinProjectAction
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/projects",
     *     operationId="getProjects",
     *     tags={"Projects"},
     *     summary="Get all projects with pagination and filtering",
     *     description="Retrieve a paginated list of projects with optional filtering by status, client_id, and search",
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
     *         name="filter[status]",
     *         in="query",
     *         description="Filter projects by status",
     *
     *         @OA\Schema(type="string", enum={"draft", "active", "ui_ux", "development", "testing", "completed", "on_hold", "cancelled"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[clientId]",
     *         in="query",
     *         description="Filter projects by client ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Search projects by title",
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
     *         description="Projects retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Projects retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="projects",
     *                     type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/ProjectResource")
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
    public function index(Request $request, ListProjectsAction $action): JsonResponse
    {
        return ApiResponse::success(
            new ProjectCollection($action->execute($request->user(), (int) $request->get('perPage', 15))),
            'Projects retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/projects",
     *     operationId="createProject",
     *     tags={"Projects"},
     *     summary="Create a new project",
     *     description="Create a new project by converting a quotation",
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
     *             required={"quotationId"},
     *
     *             @OA\Property(property="quotationId", type="integer", example=1),
     *             @OA\Property(property="title", type="string", example="Website Redesign Project"),
     *             @OA\Property(property="description", type="string", example="Complete website redesign for client"),
     *             @OA\Property(property="deadline", type="string", format="date", example="2025-06-30")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Project created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Project created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProjectResource")
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
     *             @OA\Property(property="message", type="string", example="Validation failed for project creation"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Business logic error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Only accepted quotations can be converted to projects.")
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
    public function store(StoreProjectRequest $request, CreateProjectFromQuotationAction $action): JsonResponse
    {
        try {
            $project = $action->execute($request->validated());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }

        return ApiResponse::success(
            new ProjectResource($project->load(['client'])),
            'Project created successfully',
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/projects/{id}",
     *     operationId="getProject",
     *     tags={"Projects"},
     *     summary="Get a single project",
     *     description="Retrieve a specific project by ID with related data",
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
     *         description="Project ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Project retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Project retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProjectResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Project not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project not found")
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
    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return ApiResponse::success(
            new ProjectResource($project->load('client')),
            'Project retrieved successfully'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/projects/{id}",
     *     operationId="updateProject",
     *     tags={"Projects"},
     *     summary="Update an existing project",
     *     description="Update a project with history tracking for budget and deadline changes",
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
     *         description="Project ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="title", type="string", example="Updated Project Title"),
     *             @OA\Property(property="description", type="string", example="Updated project description"),
     *             @OA\Property(property="status", type="string", enum={"draft", "active", "ui_ux", "development", "testing", "completed", "on_hold", "cancelled"}, example="active"),
     *             @OA\Property(property="budget", type="number", example=15000.00),
     *             @OA\Property(property="deadline", type="string", format="date", example="2025-07-15"),
     *             @OA\Property(property="reason", type="string", example="Client requested additional features")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Project updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Project updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProjectResource")
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
     *             @OA\Property(property="message", type="string", example="Validation failed for project update"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Project not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project not found")
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
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $project = $this->updateProjectAction->execute($project, $request->validated());

        return ApiResponse::success(
            new ProjectResource($project->load(['client'])),
            'Project updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/projects/{id}",
     *     operationId="deleteProject",
     *     tags={"Projects"},
     *     summary="Delete a project",
     *     description="Delete a specific project by ID",
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
     *         description="Project ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Project deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Project deleted successfully"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Project not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project not found")
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
    public function destroy(Project $project, DeleteProjectAction $action): JsonResponse
    {
        $this->authorize('delete', $project);

        $action->execute($project);

        return ApiResponse::success(
            null,
            'Project deleted successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/projects/direct",
     *     operationId="createDirectProject",
     *     tags={"Projects"},
     *     summary="Create a project directly for a client",
     *     description="Create a new project directly for a client without a quotation",
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
     *             required={"clientId", "title", "budget"},
     *
     *             @OA\Property(property="clientId", type="integer", example=1),
     *             @OA\Property(property="title", type="string", example="Website Development"),
     *             @OA\Property(property="description", type="string", example="Full website development project"),
     *             @OA\Property(property="budget", type="number", example=10000.00),
     *             @OA\Property(property="deadline", type="string", format="date", example="2025-06-30"),
     *             @OA\Property(property="status", type="string", enum={"draft", "active", "ui_ux", "development", "testing", "completed", "on_hold", "cancelled"}, example="draft")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Project created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Project created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProjectResource")
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
     *             @OA\Property(property="message", type="string", example="Validation failed for project creation"),
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
    public function storeDirect(StoreDirectProjectRequest $request, CreateDirectProjectAction $action): JsonResponse
    {
        $project = $action->execute($request->validated(), $request->user());

        return ApiResponse::success(
            new ProjectResource($project->load(['client'])),
            'Project created successfully',
            201
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/projects/convert",
     *     operationId="convertQuotationToProject",
     *     tags={"Projects"},
     *     summary="Convert a quotation to a project",
     *     description="Convert an accepted quotation into a new project",
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
     *             required={"quotationId"},
     *
     *             @OA\Property(property="quotationId", type="integer", example=1),
     *             @OA\Property(property="title", type="string", example="Website Redesign Project"),
     *             @OA\Property(property="description", type="string", example="Complete website redesign for client"),
     *             @OA\Property(property="deadline", type="string", format="date", example="2025-06-30")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Quotation converted to project successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Quotation converted to project successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProjectResource")
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
     *             @OA\Property(property="message", type="string", example="Validation failed for quotation conversion"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Business logic error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Only accepted quotations can be converted to projects.")
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
    public function convert(ConvertQuotationRequest $request, CreateProjectFromQuotationAction $action): JsonResponse
    {
        try {
            $project = $action->execute($request->validated());
        } catch (InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        }

        return ApiResponse::success(
            new ProjectResource($project->load(['client'])),
            'Quotation converted to project successfully',
            201
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/projects/{id}/approve-ui",
     *     operationId="approveProjectUI",
     *     tags={"Projects"},
     *     summary="Manually approve UI/UX deliverables",
     *     description="Manually approve UI/UX deliverables for a project, cancelling the auto-approval job",
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
     *         description="Project ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="UI/UX approved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="UI/UX approved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProjectResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Invalid operation",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project is not in UI/UX phase")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Project not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project not found")
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
    public function approveUI(Project $project): JsonResponse
    {
        try {
            $project = $this->approveProjectUiAction->execute($project);
        } catch (InvalidArgumentException $exception) {
            return ApiResponse::error($exception->getMessage(), 400);
        }

        return ApiResponse::success(
            new ProjectResource($project->load(['client'])),
            'UI/UX approved successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/projects/{id}/status",
     *     operationId="updateProjectStatus",
     *     tags={"Projects"},
     *     summary="Update project status",
     *     description="Update project status with flexible transitions - allows changing to any status directly",
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
     *         description="Project ID",
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
     *             @OA\Property(property="status", type="string", enum={"draft", "active", "ui_ux", "development", "testing", "completed", "on_hold", "cancelled"}, example="completed"),
     *             @OA\Property(property="reason", type="string", example="Project completed successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Project status updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Project status updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProjectResource")
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
     *             @OA\Property(property="message", type="string", example="Validation failed for project status update"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Project not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project not found")
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
    public function updateStatus(UpdateProjectStatusRequest $request, int $project, ChangeProjectStatusAction $action): JsonResponse
    {
        $result = $action->execute($project, $request->validated(), $request->user());

        if ($result['already_current']) {
            return ApiResponse::success(
                new ProjectResource($result['project']),
                'Project status is already '.$result['status']->label()
            );
        }

        return ApiResponse::success(
            new ProjectResource($result['project']->load(['client'])),
            __('messages.project_status_updated')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/projects/pinned",
     *     operationId="getPinnedProjects",
     *     tags={"Projects"},
     *     summary="Get pinned projects for the current user",
     *     description="Retrieve all projects pinned by the authenticated user",
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
     *         description="Pinned projects retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pinned projects retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="projects",
     *                     type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/ProjectResource")
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
    public function pinned(Request $request, ListPinnedProjectsAction $action): JsonResponse
    {
        return ApiResponse::success(
            ['projects' => ProjectResource::collection($action->execute($request->user()))],
            'Pinned projects retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/projects/{id}/pin",
     *     operationId="pinProject",
     *     tags={"Projects"},
     *     summary="Pin a project",
     *     description="Pin a project for the authenticated user",
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
     *         description="Project ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Project pinned successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Project pinned successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProjectResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Project already pinned",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project is already pinned")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Project not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project not found")
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
    public function pin(Request $request, Project $project): JsonResponse
    {
        try {
            $project = $this->pinProjectAction->execute($request->user(), $project);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'Project is already pinned') {
                return ApiResponse::error($exception->getMessage(), 400);
            }
        }

        return ApiResponse::success(
            new ProjectResource($project->fresh(['client'])),
            'Project pinned successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/projects/{id}/pin",
     *     operationId="unpinProject",
     *     tags={"Projects"},
     *     summary="Unpin a project",
     *     description="Unpin a project for the authenticated user",
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
     *         description="Project ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Project unpinned successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Project unpinned successfully"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Project not pinned",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project is not pinned")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Project not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project not found")
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
    public function unpin(Request $request, Project $project): JsonResponse
    {
        try {
            $this->unpinProjectAction->execute($request->user(), $project);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'Project is not pinned') {
                return ApiResponse::error($exception->getMessage(), 400);
            }
        }

        return ApiResponse::success(
            null,
            'Project unpinned successfully'
        );
    }
}
