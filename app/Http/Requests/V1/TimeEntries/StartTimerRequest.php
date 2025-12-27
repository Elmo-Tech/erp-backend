<?php

namespace App\Http\Requests\V1\TimeEntries;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StartTimerRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'trackableId' => ['required', 'integer'],
            'trackableType' => ['required', Rule::in(['lead', 'quotation', 'task'])],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'trackableId.required' => 'The trackableId field is required.',
            'trackableId.integer' => 'The trackableId must be an integer.',
            'trackableType.required' => 'The trackableType field is required.',
            'trackableType.in' => 'The selected trackableType is invalid. Valid values are: lead, quotation, task.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param Validator $validator
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'Validation failed for starting timer'
            )
        );
    }
}