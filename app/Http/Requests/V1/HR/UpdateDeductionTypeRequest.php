<?php

namespace App\Http\Requests\V1\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeductionTypeRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('deduction_types', 'code')->ignore($this->deduction_type)],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['sometimes', 'string', 'in:attendance,performance,behavior,financial,other'],
            'is_active' => ['boolean'],
            'requires_approval' => ['boolean'],
            'auto_calculate' => ['boolean'],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'frequency' => ['nullable', 'string', 'in:once,daily,weekly,monthly'],
        ];
    }
}
