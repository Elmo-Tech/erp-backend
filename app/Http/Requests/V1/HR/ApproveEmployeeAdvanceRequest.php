<?php

namespace App\Http\Requests\V1\HR;

use Illuminate\Foundation\Http\FormRequest;

class ApproveEmployeeAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyPermission(['manage_employees', 'manage_employee_advances', 'approve_employee_advances']) ?? false;
    }

    public function rules(): array
    {
        return [
            'approvedAmount' => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:999999999999.99'],
            'reviewNote' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
