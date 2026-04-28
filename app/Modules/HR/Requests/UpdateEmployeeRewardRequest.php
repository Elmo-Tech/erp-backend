<?php

namespace App\Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRewardRequest extends FormRequest
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
            'reward_type_id' => ['sometimes', 'exists:reward_types,id'],
            'reward_date' => ['sometimes', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['sometimes', 'string', 'max:1000'],
            'points_used' => ['nullable', 'integer', 'min:0'],
            'performance_score' => ['nullable', 'numeric', 'min:0', 'max:5'],
        ];
    }
}
