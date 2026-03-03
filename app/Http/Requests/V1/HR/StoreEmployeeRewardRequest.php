<?php

namespace App\Http\Requests\V1\HR;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRewardRequest extends FormRequest
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
            'user_id' => ['required', 'exists:users,id'],
            'reward_type_id' => ['required', 'exists:reward_types,id'],
            'reward_date' => ['required', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:1000'],
            'points_used' => ['nullable', 'integer', 'min:0'],
            'performance_score' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'rewardable_type' => ['nullable', 'string'],
            'rewardable_id' => ['nullable', 'integer'],
        ];
    }
}
