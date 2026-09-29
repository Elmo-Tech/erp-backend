<?php

namespace App\Http\Requests\V1\TaskSupervision;

class TaskSupervisionSummaryRequest extends TaskSupervisionRequest
{
    public function rules(): array
    {
        return [
            'taskId' => ['nullable', 'integer', 'exists:tasks,id'],
            'supervisorId' => ['nullable', 'integer', 'exists:users,id'],
            'requestedBy' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
