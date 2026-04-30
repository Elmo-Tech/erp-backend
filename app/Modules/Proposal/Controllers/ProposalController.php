<?php

namespace App\Modules\Proposal\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Proposal\Requests\FastTrackProposalRequest;
use App\Modules\Proposal\Requests\RejectProposalRequest;
use App\Modules\Proposal\Requests\StoreProposalRequest;
use App\Modules\Proposal\Requests\UpdateProposalRequest;
use App\Modules\Proposal\Resources\ProposalCollection;
use App\Modules\Proposal\Resources\ProposalResource;
use App\Modules\Proposal\Models\Proposal;
use App\Modules\Sales\Services\ProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

class ProposalController extends Controller
{
    public function __construct(
        private ProposalService $proposalService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/proposals",
     *     operationId="getProposals",
     *     tags={"Proposals"},
     *     summary="Get all proposals with pagination and filtering",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="filter[status]", in="query", @OA\Schema(type="string", enum={"draft", "sent", "viewed", "approved", "rejected", "expired"})),
     *     @OA\Parameter(name="filter[clientId]", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="filter[search]", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="perPage", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Proposals retrieved successfully"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $proposals = $this->proposalService->getProposals($request->all());

        return ApiResponse::success(
            new ProposalCollection($proposals),
            __('proposals.retrieved')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/proposals",
     *     operationId="createProposal",
     *     tags={"Proposals"},
     *     summary="Create a new proposal",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"clientId", "title"},
     *
     *             @OA\Property(property="clientId", type="integer", example=1),
     *             @OA\Property(property="templateId", type="integer", example=1),
     *             @OA\Property(property="title", type="string", example="Technical Proposal V1"),
     *             @OA\Property(property="content", type="string"),
     *             @OA\Property(property="expiryDate", type="string", format="date", example="2026-01-25"),
     *             @OA\Property(property="internalNotes", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Proposal created successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreProposalRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $validatedData = $request->validated();

            // Handle file upload if present (for upload path)
            if ($request->hasFile('file')) {
                $validatedData['file'] = $request->file('file');
            }

            $proposal = $this->proposalService->createProposal(
                $validatedData,
                $request->user()
            );

            return ApiResponse::success(
                new ProposalResource($proposal->load(['client', 'task'])),
                __('proposals.created'),
                201
            );
        });
    }

    /**
     * @OA\Get(
     *     path="/api/v1/proposals/{id}",
     *     operationId="getProposal",
     *     tags={"Proposals"},
     *     summary="Get a single proposal",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Proposal retrieved successfully"),
     *     @OA\Response(response=404, description="Proposal not found")
     * )
     */
    public function show(Proposal $proposal): JsonResponse
    {
        $proposal->load(['client', 'template', 'quotations']);

        return ApiResponse::success(
            new ProposalResource($proposal),
            __('proposals.retrieved')
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/proposals/{id}",
     *     operationId="updateProposal",
     *     tags={"Proposals"},
     *     summary="Update an existing proposal",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent()),
     *
     *     @OA\Response(response=200, description="Proposal updated successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateProposalRequest $request, Proposal $proposal): JsonResponse
    {
        if (! $proposal->canBeEdited()) {
            return ApiResponse::error(__('proposals.cannot_edit'), 403);
        }

        $proposal = $this->proposalService->updateProposal(
            $proposal,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new ProposalResource($proposal->load('client')),
            __('proposals.updated')
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/proposals/{id}",
     *     operationId="deleteProposal",
     *     tags={"Proposals"},
     *     summary="Delete a proposal",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="Accept-Language", in="header", required=true, @OA\Schema(type="string", enum={"en", "ar"})),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Proposal deleted successfully"),
     *     @OA\Response(response=404, description="Proposal not found")
     * )
     */
    public function destroy(Proposal $proposal): JsonResponse
    {
        if (! $proposal->canBeEdited()) {
            return ApiResponse::error(__('proposals.cannot_delete'), 403);
        }

        $proposal->delete();

        return ApiResponse::success(null, __('proposals.deleted'));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/proposals/{id}/upload",
     *     operationId="uploadProposalFile",
     *     tags={"Proposals"},
     *     summary="Upload proposal file (PDF/PPT)",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="File uploaded successfully")
     * )
     */
    public function uploadFile(Request $request, Proposal $proposal): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,ppt,pptx', 'max:20480'],
        ]);

        $proposal = $this->proposalService->uploadFile(
            $proposal,
            $request->file('file'),
            $request->user()
        );

        return ApiResponse::success(
            new ProposalResource($proposal),
            __('proposals.file_uploaded')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/proposals/{id}/send",
     *     operationId="sendProposal",
     *     tags={"Proposals"},
     *     summary="Send proposal to client",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Proposal sent successfully")
     * )
     */
    public function send(Request $request, Proposal $proposal): JsonResponse
    {
        $proposal = $this->proposalService->sendProposal($proposal, $request->user());

        return ApiResponse::success(
            new ProposalResource($proposal),
            __('proposals.sent')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/proposals/{id}/approve",
     *     operationId="approveProposal",
     *     tags={"Proposals"},
     *     summary="Approve proposal",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Proposal approved successfully")
     * )
     */
    public function approve(Request $request, Proposal $proposal): JsonResponse
    {
        $proposal = $this->proposalService->approveProposal($proposal, $request->user());

        return ApiResponse::success(
            new ProposalResource($proposal),
            __('proposals.approved')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/proposals/{id}/reject",
     *     operationId="rejectProposal",
     *     tags={"Proposals"},
     *     summary="Reject proposal",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"rejectionReason"},
     *
     *             @OA\Property(property="rejectionReason", type="string"),
     *             @OA\Property(property="rejectionNotes", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Proposal rejected successfully")
     * )
     */
    public function reject(RejectProposalRequest $request, Proposal $proposal): JsonResponse
    {
        $proposal = $this->proposalService->rejectProposal(
            $proposal,
            $request->validated(),
            $request->user()
        );

        return ApiResponse::success(
            new ProposalResource($proposal),
            __('proposals.rejected')
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/proposals/fast-track",
     *     operationId="createFastTrackProposal",
     *     tags={"Proposals"},
     *     summary="Create fast track proposal (skip to quotation)",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"clientId", "fastTrackReason"},
     *
     *             @OA\Property(property="clientId", type="integer"),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="projectName", type="string"),
     *             @OA\Property(property="fastTrackReason", type="string", example="SaaS Standard Package")
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Fast track proposal created")
     * )
     */
    public function fastTrack(FastTrackProposalRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $proposal = $this->proposalService->createFastTrack(
                $request->validated(),
                $request->user()
            );

            return ApiResponse::success(
                new ProposalResource($proposal->load('client')),
                __('proposals.fast_track_created'),
                201
            );
        });
    }

    /**
     * @OA\Get(
     *     path="/api/v1/proposals-expiring",
     *     operationId="getExpiringProposals",
     *     tags={"Proposals"},
     *     summary="Get proposals expiring soon",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="days", in="query", @OA\Schema(type="integer", default=3)),
     *
     *     @OA\Response(response=200, description="Expiring proposals retrieved")
     * )
     */
    public function expiring(Request $request): JsonResponse
    {
        $days = $request->get('days', 3);
        $proposals = $this->proposalService->getExpiringProposals($days);

        return ApiResponse::success(
            new ProposalCollection($proposals),
            __('proposals.expiring_retrieved')
        );
    }
}
