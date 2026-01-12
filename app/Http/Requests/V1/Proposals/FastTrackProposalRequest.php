<?php

namespace App\Http\Requests\V1\Proposals;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class FastTrackProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leadId' => ['required', 'integer', 'exists:leads,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'projectName' => ['nullable', 'string', 'max:255'],
            'fastTrackReason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'leadId.required' => __('validation.required', ['attribute' => 'leadId']),
            'fastTrackReason.required' => __('validation.required', ['attribute' => 'fastTrackReason']),
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                __('proposals.validation_failed')
            )
        );
    }
}
