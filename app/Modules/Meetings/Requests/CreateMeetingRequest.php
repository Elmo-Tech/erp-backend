<?php

namespace App\Modules\Meetings\Requests;

use App\Helpers\ApiResponse;
use App\Modules\Client\Models\Client;
use App\Modules\Lead\Models\Lead;
use App\Modules\Project\Models\Project;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateMeetingRequest extends FormRequest
{
    private const SUBJECT_TYPE_MAP = [
        'client' => Client::class,
        'project' => Project::class,
        'lead' => Lead::class,
        'App\\Models\\Client' => Client::class,
        'App\\Models\\Project' => Project::class,
        'App\\Models\\Lead' => Lead::class,
        Client::class => Client::class,
        Project::class => Project::class,
        Lead::class => Lead::class,
    ];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('schedule_meetings');
    }

    public function rules(): array
    {
        return [
            'subjectType' => ['required', 'string', Rule::in(array_values(self::SUBJECT_TYPE_MAP))],
            'subjectId' => ['required', 'integer', 'min:1'],
            'meetingType' => ['nullable', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:255'],
            'locationDetails' => [
                'nullable',
                'string',
                'max:500',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $location = (string) $this->input('location');
                    $details = is_string($value) ? trim($value) : '';

                    if ($location === 'online' && $details !== '' && filter_var($details, FILTER_VALIDATE_URL) === false) {
                        $fail('Enter a valid meeting link.');
                    }
                },
            ],
            'assignedTo' => ['nullable', 'integer', 'exists:users,id'],
            'internalAttendees' => ['nullable', 'array'],
            'internalAttendees.*' => ['integer', 'exists:users,id'],
            'externalAttendees' => ['nullable', 'array'],
            'externalAttendees.*' => ['string', 'max:255'],
            'scheduledAt' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'subjectType.required' => 'Meeting subject is required.',
            'subjectType.in' => 'Meeting subject is invalid.',
            'subjectId.required' => 'Meeting subject is required.',
            'title.required' => 'Meeting title is required.',
            'scheduledAt.required' => 'Meeting date and time are required.',
            'scheduledAt.date' => 'Meeting date and time must be valid.',
            'internalAttendees.*.exists' => 'One or more selected attendees are invalid.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $subjectType = $this->input('subjectType', $this->input('subject_type'));
        $subjectId = $this->input('subjectId', $this->input('subject_id'));
        $meetingType = $this->input('meetingType', $this->input('meeting_type'));
        $internalAttendees = $this->input('internalAttendees', $this->input('internal_attendees', []));
        $externalAttendees = $this->input('externalAttendees', $this->input('external_attendees', []));

        $this->merge([
            'subjectType' => self::SUBJECT_TYPE_MAP[$subjectType] ?? $subjectType,
            'subjectId' => $subjectId,
            'meetingType' => $meetingType,
            'internalAttendees' => $internalAttendees,
            'externalAttendees' => $externalAttendees,
        ]);
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
