<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ContractTemplate;
use App\Models\ProposalTemplate;
use App\Models\Quotation;
use App\Services\Sales\TemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class TemplateController extends Controller
{
    public function __construct(
        private TemplateService $templateService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/templates/proposals",
     *     operationId="getProposalTemplates",
     *     tags={"Templates"},
     *     summary="Get proposal templates",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Templates retrieved successfully")
     * )
     */
    public function proposalTemplates(Request $request): JsonResponse
    {
        $templates = $this->templateService->getProposalTemplates($request->input('type'));

        return ApiResponse::success($templates, __('templates.retrieved'));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/templates/proposals",
     *     operationId="createProposalTemplate",
     *     tags={"Templates"},
     *     summary="Create proposal template",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Template created successfully")
     * )
     */
    public function storeProposalTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50',
            'content' => 'required|string',
            'placeholders' => 'nullable|array',
            'isActive' => 'nullable|boolean',
        ]);

        $template = $this->templateService->createProposalTemplate($validated, $request->user());

        return ApiResponse::success($template, __('templates.created'), 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/templates/proposals/{id}",
     *     operationId="updateProposalTemplate",
     *     tags={"Templates"},
     *     summary="Update proposal template",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Template updated successfully")
     * )
     */
    public function updateProposalTemplate(Request $request, ProposalTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:50',
            'content' => 'nullable|string',
            'placeholders' => 'nullable|array',
            'isActive' => 'nullable|boolean',
        ]);

        $template = $this->templateService->updateProposalTemplate($template, $validated, $request->user());

        return ApiResponse::success($template, __('templates.updated'));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/templates/proposals/{id}",
     *     operationId="deleteProposalTemplate",
     *     tags={"Templates"},
     *     summary="Delete proposal template",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Template deleted successfully")
     * )
     */
    public function destroyProposalTemplate(ProposalTemplate $template): JsonResponse
    {
        $template->delete();

        return ApiResponse::success(null, __('templates.deleted'));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/templates/proposals/{id}/generate",
     *     operationId="generateProposal",
     *     tags={"Templates"},
     *     summary="Generate proposal from template",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Proposal generated successfully")
     * )
     */
    public function generateProposal(Request $request, ProposalTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'quotationId' => 'required|exists:quotations,id',
            'customValues' => 'nullable|array',
        ]);

        $quotation = Quotation::findOrFail($validated['quotationId']);
        $content = $this->templateService->generateProposalContent(
            $template,
            $quotation,
            $validated['customValues'] ?? []
        );

        return ApiResponse::success([
            'content' => $content,
            'templateId' => $template->id,
            'quotationId' => $quotation->id,
        ], __('templates.generated'));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/templates/contracts",
     *     operationId="getContractTemplates",
     *     tags={"Templates"},
     *     summary="Get contract templates",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Templates retrieved successfully")
     * )
     */
    public function contractTemplates(Request $request): JsonResponse
    {
        $templates = $this->templateService->getContractTemplates($request->input('type'));

        return ApiResponse::success($templates, __('templates.retrieved'));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/templates/contracts",
     *     operationId="createContractTemplate",
     *     tags={"Templates"},
     *     summary="Create contract template",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Template created successfully")
     * )
     */
    public function storeContractTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50',
            'content' => 'required|string',
            'placeholders' => 'nullable|array',
            'isActive' => 'nullable|boolean',
        ]);

        $template = $this->templateService->createContractTemplate($validated, $request->user());

        return ApiResponse::success($template, __('templates.created'), 201);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/templates/contracts/{id}",
     *     operationId="updateContractTemplate",
     *     tags={"Templates"},
     *     summary="Update contract template",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Template updated successfully")
     * )
     */
    public function updateContractTemplate(Request $request, ContractTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:50',
            'content' => 'nullable|string',
            'placeholders' => 'nullable|array',
            'isActive' => 'nullable|boolean',
        ]);

        $template = $this->templateService->updateContractTemplate($template, $validated, $request->user());

        return ApiResponse::success($template, __('templates.updated'));
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/templates/contracts/{id}",
     *     operationId="deleteContractTemplate",
     *     tags={"Templates"},
     *     summary="Delete contract template",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Template deleted successfully")
     * )
     */
    public function destroyContractTemplate(ContractTemplate $template): JsonResponse
    {
        $template->delete();

        return ApiResponse::success(null, __('templates.deleted'));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/templates/contracts/{id}/generate",
     *     operationId="generateContract",
     *     tags={"Templates"},
     *     summary="Generate contract from template",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Contract generated successfully")
     * )
     */
    public function generateContract(Request $request, ContractTemplate $template): JsonResponse
    {
        $validated = $request->validate([
            'partyOne' => 'required|array',
            'partyTwo' => 'required|array',
            'totalAmount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'customValues' => 'nullable|array',
        ]);

        $content = $this->templateService->generateContractContent(
            $template,
            $validated['partyOne'],
            $validated['partyTwo'],
            $validated['totalAmount'],
            $validated['currency'] ?? 'SAR',
            $validated['customValues'] ?? []
        );

        return ApiResponse::success([
            'content' => $content,
            'templateId' => $template->id,
        ], __('templates.generated'));
    }
}
