<?php

namespace App\Http\Requests\V1\HR;

use Illuminate\Foundation\Http\FormRequest;

class DisburseEmployeeAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyPermission(['manage_employees', 'manage_employee_advances', 'disburse_employee_advances']) ?? false;
    }

    public function rules(): array
    {
        return [
            'disbursementReference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
