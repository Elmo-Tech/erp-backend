<?php

namespace App\Http\Requests\V1\HR;

use Illuminate\Foundation\Http\FormRequest;

class ApproveDirectEmployeeDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyPermission(['manage_employees', 'manage_deductions', 'approve_deductions']) ?? false;
    }

    public function rules(): array
    {
        return [
            'reviewNote' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
