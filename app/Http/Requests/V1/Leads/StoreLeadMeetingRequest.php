<?php

namespace App\Http\Requests\V1\Leads;

use App\Enums\MeetingLocation;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreLeadMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'location' => ['required', Rule::enum(MeetingLocation::class)],
            'meetingLink' => ['nullable', 'url', 'max:500'],
            'scheduledAt' => ['required', 'date', 'after:now'],
            'durationMinutes' => ['nullable', 'integer', 'min:15', 'max:480'],
            'attendees' => ['nullable', 'array'],
            'attendees.*.userId' => ['required_with:attendees', 'integer', 'exists:users,id'],
            'attendees.*.role' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'scheduledAt.after' => __('leads.meeting_must_be_future'),
            'meetingLink.url' => __('validation.url', ['attribute' => 'meetingLink']),
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                __('leads.meeting_validation_failed')
            )
        );
    }
}
