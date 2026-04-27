<?php

namespace App\Modules\Template\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Template\Actions\CreateProposalTemplateAction;
use App\Modules\Template\Actions\CreateContractTemplateAction;
use App\Modules\Template\Actions\UpdateProposalTemplateAction;
use App\Modules\Template\Actions\UpdateContractTemplateAction;
use App\Modules\Template\Actions\RenderTemplateAction;
use App\Modules\Template\Actions\GetTemplatesAction;
use App\Modules\Template\Models\ProposalTemplate;
use App\Modules\Contract\Models\ContractTemplate;
use App\Models\Quotation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth:sanctum', 'locale']);
    }

    public function proposalTemplates(Request $request, GetTemplatesAction $action): JsonResponse
    {
        $templates = $action->getProposalTemplates($request->input('type'));

        return ApiResponse::success($templates, __('templates.retrieved'));
    }

    public function storeProposalTemplate(Request $request, CreateProposalTemplateAction $action): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50',
            'content' => 'required|string',
            'placeholders' => 'nullable|array',
            'isActive' => 'nullable|boolean',
        ]);

        $template = $action->execute($validated, $request->user());

        return ApiResponse::success($template, __('templates.created'), 201);
    }

    public function updateProposalTemplate(Request $request, ProposalTemplate $template, UpdateProposalTemplateAction $action): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:50',
            'content' => 'nullable|string',
            'placeholders' => 'nullable|array',
            'isActive' => 'nullable|boolean',
        ]);

        $template = $action->execute($template, $validated, $request->user());

        return ApiResponse::success($template, __('templates.updated'));
    }

    public function destroyProposalTemplate(ProposalTemplate $template): JsonResponse
    {
        $template->delete();

        return ApiResponse::success(null, __('templates.deleted'));
    }

    public function generateProposal(Request $request, ProposalTemplate $template, RenderTemplateAction $action): JsonResponse
    {
        $validated = $request->validate([
            'quotationId' => 'required|exists:quotations,id',
            'customValues' => 'nullable|array',
        ]);

        $quotation = Quotation::findOrFail($validated['quotationId']);
        $content = $action->renderProposal($template, $quotation, $validated['customValues'] ?? []);

        return ApiResponse::success([
            'content' => $content,
            'templateId' => $template->id,
            'quotationId' => $quotation->id,
        ], __('templates.generated'));
    }

    public function contractTemplates(Request $request, GetTemplatesAction $action): JsonResponse
    {
        $templates = $action->getContractTemplates($request->input('type'));

        return ApiResponse::success($templates, __('templates.retrieved'));
    }

    public function storeContractTemplate(Request $request, CreateContractTemplateAction $action): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50',
            'content' => 'required|string',
            'placeholders' => 'nullable|array',
            'isActive' => 'nullable|boolean',
        ]);

        $template = $action->execute($validated, $request->user());

        return ApiResponse::success($template, __('templates.created'), 201);
    }

    public function updateContractTemplate(Request $request, ContractTemplate $template, UpdateContractTemplateAction $action): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:50',
            'content' => 'nullable|string',
            'placeholders' => 'nullable|array',
            'isActive' => 'nullable|boolean',
        ]);

        $template = $action->execute($template, $validated, $request->user());

        return ApiResponse::success($template, __('templates.updated'));
    }

    public function destroyContractTemplate(ContractTemplate $template): JsonResponse
    {
        $template->delete();

        return ApiResponse::success(null, __('templates.deleted'));
    }

    public function generateContract(Request $request, ContractTemplate $template, RenderTemplateAction $action): JsonResponse
    {
        $validated = $request->validate([
            'partyOne' => 'required|array',
            'partyTwo' => 'required|array',
            'totalAmount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'customValues' => 'nullable|array',
        ]);

        $content = $action->renderContract(
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
