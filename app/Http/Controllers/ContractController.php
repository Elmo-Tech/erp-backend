<?php

namespace App\Http\Controllers;

use App\Enums\ContractStatus;
use App\Helpers\ApiResponse;
use App\Http\Requests\StoreContractRequest;
use App\Http\Requests\UpdateContractRequest;
use App\Http\Resources\V1\Contract\ContractCollection;
use App\Http\Resources\V1\Contract\ContractResource;
use App\Models\Contract;
use App\Models\Quotation;
use App\Services\Sales\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @OA\Tag(
 *     name="Contracts",
 *     description="Contract management endpoints"
 * )
 */
class ContractController extends Controller
{
    public function __construct(
        private ContractService $contractService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/contracts",
     *     summary="Get contracts list",
     *     tags={"Contracts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *     @OA\Parameter(
     *         name="filter[search]",
     *         in="query",
     *         description="Search in contract content and quotation details",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filter[status]",
     *         in="query",
     *         description="Filter by contract status",
     *         @OA\Schema(type="string", enum={"draft", "pending_signature", "signed", "active", "completed", "terminated"})
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="perPage",
     *         in="query",
     *         description="Items per page",
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contracts retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Contracts retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="contracts",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/ContractResource")
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="total", type="integer"),
     *                     @OA\Property(property="count", type="integer"),
     *                     @OA\Property(property="perPage", type="integer"),
     *                     @OA\Property(property="currentPage", type="integer"),
     *                     @OA\Property(property="totalPages", type="integer")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $contracts = QueryBuilder::for(Contract::class)
            ->with(['quotation.lead', 'project'])
            ->allowedFilters(['status'])
            ->allowedSorts(['created_at', 'signed_at', 'status'])
            ->defaultSort('-created_at')
            ->paginate($request->get('perPage', 10));

        return ApiResponse::success(
            new ContractCollection($contracts),
            __('messages.contracts.retrieved')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/quotations/{quotationId}/contract",
     *     summary="Generate contract from quotation",
     *     tags={"Contracts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *     @OA\Parameter(
     *         name="quotationId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Contract generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Contract generated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/ContractResource"
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
     *     )
     * )
     */
    public function generateFromQuotation(int $quotationId): JsonResponse
    {
        $quotation = Quotation::findOrFail($quotationId);

        // Check if contract already exists
        $existingContract = Contract::where('quotation_id', $quotationId)->first();
        if ($existingContract) {
            return ApiResponse::error(
                __('messages.contracts.already_exists'),
                422
            );
        }

        $contract = $this->contractService->generateDraft($quotation);

        return ApiResponse::success(
            new ContractResource($contract->load(['quotation.lead', 'project'])),
            __('messages.contracts.generated'),
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/contracts/{id}",
     *     summary="Get contract details",
     *     tags={"Contracts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contract retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Contract retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/ContractResource"
     *             )
     *         )
     *     )
     * )
     */
    public function show(Contract $contract): JsonResponse
    {
        $contract->load(['quotation.lead', 'project']);

        return ApiResponse::success(
            new ContractResource($contract),
            __('messages.contracts.retrieved')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/contracts/{id}/sign",
     *     summary="Sign contract with uploaded PDF",
     *     tags={"Contracts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="signedPdf",
     *                     type="string",
     *                     format="binary",
     *                     description="Signed contract PDF file"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contract signed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Contract signed successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/ContractResource"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="signedPdf",
     *                     type="array",
     *                     @OA\Items(type="string", example="The signed PDF field is required.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function sign(Request $request, Contract $contract): JsonResponse
    {
        $request->validate([
            'signedPdf' => 'required|file|mimes:pdf|max:10240', // 10MB max
        ]);

        try {
            $signedContract = $this->contractService->markAsSigned(
                $contract,
                $request->file('signedPdf')
            );

            return ApiResponse::success(
                new ContractResource($signedContract->load(['quotation.lead', 'project'])),
                __('messages.contracts.signed')
            );
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/contracts/{id}/status",
     *     summary="Update contract status",
     *     tags={"Contracts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         required=true,
     *         @OA\Schema(type="string", enum={"ar", "en"}, default="en")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 enum={"draft", "pending_signature", "signed", "active", "completed", "terminated"},
     *                 example="pending_signature"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Contract status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Contract status updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 ref="#/components/schemas/ContractResource"
     *             )
     *         )
     *     )
     * )
     */
    public function updateStatus(Request $request, Contract $contract): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:draft,pending_signature,signed,active,completed,terminated',
        ]);

        $status = ContractStatus::from($request->status);
        $updatedContract = $this->contractService->updateStatus($contract, $status);

        return ApiResponse::success(
            new ContractResource($updatedContract->load(['quotation.lead', 'project'])),
            __('messages.contracts.status_updated')
        );
    }
}
