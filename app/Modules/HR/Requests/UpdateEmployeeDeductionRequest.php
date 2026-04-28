<?php

namespace App\Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeDeductionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'deduction_type_id' => ['sometimes', 'exists:deduction_types,id'],
            'deduction_date' => ['sometimes', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['sometimes', 'string', 'max:1000'],
            'occurrences' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
