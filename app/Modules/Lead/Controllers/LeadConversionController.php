<?php

namespace App\Modules\Lead\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Lead\Actions\ConvertLeadToClientProjectAction;
use App\Modules\Lead\Models\Lead;
use Illuminate\Http\JsonResponse;

class LeadConversionController extends Controller
{
    /**
     * Convert a won lead into a Client + Project.
     *
     * POST /api/v1/leads/{lead}/convert
     *
     * Delegates entirely to ConvertLeadToClientProjectAction.
     * Returns the new client_id and project_id so the frontend can
     * redirect to the project detail page immediately.
     */
    public function convert(
        Lead $lead,
        ConvertLeadToClientProjectAction $action
    ): JsonResponse {
        $result = $action->execute($lead);

        /** @var \App\Modules\Client\Models\Client  $client  */
        /** @var \App\Modules\Project\Models\Project $project */
        $client  = $result['client'];
        $project = $result['project'];

        return ApiResponse::success(
            [
                'client_id'    => $client->id,
                'client_name'  => $client->display_name,
                'project_id'   => $project->id,
                'project_title'=> $project->title,
            ],
            'Lead successfully converted to Client and Project.'
        );
    }
}
