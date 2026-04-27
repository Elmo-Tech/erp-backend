<?php

namespace App\Modules\Lead\Controllers;

use App\Helpers\ApiResponse;
use App\Modules\Client\Resources\ClientResource;
use App\Modules\Lead\Actions\ConvertLeadToClientAction;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Requests\ConvertLeadRequest;
use Illuminate\Http\JsonResponse;

class LeadConversionController
{
    public function __construct(
        private ConvertLeadToClientAction $convertLeadToClientAction
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
        $this->middleware('permission:convert_leads')->only(['convert']);
        $this->middleware('permission:view_lead_conversion_status')->only(['canConvert']);
    }

    public function convert(ConvertLeadRequest $request, Lead $lead): JsonResponse
    {
        $canConvert = $this->convertLeadToClientAction->canConvert($lead);

        if (! $canConvert['canConvert']) {
            return ApiResponse::error($canConvert['reason'], 400);
        }

        if (isset($canConvert['existingClient'])) {
            $this->convertLeadToClientAction->linkToExistingClient($lead, $canConvert['existingClient']);

            return ApiResponse::success(
                new ClientResource($canConvert['existingClient']->load('projects')),
                __('Lead linked to existing client successfully')
            );
        }

        $client = $this->convertLeadToClientAction->execute(
            $lead,
            $request->user(),
            $request->validated()
        );

        return ApiResponse::success(
            new ClientResource($client),
            __('Lead converted to client successfully')
        );
    }

    public function canConvert(Lead $lead): JsonResponse
    {
        $result = $this->convertLeadToClientAction->canConvert($lead);

        return ApiResponse::success([
            'canConvert' => $result['canConvert'],
            'reason' => $result['reason'],
            'existingClientId' => $result['existingClient']->id ?? null,
            'existingClientName' => $result['existingClient']->name ?? null,
        ]);
    }
}
