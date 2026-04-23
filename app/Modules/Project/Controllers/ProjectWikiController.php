<?php

namespace App\Modules\Project\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Project\Requests\StoreProjectWikiRequest;
use App\Modules\Project\Requests\UpdateProjectWikiRequest;
use App\Modules\Project\Resources\ProjectWikiCollection;
use App\Modules\Project\Resources\ProjectWikiResource;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Models\ProjectWiki;
use App\Modules\Project\Actions\ProjectWikiAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

class ProjectWikiController extends Controller
{
    public function __construct(
        private ProjectWikiAction $wikiService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/projects/{projectId}/wikis",
     *     operationId="getProjectWikis",
     *     tags={"Project Wikis"},
     *     summary="Get all wikis for a project",
     *     description="Retrieve a paginated list of wiki entries for a specific project",
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
     *         description="Wikis retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Wikis retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="wikis",
     *                     type="array",
     *
     *                     @OA\Items(ref="#/components/schemas/ProjectWikiResource")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer", example=10),
     *                     @OA\Property(property="count", type="integer", example=10),
     *                     @OA\Property(property="perPage", type="integer", example=15),
     *                     @OA\Property(property="currentPage", type="integer", example=1),
     *                     @OA\Property(property="totalPages", type="integer", example=1)
     *                 )
     *             )
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
    public function index(Request $request, Project $project): JsonResponse
    {
        $wikis = $project->wikis()
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('perPage', 15));

        return ApiResponse::success(
            new ProjectWikiCollection($wikis),
            'Wikis retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/projects/{projectId}/wikis",
     *     operationId="createProjectWiki",
     *     tags={"Project Wikis"},
     *     summary="Create a new wiki entry",
     *     description="Create a new wiki entry for a project with optional encryption",
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
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"title", "content"},
     *
     *             @OA\Property(property="title", type="string", example="Database Credentials"),
     *             @OA\Property(property="content", type="string", example="# Database\n\nHost: localhost\nUser: admin\nPassword: secret123"),
     *             @OA\Property(property="isEncrypted", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Wiki created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Wiki created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProjectWikiResource")
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
     *             @OA\Property(property="message", type="string", example="Validation failed for wiki creation"),
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
    public function store(StoreProjectWikiRequest $request, Project $project): JsonResponse
    {
        return DB::transaction(function () use ($request, $project) {
            $data = $request->validated();

            $wiki = $this->wikiService->createWiki($project, $data);

            return ApiResponse::success(
                new ProjectWikiResource($wiki),
                'Wiki created successfully',
                201
            );
        });
    }

    /**
     * @OA\Get(
     *     path="/api/v1/projects/{projectId}/wikis/{id}",
     *     operationId="getProjectWiki",
     *     tags={"Project Wikis"},
     *     summary="Get a single wiki entry",
     *     description="Retrieve a specific wiki entry with decrypted content",
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
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Wiki ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Wiki retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Wiki retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProjectWikiResource")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Wiki not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Wiki not found")
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
    public function show(Project $project, ProjectWiki $wiki): JsonResponse
    {
        // Ensure wiki belongs to the project
        if ($wiki->project_id !== $project->id) {
            return ApiResponse::notFound('Wiki not found');
        }

        return ApiResponse::success(
            new ProjectWikiResource($wiki),
            'Wiki retrieved successfully'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/projects/{projectId}/wikis/{id}",
     *     operationId="updateProjectWiki",
     *     tags={"Project Wikis"},
     *     summary="Update a wiki entry",
     *     description="Update an existing wiki entry with optional encryption changes",
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
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Wiki ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="title", type="string", example="Updated Database Credentials"),
     *             @OA\Property(property="content", type="string", example="# Database\n\nHost: production.db.com\nUser: admin\nPassword: newSecret456"),
     *             @OA\Property(property="isEncrypted", type="boolean", example=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Wiki updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Wiki updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProjectWikiResource")
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
     *             @OA\Property(property="message", type="string", example="Validation failed for wiki update"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Wiki not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Wiki not found")
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
    public function update(UpdateProjectWikiRequest $request, Project $project, ProjectWiki $wiki): JsonResponse
    {
        // Ensure wiki belongs to the project
        if ($wiki->project_id !== $project->id) {
            return ApiResponse::notFound('Wiki not found');
        }

        return DB::transaction(function () use ($request, $wiki) {
            $data = $request->validated();

            $updatedWiki = $this->wikiService->updateWiki($wiki, $data);

            return ApiResponse::success(
                new ProjectWikiResource($updatedWiki),
                'Wiki updated successfully'
            );
        });
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/projects/{projectId}/wikis/{id}",
     *     operationId="deleteProjectWiki",
     *     tags={"Project Wikis"},
     *     summary="Delete a wiki entry",
     *     description="Delete a specific wiki entry",
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
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Wiki ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Wiki deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Wiki deleted successfully"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Wiki not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Wiki not found")
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
    public function destroy(Project $project, ProjectWiki $wiki): JsonResponse
    {
        // Ensure wiki belongs to the project
        if ($wiki->project_id !== $project->id) {
            return ApiResponse::notFound('Wiki not found');
        }

        $this->wikiService->deleteWiki($wiki);

        return ApiResponse::success(
            null,
            'Wiki deleted successfully'
        );
    }
}
