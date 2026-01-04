<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Leads\StoreLeadRequest;
use App\Http\Requests\V1\Leads\UpdateLeadRequest;
use App\Http\Resources\V1\Leads\LeadCollection;
use App\Http\Resources\V1\Leads\LeadResource;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class LeadController extends Controller
{
    public function __construct()
    {
        // Apply middleware using Laravel 12 approach
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/leads",
     *     operationId="getLeads",
     *     tags={"Leads"},
     *     summary="Get all leads with pagination and filtering",
     *     description="Retrieve a paginated list of leads with optional filtering by status and search",
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
     *         description="Filter leads by status",
     *         @OA\Schema(type="string", enum={"new", "contacted", "qualified", "proposal", "negotiation", "won", "lost"})
     *     ),
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Search leads by name, email, or company",
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
     *         description="Leads retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Leads retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="leads",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="leadId", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe"),
     *                         @OA\Property(property="email", type="string", example="john@example.com"),
     *                         @OA\Property(property="company", type="string", example="Acme Corp"),
     *                         @OA\Property(property="source", type="string", example="Website"),
     *                         @OA\Property(property="status", type="string", example="new"),
     *                         @OA\Property(property="createdAt", type="string", format="date-time"),
     *                         @OA\Property(property="updatedAt", type="string", format="date-time"),
     *                         @OA\Property(property="createdBy", type="integer", example=1),
     *                         @OA\Property(property="updatedBy", type="integer", example=1)
     *                     )
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
        $leads = QueryBuilder::for(Lead::class)
            ->allowedFilters([
                'status',
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                          ->orWhere('email', 'like', "%{$value}%")
                          ->orWhere('company', 'like', "%{$value}%");
                    });
                })
            ])
            ->defaultSort('-created_at')
            ->paginate($request->get('perPage', 15));

        return ApiResponse::success(
            new LeadCollection($leads),
            'Leads retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/leads",
     *     operationId="createLead",
     *     tags={"Leads"},
     *     summary="Create a new lead",
     *     description="Create a new lead with the provided information",
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
     *             required={"name", "email"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="company", type="string", example="Acme Corp"),
     *             @OA\Property(property="source", type="string", example="Website"),
     *             @OA\Property(property="status", type="string", enum={"new", "contacted", "qualified", "proposal", "negotiation", "won", "lost"}, example="new")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Lead created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Lead created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="leadId", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="company", type="string", example="Acme Corp"),
     *                 @OA\Property(property="source", type="string", example="Website"),
     *                 @OA\Property(property="status", type="string", example="new"),
     *                 @OA\Property(property="createdAt", type="string", format="date-time"),
     *                 @OA\Property(property="updatedAt", type="string", format="date-time"),
     *                 @OA\Property(property="createdBy", type="integer", example=1),
     *                 @OA\Property(property="updatedBy", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed for lead creation"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="name",
     *                     type="array",
     *                     @OA\Items(type="string", example="The name field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="email",
     *                     type="array",
     *                     @OA\Items(type="string", example="The email field is required.")
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
    public function store(StoreLeadRequest $request): JsonResponse
    {
        $lead = Lead::create($request->validated());

        return ApiResponse::success(
            new LeadResource($lead),
            'Lead created successfully',
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/leads/{id}",
     *     operationId="getLead",
     *     tags={"Leads"},
     *     summary="Get a single lead",
     *     description="Retrieve a specific lead by ID",
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
     *         description="Lead ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lead retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Lead retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="leadId", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="company", type="string", example="Acme Corp"),
     *                 @OA\Property(property="source", type="string", example="Website"),
     *                 @OA\Property(property="status", type="string", example="new"),
     *                 @OA\Property(property="createdAt", type="string", format="date-time"),
     *                 @OA\Property(property="updatedAt", type="string", format="date-time"),
     *                 @OA\Property(property="createdBy", type="integer", example=1),
     *                 @OA\Property(property="updatedBy", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Lead not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Lead not found")
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
    public function show(Lead $lead): JsonResponse
    {
        return ApiResponse::success(
            new LeadResource($lead),
            'Lead retrieved successfully'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/leads/{id}",
     *     operationId="updateLead",
     *     tags={"Leads"},
     *     summary="Update an existing lead",
     *     description="Update a lead with the provided information",
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
     *         description="Lead ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="company", type="string", example="Acme Corp"),
     *             @OA\Property(property="source", type="string", example="Website"),
     *             @OA\Property(property="status", type="string", enum={"new", "contacted", "qualified", "proposal", "negotiation", "won", "lost"}, example="contacted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lead updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Lead updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="leadId", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", example="john@example.com"),
     *                 @OA\Property(property="company", type="string", example="Acme Corp"),
     *                 @OA\Property(property="source", type="string", example="Website"),
     *                 @OA\Property(property="status", type="string", example="contacted"),
     *                 @OA\Property(property="createdAt", type="string", format="date-time"),
     *                 @OA\Property(property="updatedAt", type="string", format="date-time"),
     *                 @OA\Property(property="createdBy", type="integer", example=1),
     *                 @OA\Property(property="updatedBy", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed for lead update"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="email",
     *                     type="array",
     *                     @OA\Items(type="string", example="The email must be a valid email address.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Lead not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Lead not found")
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
    public function update(UpdateLeadRequest $request, Lead $lead): JsonResponse
    {
        $lead->update($request->validated());

        return ApiResponse::success(
            new LeadResource($lead->fresh()),
            'Lead updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/leads/{id}",
     *     operationId="deleteLead",
     *     tags={"Leads"},
     *     summary="Delete a lead",
     *     description="Delete a specific lead by ID",
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
     *         description="Lead ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lead deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Lead deleted successfully"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Lead not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Lead not found")
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
    public function destroy(Lead $lead): JsonResponse
    {
        $lead->delete();

        return ApiResponse::success(
            null,
            'Lead deleted successfully'
        );
    }
}