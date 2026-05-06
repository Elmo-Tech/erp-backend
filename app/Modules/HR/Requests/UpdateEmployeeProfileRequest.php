<?php

namespace App\Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shift_id'    => ['sometimes', 'nullable', 'integer', Rule::exists('shifts', 'id')],
            'base_salary' => ['sometimes', 'numeric', 'min:0'],
            'hourly_rate' => ['sometimes', 'numeric', 'min:0'],
            'join_date'   => ['sometimes', 'date'],
        ];
    }
}
