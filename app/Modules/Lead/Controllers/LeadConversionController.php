<?php

namespace App\Modules\Lead\Controllers;

use App\Enums\LeadStage;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Modules\Client\Models\Client;
use App\Modules\Lead\Actions\ConvertLeadToClientProjectAction;
use App\Modules\Lead\Models\Lead;
use App\Modules\Project\Models\Project;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        Request $request,
        Lead $lead,
        ConvertLeadToClientProjectAction $action
    ): JsonResponse {
        abort_unless($request->user()?->canAny(['convert_leads', 'manage_leads']), 403, 'Unauthorized');

        if ($lead->stage !== LeadStage::WON) {
            $message = 'Only won leads can be converted.';

            return ApiResponse::error($message, 422, ['stage' => [$message]]);
        }

        try {
            $result = $action->execute($lead);
        } catch (DomainException $exception) {
            return ApiResponse::error($exception->getMessage(), 422);
        }

        /** @var Client $client */
        /** @var Project $project */
        $client = $result['client'];
        $project = $result['project'];

        return ApiResponse::success(
            [
                'client_id' => $client->id,
                'client_name' => $client->display_name,
                'project_id' => $project->id,
                'project_title' => $project->title,
            ],
            'Lead successfully converted to Client and Project.'
        );
    }
}
