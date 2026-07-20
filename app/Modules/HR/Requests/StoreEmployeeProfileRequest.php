<?php

namespace App\Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id'), Rule::unique('employee_profiles', 'user_id')],
            'shift_id' => ['nullable', 'integer', Rule::exists('shifts', 'id')],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'join_date' => ['required', 'date'],
        ];
    }
}
