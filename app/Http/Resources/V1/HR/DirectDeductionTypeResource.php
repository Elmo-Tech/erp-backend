<?php

namespace App\Http\Resources\V1\HR;

use App\Models\DeductionType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DeductionType */
class DirectDeductionTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var DeductionType $deductionType */
        $deductionType = $this->resource;

        return [
            'id' => $deductionType->id,
            'name' => $deductionType->name,
            'code' => $deductionType->code,
            'description' => $deductionType->description,
            'amount' => (float) $deductionType->amount,
            'requiresApproval' => $deductionType->requires_approval,
        ];
    }
}
