<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Enums\ProjectStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Client\ApproveUIRequest;
use App\Http\Resources\V1\Client\ClientProjectCollection;
use App\Http\Resources\V1\Client\ClientProjectResource;
use App\Models\Project;
use App\Services\Client\AuditLogService;
use App\Services\Projects\ProjectHistoryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ClientProjectController extends Controller
{
    public function __construct(
        private ProjectHistoryService $historyService,
        private AuditLogService $auditLogService
    ) {
        // Middleware is applied at route level: auth:sanctum, locale, role:client
    }

    /**
     * @OA\Get(
     *     path="/api/v1/client/projects",
     *     operationId="getClientProjects",
     *     tags={"Client Portal"},
     *     summary="Get client's projects with pagination",
     *     description="Retrieve a paginated list of projects for the authenticated client with limited data visibility (Black Box principle)",
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
     *         description="Client projects retrieved successfully",
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
     *                     @OA\Items(ref="#/components/schemas/ClientProjectResource")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=10),
     *                     @OA\Property(property="count", type="integer", example=5),
     *                     @OA\Property(property="perPage", type="integer", example=15),
     *                     @OA\Property(property="currentPage", type="integer", example=1),
     *                     @OA\Property(property="totalPages", type="integer", example=1)
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
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have client role",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // Log client data access
        $this->auditLogService->log('client_projects_viewed');

        // ClientScope automatically filters to only this client's projects
        $projects = QueryBuilder::for(Project::class)
            ->with(['invoices']) // Load invoices for ClientProjectResource
            ->allowedFilters([
                'status',
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where('title', 'like', "%{$value}%");
                }),
            ])
            ->defaultSort('-created_at')
            ->paginate($request->get('perPage', 15));

        return ApiResponse::success(
            new ClientProjectCollection($projects),
            'Projects retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/client/projects/{id}",
     *     operationId="getClientProject",
     *     tags={"Client Portal"},
     *     summary="Get a single client project",
     *     description="Retrieve a specific project for the authenticated client with limited data visibility (Black Box principle)",
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
     *         description="Client project retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Project retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ClientProjectResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Project not found or not accessible to client",
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
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have client role",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied")
     *         )
     *     )
     * )
     */
    public function show(Project $project): JsonResponse
    {
        // Log client data access
        $this->auditLogService->log('client_project_viewed', $project);

        // ClientScope and ProjectPolicy ensure client can only see their own projects
        return ApiResponse::success(
            new ClientProjectResource($project->load(['invoices'])),
            'Project retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/client/projects/{id}/approve-ui",
     *     operationId="approveClientProjectUI",
     *     tags={"Client Portal"},
     *     summary="Approve UI/UX deliverables for client project",
     *     description="Allow client to approve UI/UX deliverables, transitioning project from ui_ux to development phase and cancelling auto-approval job",
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
     *             @OA\Property(property="message", type="string", example="UI/UX deliverables approved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ClientProjectResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Invalid operation - project not in UI/UX phase or already approved",
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
     *         description="Project not found or not accessible to client",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Project not found")
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
     *             @OA\Property(property="message", type="string", example="Validation failed for UI approval"),
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
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have client role or cannot approve this project",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Access denied")
     *         )
     *     )
     * )
     */
    public function approveUI(ApproveUIRequest $request, Project $project): JsonResponse
    {
        // Validation is handled by ApproveUIRequest
        // ProjectPolicy ensures client can only approve their own projects

        return DB::transaction(function () use ($project) {
            $oldStatus = $project->status->value;

            // Update project - set approval timestamp, change status, cancel auto-approval job
            $project->update([
                'ui_approved_at' => Carbon::now(),
                'status' => ProjectStatus::DEVELOPMENT,
                'auto_approval_job_id' => null, // Cancel scheduled auto-approval
            ]);

            // Note: Status change history is automatically recorded by ProjectObserver
            // Only record history for UI approval timestamp
            $this->historyService->recordChange(
                $project,
                'ui_approved_at',
                null,
                $project->ui_approved_at->toISOString(),
                'Client approved UI deliverables'
            );

            // Log the approval action
            $this->auditLogService->log('client_ui_approved', $project, [
                'approved_at' => $project->ui_approved_at->toISOString(),
                'status_changed_from' => $oldStatus,
                'status_changed_to' => ProjectStatus::DEVELOPMENT->value,
            ]);

            return ApiResponse::success(
                new ClientProjectResource($project->fresh()->load(['invoices'])),
                'UI/UX deliverables approved successfully'
            );
        });
    }
}
