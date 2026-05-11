<?php

namespace App\Modules\HR\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Modules\HR\Models\PayrollTransaction
 */
class PayrollTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'userId'          => $this->user_id,
            'amount'          => (float) $this->amount,
            'type'            => $this->type,
            'reason'          => $this->reason,
            'transactionDate' => $this->transaction_date?->toDateString(),
            'user'            => $this->whenLoaded('user', fn () => [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
            ]),
            'createdAt'       => $this->created_at?->toIso8601String(),
            'updatedAt'       => $this->updated_at?->toIso8601String(),
        ];
    }
}
