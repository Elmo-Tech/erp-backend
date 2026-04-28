<?php

namespace App\Modules\Proposal\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RejectProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rejectionReason' => ['required', 'string', 'max:255'],
            'rejectionNotes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'rejectionReason.required' => __('validation.required', ['attribute' => 'rejectionReason']),
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
