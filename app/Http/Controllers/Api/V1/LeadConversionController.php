<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Leads\ConvertLeadRequest;
use App\Http\Resources\V1\Client\ClientResource;
use App\Models\Lead;
use App\Services\Sales\LeadConversionService;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Lead Conversion",
 *     description="Lead to Client conversion operations"
 * )
 */
class LeadConversionController extends Controller
{
    public function __construct(
        private LeadConversionService $conversionService
    ) {
        $this->middleware(['auth:sanctum', 'locale']);
        $this->middleware('permission:convert_leads')->only(['convert']);
        $this->middleware('permission:view_lead_conversion_status')->only(['canConvert']);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/leads/{lead}/convert",
     *     summary="Convert a lead to a client",
     *     tags={"Lead Conversion"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="lead",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="company", type="string"),
     *             @OA\Property(property="position", type="string"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="city", type="string"),
     *             @OA\Property(property="country", type="string"),
     *             @OA\Property(property="website", type="string"),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lead converted successfully"
     *     ),
     *     @OA\Response(response=400, description="Lead cannot be converted"),
     *     @OA\Response(response=404, description="Lead not found")
     * )
     */
    public function convert(ConvertLeadRequest $request, Lead $lead): JsonResponse
    {
        $canConvert = $this->conversionService->canConvert($lead);

        if (!$canConvert['canConvert']) {
            return ApiResponse::error($canConvert['reason'], 400);
        }

        // If existing client found, link instead of creating new
        if (isset($canConvert['existingClient'])) {
            $this->conversionService->linkToExistingClient($lead, $canConvert['existingClient']);
            return ApiResponse::success(
                new ClientResource($canConvert['existingClient']->load('projects')),
                __('Lead linked to existing client successfully')
            );
        }

        $client = $this->conversionService->convertToClient(
            $lead,
            $request->user(),
            $request->validated()
        );

        return ApiResponse::success(
            new ClientResource($client),
            __('Lead converted to client successfully')
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/leads/{lead}/can-convert",
     *     summary="Check if a lead can be converted",
     *     tags={"Lead Conversion"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="lead",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Conversion check result")
     * )
     */
    public function canConvert(Lead $lead): JsonResponse
    {
        $result = $this->conversionService->canConvert($lead);

        return ApiResponse::success([
            'canConvert' => $result['canConvert'],
            'reason' => $result['reason'],
            'existingClientId' => $result['existingClient']->id ?? null,
            'existingClientName' => $result['existingClient']->name ?? null,
        ]);
    }
}
