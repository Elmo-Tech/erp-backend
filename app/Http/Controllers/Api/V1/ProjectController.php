<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Projects\ConvertQuotationRequest;
use App\Http\Requests\V1\Projects\StoreDirectProjectRequest;
use App\Http\Requests\V1\Projects\StoreProjectRequest;
use App\Http\Requests\V1\Projects\UpdateProjectRequest;
use App\Http\Requests\V1\Projects\UpdateProjectStatusRequest;
use App\Http\Resources\V1\Projects\ProjectCollection;
use App\Http\Resources\V1\Projects\ProjectResource;
use App\Models\Project;
use App\Services\Projects\ProjectConversionService;
use App\Services\Projects\ProjectHistoryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OpenApi\Annotations as OA;
use RuntimeException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProjectController extends Controller
{
    public function __construct(
        private ProjectConversionService $conversionService,
        private ProjectHistoryService $historyService
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
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="filter[status]",
     *         in="query",
     *         description="Filter projects by status",
     *         @OA\Schema(type="string", enum={"draft", "active", "ui_ux", "development", "testing", "completed", "on_hold", "cancelled"})
     *     ),
     *     @OA\Parameter(
     *         name="filter[clientId]",
     *         in="query",
     *         description="Filter projects by client ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Search projects by title",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", minimum=1, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, example=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Projects retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Projects retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="projects",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/ProjectResource")
     *                 ),
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $projects = QueryBuilder::for(Project::class)
            ->with(['client', 'quotation'])
            ->leftJoin('pinned_projects', function ($join) use ($user) {
                $join->on('projects.id', '=', 'pinned_projects.project_id')
                    ->where('pinned_projects.user_id', '=', $user?->id);
            })
            ->select('projects.*', 'pinned_projects.id as is_pinned')
            ->allowedFilters([
                'status',
                AllowedFilter::exact('client_id', 'client_id')->ignore(null),
                AllowedFilter::callback('clientId', function ($query, $value) {
                    $query->where('client_id', $value);
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where('title', 'like', "%{$value}%");
                }),
            ])
            ->orderByRaw('CASE WHEN pinned_projects.id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('projects.created_at', 'desc')
            ->paginate($request->get('perPage', 15));

        return ApiResponse::success(
            new ProjectCollection($projects),
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
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"quotationId"},
     *             @OA\Property(property="quotationId", type="integer", example=1),
     *             @OA\Property(property="title", type="string", example="Website Redesign Project"),
     *             @OA\Property(property="description", type="string", example="Complete website redesign for client"),
     *             @OA\Property(property="deadline", type="string", format="date", example="2025-06-30")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Project created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Project created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProjectResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed for project creation"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Business logic error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Only accepted quotations can be converted to projects.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();

            try {
                $project = $this->conversionService->convertQuotation(
                    $data['quotationId'],
                    [
                        'title' => $data['title'] ?? null,
                        'description' => $data['description'] ?? null,
                        'deadline' => $data['deadline'] ?? null,
                    ]
                );

                return ApiResponse::success(
                    new ProjectResource($project->load(['client', 'quotation'])),
                    'Project created successfully',
                    201
                );
            } catch (InvalidArgumentException $e) {
                return ApiResponse::error($e->getMessage(), 400);
            } catch (RuntimeException $e) {
                return ApiResponse::error($e->getMessage(), 400);
            }
        });
    }

    /**
     * @OA\Get(
     *     path="/api/v1/projects/{id}",
     *     operationId="getProject",
     *     tags={"Projects"},
     *     summary="Get a single project",
     *     description="Retrieve a specific project by ID with related data",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Project ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Project retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Project retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProjectResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Project not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function show(Project $project): JsonResponse
    {
        return ApiResponse::success(
            new ProjectResource($project->load(['client', 'quotation', 'members.user', 'files'])),
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
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Project ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Updated Project Title"),
     *             @OA\Property(property="description", type="string", example="Updated project description"),
     *             @OA\Property(property="status", type="string", enum={"draft", "active", "ui_ux", "development", "testing", "completed", "on_hold", "cancelled"}, example="active"),
     *             @OA\Property(property="budget", type="number", example=15000.00),
     *             @OA\Property(property="deadline", type="string", format="date", example="2025-07-15"),
     *             @OA\Property(property="reason", type="string", example="Client requested additional features")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Project updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Project updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProjectResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed for project update"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Project not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        return DB::transaction(function () use ($request, $project) {
            $data = $request->validated();
            $reason = $data['reason'] ?? null;
            $updateData = [];

            // Track changes for history
            if (isset($data['title'])) {
                $updateData['title'] = $data['title'];
            }

            if (isset($data['description'])) {
                $updateData['description'] = $data['description'];
            }

            // Handle budget change with history tracking
            if (isset($data['budget']) && (float) $data['budget'] !== (float) $project->budget) {
                $oldBudget = $project->budget;
                $updateData['budget'] = $data['budget'];

                $this->historyService->recordChange(
                    $project,
                    'budget',
                    $oldBudget,
                    $data['budget'],
                    $reason
                );
            }

            // Handle deadline change with history tracking
            if (isset($data['deadline'])) {
                $currentDeadline = $project->deadline?->format('Y-m-d');
                $newDeadline = $data['deadline'];

                if ($currentDeadline !== $newDeadline) {
                    $updateData['deadline'] = $newDeadline;

                    $this->historyService->recordChange(
                        $project,
                        'deadline',
                        $currentDeadline,
                        $newDeadline,
                        $reason
                    );
                }
            }

            // Handle status change (history is recorded by observer)
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }

            // Update project
            if (!empty($updateData)) {
                $project->update($updateData);
            }

            return ApiResponse::success(
                new ProjectResource($project->fresh()->load(['client', 'quotation'])),
                'Project updated successfully'
            );
        });
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/projects/{id}",
     *     operationId="deleteProject",
     *     tags={"Projects"},
     *     summary="Delete a project",
     *     description="Delete a specific project by ID",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Project ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Project deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Project deleted successfully"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Project not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function destroy(Project $project): JsonResponse
    {
        $project->delete();

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
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"clientId", "title", "budget"},
     *             @OA\Property(property="clientId", type="integer", example=1),
     *             @OA\Property(property="title", type="string", example="Website Development"),
     *             @OA\Property(property="description", type="string", example="Full website development project"),
     *             @OA\Property(property="budget", type="number", example=10000.00),
     *             @OA\Property(property="deadline", type="string", format="date", example="2025-06-30"),
     *             @OA\Property(property="status", type="string", enum={"draft", "active", "ui_ux", "development", "testing", "completed", "on_hold", "cancelled"}, example="draft")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Project created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Project created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProjectResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed for project creation"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function storeDirect(StoreDirectProjectRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();

            $project = Project::create([
                'client_id' => $data['clientId'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'budget' => $data['budget'],
                'deadline' => $data['deadline'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'quotation_id' => null,
            ]);

            return ApiResponse::success(
                new ProjectResource($project->load(['client'])),
                'Project created successfully',
                201
            );
        });
    }

    /**
     * @OA\Post(
     *     path="/api/v1/projects/convert",
     *     operationId="convertQuotationToProject",
     *     tags={"Projects"},
     *     summary="Convert a quotation to a project",
     *     description="Convert an accepted quotation into a new project",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"quotationId"},
     *             @OA\Property(property="quotationId", type="integer", example=1),
     *             @OA\Property(property="title", type="string", example="Website Redesign Project"),
     *             @OA\Property(property="description", type="string", example="Complete website redesign for client"),
     *             @OA\Property(property="deadline", type="string", format="date", example="2025-06-30")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Quotation converted to project successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Quotation converted to project successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProjectResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed for quotation conversion"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Business logic error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Only accepted quotations can be converted to projects.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function convert(ConvertQuotationRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();

            try {
                $project = $this->conversionService->convertQuotation(
                    $data['quotationId'],
                    [
                        'title' => $data['title'] ?? null,
                        'description' => $data['description'] ?? null,
                        'deadline' => $data['deadline'] ?? null,
                    ]
                );

                return ApiResponse::success(
                    new ProjectResource($project->load(['client', 'quotation'])),
                    'Quotation converted to project successfully',
                    201
                );
            } catch (InvalidArgumentException $e) {
                return ApiResponse::error($e->getMessage(), 400);
            } catch (RuntimeException $e) {
                return ApiResponse::error($e->getMessage(), 400);
            }
        });
    }

    /**
     * @OA\Post(
     *     path="/api/v1/projects/{id}/approve-ui",
     *     operationId="approveProjectUI",
     *     tags={"Projects"},
     *     summary="Manually approve UI/UX deliverables",
     *     description="Manually approve UI/UX deliverables for a project, cancelling the auto-approval job",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Project ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="UI/UX approved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="UI/UX approved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProjectResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project is not in UI/UX phase")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Project not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function approveUI(Project $project): JsonResponse
    {
        // Validate project is in UI/UX phase
        if ($project->status !== ProjectStatus::UI_UX) {
            return ApiResponse::error('Project is not in UI/UX phase', 400);
        }

        // Check if already approved
        if ($project->ui_approved_at !== null) {
            return ApiResponse::error('UI/UX has already been approved', 400);
        }

        return DB::transaction(function () use ($project) {
            $oldStatus = $project->status->value;

            // Update project
            $project->update([
                'ui_approved_at' => Carbon::now(),
                'status' => ProjectStatus::DEVELOPMENT,
                'auto_approval_job_id' => null,
            ]);

            // Record history
            $this->historyService->recordChange(
                $project,
                'status',
                $oldStatus,
                ProjectStatus::DEVELOPMENT->value,
                'Manual UI/UX approval by client'
            );

            $this->historyService->recordChange(
                $project,
                'ui_approved_at',
                null,
                $project->ui_approved_at->toISOString(),
                'Manual UI/UX approval by client'
            );

            return ApiResponse::success(
                new ProjectResource($project->fresh()->load(['client', 'quotation'])),
                'UI/UX approved successfully'
            );
        });
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/projects/{id}/status",
     *     operationId="updateProjectStatus",
     *     tags={"Projects"},
     *     summary="Update project status",
     *     description="Update project status with flexible transitions - allows changing to any status directly",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Project ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"draft", "active", "ui_ux", "development", "testing", "completed", "on_hold", "cancelled"}, example="completed"),
     *             @OA\Property(property="reason", type="string", example="Project completed successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Project status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Project status updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProjectResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed for project status update"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Project not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function updateStatus(UpdateProjectStatusRequest $request, int $project): JsonResponse
    {
        $projectModel = Project::withoutGlobalScopes()
            ->with(['client', 'quotation'])
            ->find($project);

        if (!$projectModel) {
            return ApiResponse::notFound('Project not found');
        }

        if (!$request->user()?->can('update', $projectModel)) {
            return ApiResponse::forbidden('You do not have permission to update this project');
        }

        return DB::transaction(function () use ($request, $projectModel) {
            $data = $request->validated();
            $oldStatus = $projectModel->status;
            $newStatus = ProjectStatus::from($data['status']);
            $reason = $data['reason'] ?? null;

            // Check if status is actually changing
            if ($oldStatus === $newStatus) {
                return ApiResponse::success(
                    new ProjectResource($projectModel),
                    'Project status is already ' . $newStatus->label()
                );
            }

            // Update project status
            $projectModel->update([
                'status' => $newStatus,
                'previous_status' => $oldStatus,
            ]);

            // Record history
            $this->historyService->recordChange(
                $projectModel,
                'status',
                $oldStatus->value,
                $newStatus->value,
                $reason ?? 'Status updated via API'
            );

            return ApiResponse::success(
                new ProjectResource($projectModel->fresh()->load(['client', 'quotation'])),
                __('messages.project_status_updated')
            );
        });
    }

    /**
     * @OA\Get(
     *     path="/api/v1/projects/pinned",
     *     operationId="getPinnedProjects",
     *     tags={"Projects"},
     *     summary="Get pinned projects for the current user",
     *     description="Retrieve all projects pinned by the authenticated user",
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
     *         description="Pinned projects retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pinned projects retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="projects",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/ProjectResource")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function pinned(Request $request): JsonResponse
    {
        $user = $request->user();
        $pinnedProjects = $user->pinnedProjects()
            ->with(['client', 'quotation'])
            ->orderByPivot('created_at', 'desc')
            ->get();

        return ApiResponse::success(
            ['projects' => ProjectResource::collection($pinnedProjects)],
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
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Project ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Project pinned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Project pinned successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProjectResource")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Project already pinned",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project is already pinned")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Project not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function pin(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();

        // Check if already pinned
        if ($project->isPinnedBy($user->id)) {
            return ApiResponse::error('Project is already pinned', 400);
        }

        // Pin the project
        $user->pinnedProjects()->attach($project->id);

        return ApiResponse::success(
            new ProjectResource($project->load(['client', 'quotation'])),
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
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         description="Language preference",
     *         @OA\Schema(type="string", enum={"en", "ar"}, example="en")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Project ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Project unpinned successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Project unpinned successfully"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Project not pinned",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project is not pinned")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Project not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     )
     * )
     */
    public function unpin(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();

        // Check if not pinned
        if (!$project->isPinnedBy($user->id)) {
            return ApiResponse::error('Project is not pinned', 400);
        }

        // Unpin the project
        $user->pinnedProjects()->detach($project->id);

        return ApiResponse::success(
            null,
            'Project unpinned successfully'
        );
    }
}
