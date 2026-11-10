<?php

namespace App\Http\Requests\V1\TaskSupervision;

class SetTaskSupervisorRequest extends TaskSupervisionRequest
{
    public function rules(): array
    {
        return [
            'supervisorIds' => ['sometimes', 'array', 'max:2'],
            'supervisorIds.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'supervisorId' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
