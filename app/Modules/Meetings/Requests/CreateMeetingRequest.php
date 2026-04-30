<?php

namespace App\Modules\Meetings\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreateMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('schedule_meetings');
    }

    public function rules(): array
    {
        return [
            'subjectType' => ['required', 'string', 'in:App\\Modules\\Client\\Models\\Client,App\\Modules\\Project\\Models\\Project'],
            'subjectId' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:255'],
            'locationDetails' => ['nullable', 'string', 'max:500'],
            'assignedTo' => ['nullable', 'integer', 'exists:users,id'],
            'scheduledAt' => ['required', 'date', 'after:now'],
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
