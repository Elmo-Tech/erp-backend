<?php

namespace App\Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRewardTypeRequest extends FormRequest
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
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('reward_types', 'code')->ignore($this->reward_type)],
            'description' => ['nullable', 'string', 'max:1000'],
            'calculation_type' => ['sometimes', 'string', 'in:fixed,percentage,points_based'],
            'base_amount' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'requires_approval' => ['boolean'],
            'criteria' => ['nullable', 'array'],
            'multipliers' => ['nullable', 'array'],
        ];
    }
}
