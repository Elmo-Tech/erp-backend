<?php

namespace App\Modules\Client\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Client\Actions\CreateClientAction;
use App\Modules\Client\Actions\DeleteClientAction;
use App\Modules\Client\Actions\UpdateClientAction;
use App\Modules\Client\Models\Client;
use App\Modules\Client\Requests\StoreClientRequest;
use App\Modules\Client\Requests\UpdateClientRequest;
use App\Modules\Client\Resources\ClientCollection;
use App\Modules\Client\Resources\ClientResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RuntimeException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Tag(
 *     name="Clients",
 *     description="Client management endpoints"
 * )
 */
class ClientController extends Controller
{
    public function __construct(
        private CreateClientAction $createClientAction,
        private UpdateClientAction $updateClientAction,
        private DeleteClientAction $deleteClientAction
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/clients",
     *     operationId="getClients",
     *     tags={"Clients"},
     *     summary="Get all clients",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="filter[search]", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="filter[status]", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer")),
     *
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
     *
     *     @OA\RequestBody(required=true),
     *
     *     @OA\Response(response=201, description="Created")
     * )
     */
    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = $this->createClientAction->execute($request->validated());

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
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
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
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true),
     *
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        $client = $this->updateClientAction->execute($client, $request->validated());

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
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function destroy(Client $client): JsonResponse
    {
        try {
            $this->deleteClientAction->execute($client);
        } catch (RuntimeException $exception) {
            return ApiResponse::error(
                $exception->getMessage(),
                400
            );
        }

        return ApiResponse::success(null, 'Client deleted successfully');
    }
}
