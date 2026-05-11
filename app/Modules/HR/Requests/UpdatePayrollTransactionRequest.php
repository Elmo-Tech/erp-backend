<?php

namespace App\Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via $user->can()
    }

    public function rules(): array
    {
        return [
            'amount'           => ['sometimes', 'numeric', 'min:0.01'],
            'type'             => ['sometimes', 'string', 'in:manual_bonus,manual_deduction'],
            'reason'           => ['sometimes', 'string', 'max:500'],
            'transaction_date' => ['sometimes', 'date'],
        ];
    }
}
