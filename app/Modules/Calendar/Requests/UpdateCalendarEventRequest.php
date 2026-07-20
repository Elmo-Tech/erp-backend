<?php

namespace App\Modules\Calendar\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via $user->can()
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'startTime' => ['sometimes', 'date'],
            'endTime' => ['sometimes', 'date'],
            'eventType' => ['sometimes', 'string', 'in:meeting,reminder,custom'],
        ];
    }
}
