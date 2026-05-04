<?php

namespace App\Modules\Project\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreManualTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'startTime' => ['required', 'date'],
            'endTime'   => ['required', 'date', 'after:startTime'],
            'note'      => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'startTime.required' => 'A start time is required.',
            'startTime.date'     => 'Start time must be a valid date.',
            'endTime.required'   => 'An end time is required.',
            'endTime.date'       => 'End time must be a valid date.',
            'endTime.after'      => 'End time must be after start time.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError(
                $validator->errors()->toArray(),
                'Validation failed for manual time entry'
            )
        );
    }
}
