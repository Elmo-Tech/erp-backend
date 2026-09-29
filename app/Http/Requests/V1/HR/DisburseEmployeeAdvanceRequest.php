<?php

namespace App\Http\Requests\V1\HR;

use Illuminate\Foundation\Http\FormRequest;

class DisburseEmployeeAdvanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_employees') ?? false;
    }

    public function rules(): array
    {
        return [
            'disbursementReference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
