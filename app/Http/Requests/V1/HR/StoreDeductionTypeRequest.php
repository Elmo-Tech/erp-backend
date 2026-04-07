<?php

namespace App\Http\Requests\V1\HR;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeductionTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:deduction_types,code'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['required', 'string', 'in:attendance,performance,behavior,financial,other'],
            'is_active' => ['boolean'],
            'requires_approval' => ['boolean'],
            'auto_calculate' => ['boolean'],
            'default_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'frequency' => ['nullable', 'string', 'in:once,daily,weekly,monthly'],
        ];
    }
}
