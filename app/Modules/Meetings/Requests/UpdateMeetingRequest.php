<?php

namespace App\Modules\Meetings\Requests;

use App\Enums\MeetingStatus;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Enum;

class UpdateMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'assignedTo' => ['sometimes', 'integer', 'exists:users,id'],
            'scheduledAt' => ['nullable', 'date'],
            'outcomeSummary' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', new Enum(MeetingStatus::class)],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                __('validation.failed'),
                422,
                $validator->errors()->toArray()
            )
        );
    }
}
