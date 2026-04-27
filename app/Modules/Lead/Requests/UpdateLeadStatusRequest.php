<?php

namespace App\Modules\Lead\Requests;

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
            'meetingId' => ['nullable', 'integer', 'exists:lead_meetings,id'],
            'outcome' => ['required_if:status,meeting_completed', 'nullable', 'string', 'max:1000'],
            'feedback' => ['required_if:status,meeting_completed', 'nullable', 'string', 'min:20', 'max:5000'],
            'teamLeadId' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => __('validation.required', ['attribute' => 'status']),
            'lossReason.required_if' => __('leads.loss_reason_required'),
            'outcome.required_if' => __('validation.required', ['attribute' => 'outcome']),
            'feedback.required_if' => __('validation.required', ['attribute' => 'feedback']),
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
