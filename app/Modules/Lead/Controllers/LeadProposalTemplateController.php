<?php

namespace App\Modules\Lead\Controllers;

use App\Enums\ProposalTemplate;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class LeadProposalTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $templates = array_map(fn (ProposalTemplate $template) => [
            'id' => $template->value,
            'name' => $template->label(),
            'description' => $template->description(),
        ], ProposalTemplate::cases());

        return ApiResponse::success($templates, 'Proposal templates retrieved successfully.');
    }
}
