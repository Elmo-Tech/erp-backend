<?php

namespace App\Http\Requests\V1\HR;

use App\Support\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;

class ReviewEmployeeOvertimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasAnyPermission(['manage_overtime', 'manage_employees', PermissionCatalog::hrAction('manage_overtime')]);
    }

    public function rules(): array
    {
        return [
            'reviewNote' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
