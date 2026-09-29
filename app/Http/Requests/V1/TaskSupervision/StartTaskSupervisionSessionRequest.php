<?php

namespace App\Http\Requests\V1\TaskSupervision;

class StartTaskSupervisionSessionRequest extends TaskSupervisionRequest
{
    public function rules(): array
    {
        return [
            'helpRequestId' => ['nullable', 'integer', 'exists:task_help_requests,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
