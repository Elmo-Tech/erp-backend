<?php

namespace App\Http\Requests\V1\TaskSupervision;

class UpdateTaskHelpRequest extends TaskSupervisionRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:acknowledged,resolved,cancelled'],
            'resolutionNote' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
