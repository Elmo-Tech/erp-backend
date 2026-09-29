<?php

namespace App\Http\Requests\V1\TaskSupervision;

class SetTaskSupervisorRequest extends TaskSupervisionRequest
{
    public function rules(): array
    {
        return [
            'supervisorId' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
