<?php

namespace App\Http\Requests\V1\HR;

use Illuminate\Foundation\Http\FormRequest;

class ReviewEmployeeOvertimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->can('manage_overtime') || $user->can('manage_employees'));
    }

    public function rules(): array
    {
        return [
            'reviewNote' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
