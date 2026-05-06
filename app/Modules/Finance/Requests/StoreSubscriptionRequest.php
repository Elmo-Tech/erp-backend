<?php

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via $user->can()
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'provider'        => ['nullable', 'string', 'max:255'],
            'billingCycle'    => ['required', 'string', 'in:monthly,yearly'],
            'cost'            => ['required', 'numeric', 'min:0'],
            'currency'        => ['sometimes', 'string', 'size:3'],
            'nextBillingDate' => ['required', 'date'],
            'status'          => ['sometimes', 'string', 'in:active,cancelled'],
            'notes'           => ['nullable', 'string'],
        ];
    }
}
