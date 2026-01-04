<?php

namespace App\Http\Requests\V1\Leads;

use App\Enums\LeadStatus;
use App\Enums\RejectionReason;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateLeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(LeadStatus::class)],
            'lossReason' => ['nullable', 'required_if:status,lost', Rule::enum(RejectionReason::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => __('validation.required', ['attribute' => 'status']),
            'lossReason.required_if' => __('leads.loss_reason_required'),
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                __('leads.validation_failed')
            )
        );
    }
}
