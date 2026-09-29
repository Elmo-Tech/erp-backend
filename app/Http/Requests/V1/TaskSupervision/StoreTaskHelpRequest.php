<?php

namespace App\Http\Requests\V1\TaskSupervision;

class StoreTaskHelpRequest extends TaskSupervisionRequest
{
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000', 'not_regex:/^\s*$/'],
        ];
    }
}
