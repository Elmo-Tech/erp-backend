<?php

namespace App\Http\Resources\V1\HR;

use App\Models\HrPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HrPolicy
 */
class HrPolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var HrPolicy $policy */
        $policy = $this->resource;

        return [
            'policyId' => $policy->id,
            'settings' => $policy->settings,
            'effectiveFrom' => $policy->effective_from?->toDateString(),
            'createdBy' => $policy->created_by,
            'createdAt' => $policy->created_at?->toISOString(),
            'notes' => $policy->notes,
        ];
    }
}
