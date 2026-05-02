<?php

namespace App\Modules\Lead\Controllers;

use App\Enums\LeadStage;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Lead\Actions\ProcessLeadStageUpdateAction;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Requests\UpdateLeadStageRequest;
use Illuminate\Http\JsonResponse;

class LeadPipelineController extends Controller
{
    /**
     * Update the pipeline stage of a lead.
     *
     * POST /api/v1/leads/{lead}/stage
     *
     * Accepts the new stage and an array of stage results (documents produced
     * during the transition). Delegates all business logic to
     * ProcessLeadStageUpdateAction, which runs inside a DB transaction.
     */
    public function updateStage(
        UpdateLeadStageRequest $request,
        Lead $lead,
        ProcessLeadStageUpdateAction $action
    ): JsonResponse {
        $newStage     = LeadStage::from($request->validated('new_stage'));
        $stageResults = $request->validated('stage_results');

        $updatedLead = $action->execute($lead->id, $newStage, $stageResults);

        return ApiResponse::success(
            [
                'id'    => $updatedLead->id,
                'stage' => $updatedLead->stage->value,
            ],
            'Lead stage updated successfully.'
        );
    }
}
