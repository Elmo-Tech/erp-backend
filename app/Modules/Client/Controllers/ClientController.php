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
use App\Modules\Client\Services\ClientActivityLogger;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\Meetings\Resources\MeetingResource;
use App\Modules\Project\Models\Project;
use App\Modules\Project\Resources\ProjectResource;
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
        private DeleteClientAction $deleteClientAction,
        private ClientActivityLogger $clientActivityLogger
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

        $clients = QueryBuilder::for(
            Client::query()
                ->select([
                    'id',
                    'type',
                    'display_name',
                    'legal_name',
                    'company_name',
                    'primary_email',
                    'primary_phone',
                    'position',
                    'status',
                    'country',
                    'created_at',
                ])
                ->withCount('projects')
                ->withCount([
                    'projects as active_projects_count' => fn ($query) => $query->whereNotIn('status', ['completed', 'cancelled']),
                ])
                ->withSum('projects as total_revenue', 'budget')
        )
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($searchQuery) use ($value) {
                        $searchQuery
                            ->where('display_name', 'like', "%{$value}%")
                            ->orWhere('company_name', 'like', "%{$value}%")
                            ->orWhere('legal_name', 'like', "%{$value}%")
                            ->orWhere('primary_email', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::exact('status'),
                AllowedFilter::partial('company', 'company_name'),
                AllowedFilter::partial('email', 'primary_email'),
            ])
            ->allowedSorts(['display_name', 'company_name', 'created_at'])
            ->defaultSort('-created_at')
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
        $this->clientActivityLogger->clientCreated($client, $request->user());

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
        $client->load(['accountManager', 'contacts', 'user']);

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
        abort_if(! request()->user()?->can('delete_clients'), 403, 'Unauthorized');

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

    public function projects(Request $request, Client $client): JsonResponse
    {
        $projects = Project::withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->with(['client'])
            ->orderBy('created_at', 'desc')
            ->limit((int) $request->integer('limit', 100))
            ->get();

        return ApiResponse::success([
            'projects' => ProjectResource::collection($projects),
        ], 'Client projects retrieved successfully');
    }

    public function meetings(Request $request, Client $client): JsonResponse
    {
        $projectIds = Project::withoutGlobalScopes()
            ->where('client_id', $client->id)
            ->pluck('id');

        $meetings = Meeting::query()
            ->with(['model', 'assignedTo'])
            ->where(function ($query) use ($client, $projectIds) {
                $query->where(function ($clientQuery) use ($client) {
                    $clientQuery->where('model_type', Client::class)
                        ->where('model_id', $client->id);
                });

                if ($projectIds->isNotEmpty()) {
                    $query->orWhere(function ($projectQuery) use ($projectIds) {
                        $projectQuery->where('model_type', Project::class)
                            ->whereIn('model_id', $projectIds);
                    });
                }
            })
            ->orderBy('scheduled_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit((int) $request->integer('limit', 50))
            ->get();

        return ApiResponse::success([
            'meetings' => MeetingResource::collection($meetings),
        ], 'Client meetings retrieved successfully');
    }
}
