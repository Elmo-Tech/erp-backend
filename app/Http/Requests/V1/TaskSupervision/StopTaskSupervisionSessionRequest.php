<?php

namespace App\Http\Requests\V1\TaskSupervision;

class StopTaskSupervisionSessionRequest extends TaskSupervisionRequest
{
    public function rules(): array
    {
        return [
            'stopReason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
