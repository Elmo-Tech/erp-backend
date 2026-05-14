<?php

namespace App\Modules\Finance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via $user->can()
    }

    public function rules(): array
    {
        return [
            'clientId' => ['sometimes', 'nullable', 'integer', 'exists:clients,id'],
            'projectId' => ['sometimes', 'nullable', 'integer', 'exists:projects,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'provider' => ['sometimes', 'nullable', 'string', 'max:255'],
            'billingCycle' => ['sometimes', 'string', 'in:monthly,quarterly,semi_annual,annual,yearly'],
            'cost' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'nextBillingDate' => ['sometimes', 'date'],
            'status' => ['sometimes', 'string', 'in:active,cancelled'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
