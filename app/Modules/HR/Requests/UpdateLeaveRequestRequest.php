<?php

namespace App\Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via $user->can()
    }

    public function rules(): array
    {
        return [
            'start_date' => ['sometimes', 'date'],
            'end_date'   => ['sometimes', 'date', 'after_or_equal:start_date'],
            'type'       => ['sometimes', 'string', 'in:annual,sick,unpaid,permission'],
            'status'     => ['sometimes', 'string', 'in:pending,approved,rejected'],
            'reason'     => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
