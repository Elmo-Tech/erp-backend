<?php

namespace App\Http\Requests\V1\HR;

use Illuminate\Foundation\Http\FormRequest;

class RejectDirectEmployeeDeductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyPermission(['manage_employees', 'manage_deductions', 'reject_deductions']) ?? false;
    }

    public function rules(): array
    {
        return [
            'reviewNote' => ['required', 'string', 'max:2000'],
        ];
    }
}
