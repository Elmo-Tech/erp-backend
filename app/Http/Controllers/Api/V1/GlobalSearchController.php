<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Sales\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Global Search",
 *     description="Search across all entities"
 * )
 */
class GlobalSearchController extends Controller
{
    public function __construct(
        private GlobalSearchService $searchService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/search",
     *     operationId="globalSearch",
     *     tags={"Global Search"},
     *     summary="Search across all entities",
     *     description="Search leads, proposals, quotations, clients, and projects",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="q", in="query", required=true, description="Search query", @OA\Schema(type="string", minLength=2)),
     *     @OA\Parameter(name="types[]", in="query", description="Entity types to search", @OA\Schema(type="array", @OA\Items(type="string", enum={"leads", "proposals", "quotations", "clients", "projects"}))),
     *     @OA\Parameter(name="limit", in="query", description="Results limit per type", @OA\Schema(type="integer", default=10)),
     *     @OA\Response(response=200, description="Search results retrieved successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
            'types' => 'nullable|array',
            'types.*' => 'string|in:leads,proposals,quotations,clients,projects',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $query = $request->input('q');
        $types = $request->input('types', []);
        $limit = $request->input('limit', 10);

        $results = $this->searchService->search($query, $types, $limit);

        return ApiResponse::success($results, __('search.results_retrieved'));
    }
}
