<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Quotations\CalculatePricingRequest;
use App\Http\Requests\V1\Quotations\StoreQuotationRequest;
use App\Http\Requests\V1\Quotations\UpdateQuotationRequest;
use App\Http\Resources\V1\Quotations\QuotationCollection;
use App\Http\Resources\V1\Quotations\QuotationResource;
use App\Models\Quotation;
use App\Services\Sales\PricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class QuotationController extends Controller
{
    public function __construct(
        private PricingService $pricingService
    ) {
        // Apply middleware using Laravel 12 approach
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/quotations",
     *     operationId="getQuotations",
     *     tags={"Quotations"},
     *     summary="Get all quotations with pagination and filtering",
     *     description="Retrieve a paginated list of quotations with optional filtering by status and lead ID",
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
     *         description="Filter quotations by status",
     *         @OA\Schema(type="string", enum={"draft", "sent", "accepted", "rejected", "expired"})
     *     ),
     *     @OA\Parameter(
     *         name="filter[leadId]",
     *         in="query",
     *         description="Filter quotations by lead ID",
     *         @OA\Schema(type="integer")
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
     *         description="Quotations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Quotations retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="quotations",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="quotationId", type="integer", example=1),
     *                         @OA\Property(property="leadId", type="integer", example=1),
     *                         @OA\Property(
     *                             property="estimations",
     *                             type="object",
     *                             @OA\Property(property="frontendHours", type="number", example=40),
     *                             @OA\Property(property="backendHours", type="number", example=60),
     *                             @OA\Property(property="uiUxHours", type="number", example=20),
     *                             @OA\Property(property="testingHours", type="number", example=15),
     *                             @OA\Property(property="pmHours", type="number", example=10)
     *                         ),
     *                         @OA\Property(property="hourlyRate", type="number", example=50.00),
     *                         @OA\Property(property="discountPercentage", type="number", example=10.00),
     *                         @OA\Property(property="finalPrice", type="number", example=6525.00),
     *                         @OA\Property(property="currency", type="string", example="USD"),
     *                         @OA\Property(property="status", type="string", example="draft"),
     *                         @OA\Property(property="createdAt", type="string", format="date-time"),
     *                         @OA\Property(property="updatedAt", type="string", format="date-time"),
     *                         @OA\Property(property="createdBy", type="integer", example=1),
     *                         @OA\Property(property="updatedBy", type="integer", example=1)
     *                     )
     *                 ),
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
        $quotations = QueryBuilder::for(Quotation::class)
            ->with('lead')
            ->allowedFilters([
                'status',
                AllowedFilter::exact('lead_id')
            ])
            ->defaultSort('-created_at')
            ->paginate($request->get('perPage', 15));

        return ApiResponse::success(
            new QuotationCollection($quotations),
            'Quotations retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/quotations",
     *     operationId="createQuotation",
     *     tags={"Quotations"},
     *     summary="Create a new quotation",
     *     description="Create a new quotation with automatic pricing calculation",
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
     *             required={"leadId", "estimations", "hourlyRate", "discountPercentage", "currency"},
     *             @OA\Property(property="leadId", type="integer", example=1),
     *             @OA\Property(
     *                 property="estimations",
     *                 type="object",
     *                 @OA\Property(property="frontendHours", type="number", example=40),
     *                 @OA\Property(property="backendHours", type="number", example=60),
     *                 @OA\Property(property="uiUxHours", type="number", example=20),
     *                 @OA\Property(property="testingHours", type="number", example=15),
     *                 @OA\Property(property="pmHours", type="number", example=10)
     *             ),
     *             @OA\Property(property="hourlyRate", type="number", example=50.00),
     *             @OA\Property(property="discountPercentage", type="number", example=10.00),
     *             @OA\Property(property="currency", type="string", example="USD"),
     *             @OA\Property(property="status", type="string", enum={"draft", "sent", "accepted", "rejected", "expired"}, example="draft")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Quotation created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Quotation created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="quotationId", type="integer", example=1),
     *                 @OA\Property(property="leadId", type="integer", example=1),
     *                 @OA\Property(
     *                     property="estimations",
     *                     type="object",
     *                     @OA\Property(property="frontendHours", type="number", example=40),
     *                     @OA\Property(property="backendHours", type="number", example=60),
     *                     @OA\Property(property="uiUxHours", type="number", example=20),
     *                     @OA\Property(property="testingHours", type="number", example=15),
     *                     @OA\Property(property="pmHours", type="number", example=10)
     *                 ),
     *                 @OA\Property(property="hourlyRate", type="number", example=50.00),
     *                 @OA\Property(property="discountPercentage", type="number", example=10.00),
     *                 @OA\Property(property="finalPrice", type="number", example=6525.00),
     *                 @OA\Property(property="currency", type="string", example="USD"),
     *                 @OA\Property(property="status", type="string", example="draft"),
     *                 @OA\Property(
     *                     property="pricingBreakdown",
     *                     type="object",
     *                     @OA\Property(property="totalHours", type="number", example=145),
     *                     @OA\Property(property="grossPrice", type="number", example=7250.00),
     *                     @OA\Property(property="discountAmount", type="number", example=725.00),
     *                     @OA\Property(property="finalPrice", type="number", example=6525.00)
     *                 ),
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
     *             @OA\Property(property="message", type="string", example="Validation failed for quotation creation"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="leadId",
     *                     type="array",
     *                     @OA\Items(type="string", example="The leadId field is required.")
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
    public function store(StoreQuotationRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();
            
            // Calculate pricing using PricingService
            $pricingBreakdown = $this->pricingService->calculateQuote(
                $data['estimations'],
                $data['hourlyRate'],
                $data['discountPercentage']
            );
            
            // Convert estimations to snake_case for database storage
            $snakeCaseEstimations = $this->pricingService->convertEstimationsToSnakeCase($data['estimations']);
            
            // Create quotation with calculated final price
            $quotation = Quotation::create([
                'lead_id' => $data['leadId'],
                'estimations' => $snakeCaseEstimations,
                'hourly_rate' => $data['hourlyRate'],
                'discount_percentage' => $data['discountPercentage'],
                'final_price' => $pricingBreakdown['finalPrice'],
                'currency' => $data['currency'],
                'status' => $data['status'] ?? 'draft',
            ]);
            
            return ApiResponse::success(
                new QuotationResource($quotation->load('lead')),
                'Quotation created successfully',
                201
            );
        });
    }

    /**
     * @OA\Get(
     *     path="/api/v1/quotations/{id}",
     *     operationId="getQuotation",
     *     tags={"Quotations"},
     *     summary="Get a single quotation",
     *     description="Retrieve a specific quotation by ID with pricing breakdown",
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
     *         description="Quotation ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quotation retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Quotation retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="quotationId", type="integer", example=1),
     *                 @OA\Property(property="leadId", type="integer", example=1),
     *                 @OA\Property(
     *                     property="estimations",
     *                     type="object",
     *                     @OA\Property(property="frontendHours", type="number", example=40),
     *                     @OA\Property(property="backendHours", type="number", example=60),
     *                     @OA\Property(property="uiUxHours", type="number", example=20),
     *                     @OA\Property(property="testingHours", type="number", example=15),
     *                     @OA\Property(property="pmHours", type="number", example=10)
     *                 ),
     *                 @OA\Property(property="hourlyRate", type="number", example=50.00),
     *                 @OA\Property(property="discountPercentage", type="number", example=10.00),
     *                 @OA\Property(property="finalPrice", type="number", example=6525.00),
     *                 @OA\Property(property="currency", type="string", example="USD"),
     *                 @OA\Property(property="status", type="string", example="draft"),
     *                 @OA\Property(
     *                     property="pricingBreakdown",
     *                     type="object",
     *                     @OA\Property(property="totalHours", type="number", example=145),
     *                     @OA\Property(property="grossPrice", type="number", example=7250.00),
     *                     @OA\Property(property="discountAmount", type="number", example=725.00),
     *                     @OA\Property(property="finalPrice", type="number", example=6525.00)
     *                 ),
     *                 @OA\Property(
     *                     property="lead",
     *                     type="object",
     *                     @OA\Property(property="leadId", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@example.com"),
     *                     @OA\Property(property="company", type="string", example="Acme Corp")
     *                 ),
     *                 @OA\Property(property="createdAt", type="string", format="date-time"),
     *                 @OA\Property(property="updatedAt", type="string", format="date-time"),
     *                 @OA\Property(property="createdBy", type="integer", example=1),
     *                 @OA\Property(property="updatedBy", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Quotation not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Quotation not found")
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
    public function show(Quotation $quotation): JsonResponse
    {
        return ApiResponse::success(
            new QuotationResource($quotation->load('lead')),
            'Quotation retrieved successfully'
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/quotations/{id}",
     *     operationId="updateQuotation",
     *     tags={"Quotations"},
     *     summary="Update an existing quotation",
     *     description="Update a quotation with automatic pricing recalculation",
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
     *         description="Quotation ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="leadId", type="integer", example=1),
     *             @OA\Property(
     *                 property="estimations",
     *                 type="object",
     *                 @OA\Property(property="frontendHours", type="number", example=45),
     *                 @OA\Property(property="backendHours", type="number", example=65),
     *                 @OA\Property(property="uiUxHours", type="number", example=25),
     *                 @OA\Property(property="testingHours", type="number", example=20),
     *                 @OA\Property(property="pmHours", type="number", example=15)
     *             ),
     *             @OA\Property(property="hourlyRate", type="number", example=55.00),
     *             @OA\Property(property="discountPercentage", type="number", example=15.00),
     *             @OA\Property(property="currency", type="string", example="USD"),
     *             @OA\Property(property="status", type="string", enum={"draft", "sent", "accepted", "rejected", "expired"}, example="sent")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quotation updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Quotation updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="quotationId", type="integer", example=1),
     *                 @OA\Property(property="leadId", type="integer", example=1),
     *                 @OA\Property(
     *                     property="estimations",
     *                     type="object",
     *                     @OA\Property(property="frontendHours", type="number", example=45),
     *                     @OA\Property(property="backendHours", type="number", example=65),
     *                     @OA\Property(property="uiUxHours", type="number", example=25),
     *                     @OA\Property(property="testingHours", type="number", example=20),
     *                     @OA\Property(property="pmHours", type="number", example=15)
     *                 ),
     *                 @OA\Property(property="hourlyRate", type="number", example=55.00),
     *                 @OA\Property(property="discountPercentage", type="number", example=15.00),
     *                 @OA\Property(property="finalPrice", type="number", example=7942.50),
     *                 @OA\Property(property="currency", type="string", example="USD"),
     *                 @OA\Property(property="status", type="string", example="sent"),
     *                 @OA\Property(
     *                     property="pricingBreakdown",
     *                     type="object",
     *                     @OA\Property(property="totalHours", type="number", example=170),
     *                     @OA\Property(property="grossPrice", type="number", example=9350.00),
     *                     @OA\Property(property="discountAmount", type="number", example=1402.50),
     *                     @OA\Property(property="finalPrice", type="number", example=7947.50)
     *                 ),
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
     *             @OA\Property(property="message", type="string", example="Validation failed for quotation update"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="hourlyRate",
     *                     type="array",
     *                     @OA\Items(type="string", example="The hourlyRate must be greater than 0.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Quotation not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Quotation not found")
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
    public function update(UpdateQuotationRequest $request, Quotation $quotation): JsonResponse
    {
        return DB::transaction(function () use ($request, $quotation) {
            $data = $request->validated();
            
            // Prepare update data
            $updateData = [];
            
            if (isset($data['leadId'])) {
                $updateData['lead_id'] = $data['leadId'];
            }
            
            if (isset($data['currency'])) {
                $updateData['currency'] = $data['currency'];
            }
            
            if (isset($data['status'])) {
                $updateData['status'] = $data['status'];
            }
            
            // Handle pricing-related updates
            $needsPricingRecalculation = false;
            $estimations = $quotation->estimations;
            $hourlyRate = $quotation->hourly_rate;
            $discountPercentage = $quotation->discount_percentage;
            
            if (isset($data['estimations'])) {
                $estimations = $this->pricingService->convertEstimationsToSnakeCase($data['estimations']);
                $updateData['estimations'] = $estimations;
                $needsPricingRecalculation = true;
            }
            
            if (isset($data['hourlyRate'])) {
                $hourlyRate = $data['hourlyRate'];
                $updateData['hourly_rate'] = $hourlyRate;
                $needsPricingRecalculation = true;
            }
            
            if (isset($data['discountPercentage'])) {
                $discountPercentage = $data['discountPercentage'];
                $updateData['discount_percentage'] = $discountPercentage;
                $needsPricingRecalculation = true;
            }
            
            // Recalculate pricing if needed
            if ($needsPricingRecalculation) {
                $camelCaseEstimations = $this->pricingService->convertEstimationsToCamelCase($estimations);
                $pricingBreakdown = $this->pricingService->calculateQuote(
                    $camelCaseEstimations,
                    (float) $hourlyRate,
                    (float) $discountPercentage
                );
                $updateData['final_price'] = $pricingBreakdown['finalPrice'];
            }
            
            // Update quotation
            $quotation->update($updateData);
            
            return ApiResponse::success(
                new QuotationResource($quotation->fresh()->load('lead')),
                'Quotation updated successfully'
            );
        });
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/quotations/{id}",
     *     operationId="deleteQuotation",
     *     tags={"Quotations"},
     *     summary="Delete a quotation",
     *     description="Delete a specific quotation by ID",
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
     *         description="Quotation ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quotation deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Quotation deleted successfully"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Quotation not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Quotation not found")
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
    public function destroy(Quotation $quotation): JsonResponse
    {
        $quotation->delete();

        return ApiResponse::success(
            null,
            'Quotation deleted successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/quotations/calculate",
     *     operationId="calculateQuotationPricing",
     *     tags={"Quotations"},
     *     summary="Calculate quotation pricing preview",
     *     description="Calculate pricing breakdown without saving the quotation",
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
     *             required={"estimations", "hourlyRate", "discountPercentage"},
     *             @OA\Property(
     *                 property="estimations",
     *                 type="object",
     *                 @OA\Property(property="frontendHours", type="number", example=40),
     *                 @OA\Property(property="backendHours", type="number", example=60),
     *                 @OA\Property(property="uiUxHours", type="number", example=20),
     *                 @OA\Property(property="testingHours", type="number", example=15),
     *                 @OA\Property(property="pmHours", type="number", example=10)
     *             ),
     *             @OA\Property(property="hourlyRate", type="number", example=50.00),
     *             @OA\Property(property="discountPercentage", type="number", example=10.00)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pricing calculated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pricing calculated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="totalHours", type="number", example=145),
     *                 @OA\Property(property="grossPrice", type="number", example=7250.00),
     *                 @OA\Property(property="discountAmount", type="number", example=725.00),
     *                 @OA\Property(property="finalPrice", type="number", example=6525.00)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed for pricing calculation"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="hourlyRate",
     *                     type="array",
     *                     @OA\Items(type="string", example="The hourlyRate must be greater than 0.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Calculation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Pricing calculation failed")
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
    public function calculate(CalculatePricingRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            
            $pricingBreakdown = $this->pricingService->calculateQuote(
                $data['estimations'],
                $data['hourlyRate'],
                $data['discountPercentage']
            );
            
            return ApiResponse::success(
                $pricingBreakdown,
                'Pricing calculated successfully'
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error(
                'Pricing calculation failed: ' . $e->getMessage(),
                400
            );
        }
    }
}