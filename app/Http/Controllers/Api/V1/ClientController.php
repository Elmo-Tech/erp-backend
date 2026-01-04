<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Client\StoreClientRequest;
use App\Http\Requests\V1\Client\UpdateClientRequest;
use App\Http\Resources\V1\Client\ClientResource;
use App\Http\Resources\V1\Client\ClientCollection;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Clients",
 *     description="Client management endpoints"
 * )
 */
class ClientController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clients",
     *     operationId="getClients",
     *     tags={"Clients"},
     *     summary="Get all clients",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="filter[search]", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="filter[status]", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('perPage', 15);

        $clients = QueryBuilder::for(Client::class)
            ->allowedFilters([
                AllowedFilter::partial('search', 'name'),
                AllowedFilter::exact('status'),
                AllowedFilter::partial('company'),
                AllowedFilter::partial('email'),
            ])
            ->allowedSorts(['name', 'company', 'created_at'])
            ->defaultSort('-created_at')
            ->with(['user'])
            ->withCount('projects')
            ->paginate($perPage);

        return ApiResponse::success(
            new ClientCollection($clients),
            'Clients retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/clients",
     *     operationId="createClient",
     *     tags={"Clients"},
     *     summary="Create a new client",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(required=true),
     *     @OA\Response(response=201, description="Created")
     * )
     */
    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create($request->validated());

        return ApiResponse::success(
            new ClientResource($client),
            'Client created successfully',
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clients/{id}",
     *     operationId="getClient",
     *     tags={"Clients"},
     *     summary="Get a single client",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function show(Client $client): JsonResponse
    {
        $client->load(['user', 'projects']);

        return ApiResponse::success(
            new ClientResource($client),
            'Client retrieved successfully'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/clients/{id}",
     *     operationId="updateClient",
     *     tags={"Clients"},
     *     summary="Update a client",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true),
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        $client->update($request->validated());

        return ApiResponse::success(
            new ClientResource($client),
            'Client updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/clients/{id}",
     *     operationId="deleteClient",
     *     tags={"Clients"},
     *     summary="Delete a client",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function destroy(Client $client): JsonResponse
    {
        // Check if client has projects
        if ($client->projects()->exists()) {
            return ApiResponse::error(
                'Cannot delete client with existing projects',
                400
            );
        }

        $client->delete();

        return ApiResponse::success(null, 'Client deleted successfully');
    }
}
