<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Contracts\StoreContractRequest;
use App\Http\Requests\V1\Contracts\UpdateContractRequest;
use App\Http\Requests\V1\Contracts\SignContractRequest;
use App\Http\Resources\V1\Contracts\ContractCollection;
use App\Http\Resources\V1\Contracts\ContractResource;
use App\Models\Contract;
use App\Models\ContractMilestone;
use App\Models\Quotation;
use App\Services\Sales\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

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
     *     operationId="getContracts",
     *     tags={"Contracts"},
     *     summary="Get all contracts with pagination",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Contracts retrieved successfully")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $contracts = $this->contractService->getContracts($request->all());

        return ApiResponse::success(
            new ContractCollection($contracts),
            __('contracts.retrieved')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/contracts",
     *     operationId="createContract",
     *     tags={"Contracts"},
     *     summary="Create a new contract",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=201, description="Contract created successfully")
     * )
     */
    public function store(StoreContractRequest $request): JsonResponse
    {
        $contract = $this->contractService->createContract(
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new ContractResource($contract),
            __('contracts.created'),
            201
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/contracts/from-quotation/{quotationId}",
     *     operationId="createContractFromQuotation",
     *     tags={"Contracts"},
     *     summary="Create contract from quotation",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=201, description="Contract created successfully")
     * )
     */
    public function createFromQuotation(StoreContractRequest $request, Quotation $quotation): JsonResponse
    {
        $contract = $this->contractService->createFromQuotation(
            $quotation,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new ContractResource($contract),
            __('contracts.created'),
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/contracts/{id}",
     *     operationId="getContract",
     *     tags={"Contracts"},
     *     summary="Get a single contract",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Contract retrieved successfully")
     * )
     */
    public function show(Contract $contract): JsonResponse
    {
        $contract->load(['milestones.invoice', 'quotation', 'project', 'template']);

        return ApiResponse::success(
            new ContractResource($contract),
            __('contracts.retrieved')
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/contracts/{id}",
     *     operationId="updateContract",
     *     tags={"Contracts"},
     *     summary="Update a contract",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Contract updated successfully")
     * )
     */
    public function update(UpdateContractRequest $request, Contract $contract): JsonResponse
    {
        $contract = $this->contractService->updateContract(
            $contract,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new ContractResource($contract),
            __('contracts.updated')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/contracts/{id}/sign",
     *     operationId="signContract",
     *     tags={"Contracts"},
     *     summary="Sign a contract",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Contract signed successfully")
     * )
     */
    public function sign(SignContractRequest $request, Contract $contract): JsonResponse
    {
        $contract = $this->contractService->signContract(
            $contract,
            $request->validated()['signedByClient'],
            $request->user()
        );

        return ApiResponse::success(
            new ContractResource($contract),
            __('contracts.signed')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/contracts/{id}/activate",
     *     operationId="activateContract",
     *     tags={"Contracts"},
     *     summary="Activate a signed contract",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Contract activated successfully")
     * )
     */
    public function activate(Contract $contract): JsonResponse
    {
        $contract = $this->contractService->activateContract(
            $contract,
            request()->user()
        );

        return ApiResponse::success(
            new ContractResource($contract),
            __('contracts.activated')
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/contracts/{id}/status",
     *     operationId="updateContractStatus",
     *     tags={"Contracts"},
     *     summary="Update contract status",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"draft", "pending_signature", "signed", "active", "completed", "terminated"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Contract status updated successfully")
     * )
     */
    public function updateStatus(Request $request, Contract $contract): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:draft,pending_signature,signed,active,completed,terminated',
        ]);

        $contract = $this->contractService->updateContractStatus(
            $contract,
            $request->input('status'),
            $request->user()
        );

        return ApiResponse::success(
            new ContractResource($contract),
            __('contracts.status_updated')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/contracts/milestones/due",
     *     operationId="getDueMilestones",
     *     tags={"Contracts"},
     *     summary="Get milestones due for payment",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Milestones retrieved successfully")
     * )
     */
    public function dueMilestones(): JsonResponse
    {
        $milestones = $this->contractService->getDueMilestones();

        return ApiResponse::success($milestones, __('contracts.milestones_retrieved'));
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/contracts/milestones/{id}/paid",
     *     operationId="markMilestonePaid",
     *     tags={"Contracts"},
     *     summary="Mark milestone as paid",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Milestone marked as paid")
     * )
     */
    public function markMilestonePaid(Request $request, ContractMilestone $milestone): JsonResponse
    {
        $milestone = $this->contractService->markMilestonePaid(
            $milestone,
            $request->input('invoiceId')
        );

        return ApiResponse::success($milestone, __('contracts.milestone_paid'));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/contracts/{id}",
     *     operationId="deleteContract",
     *     tags={"Contracts"},
     *     summary="Delete a contract",
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Contract deleted successfully")
     * )
     */
    public function destroy(Contract $contract): JsonResponse
    {
        $contract->delete();

        return ApiResponse::success(null, __('contracts.deleted'));
    }
}
