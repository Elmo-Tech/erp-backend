<?php

namespace App\Http\Requests\V1\UserPoints;

use Illuminate\Foundation\Http\FormRequest;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class DeductPointsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage_user_points');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'userId' => ['required', 'integer', 'exists:users,id'],
            'points' => ['required', 'integer', 'min:1', 'max:1000'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'userId.required' => __('User selection is required'),
            'userId.exists' => __('Selected user does not exist'),
            'points.required' => __('Points amount is required'),
            'points.min' => __('Points must be at least 1'),
            'points.max' => __('Points cannot exceed 1000 per transaction'),
            'reason.required' => __('Reason for deducting points is required'),
            'reason.min' => __('Reason must be at least 3 characters'),
            'reason.max' => __('Reason cannot exceed 255 characters'),
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                __('Validation failed'),
                $validator->errors()->toArray(),
                422
            )
        );
    }
}