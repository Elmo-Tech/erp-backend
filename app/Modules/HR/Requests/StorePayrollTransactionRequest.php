<?php

namespace App\Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via $user->can()
    }

    public function rules(): array
    {
        return [
            'user_id'          => ['required', 'integer', 'exists:users,id'],
            'amount'           => ['required', 'numeric', 'min:0.01'],
            'type'             => ['required', 'string', 'in:manual_bonus,manual_deduction'],
            'reason'           => ['required', 'string', 'max:500'],
            'transaction_date' => ['required', 'date'],
        ];
    }
}
