<?php

namespace App\Http\Requests\V1\HR;

use Illuminate\Foundation\Http\FormRequest;

class StoreRewardTypeRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50', 'unique:reward_types,code'],
            'description' => ['nullable', 'string', 'max:1000'],
            'calculation_type' => ['required', 'string', 'in:fixed,percentage,points_based'],
            'base_amount' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'requires_approval' => ['boolean'],
            'criteria' => ['nullable', 'array'],
            'multipliers' => ['nullable', 'array'],
        ];
    }
}
