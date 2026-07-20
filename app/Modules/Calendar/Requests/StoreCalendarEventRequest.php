<?php

namespace App\Modules\Calendar\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCalendarEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via $user->can()
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'startTime' => ['required', 'date'],
            'endTime' => ['required', 'date', 'after_or_equal:startTime'],
            'eventType' => ['sometimes', 'string', 'in:meeting,reminder,custom'],
        ];
    }
}
