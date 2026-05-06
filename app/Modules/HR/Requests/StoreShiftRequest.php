<?php

namespace App\Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled in controller via $user->can()
    }

    public function rules(): array
    {
        return [
            'name'                  => ['required', 'string', 'max:100'],
            'start_time'            => ['required', 'date_format:H:i,H:i:s'],
            'end_time'              => ['required', 'date_format:H:i,H:i:s', 'after:start_time'],
            'working_days'          => ['required', 'array', 'min:1'],
            'working_days.*'        => ['required', 'string', 'in:Sun,Mon,Tue,Wed,Thu,Fri,Sat'],
            'grace_period_minutes'  => ['sometimes', 'integer', 'min:0', 'max:120'],
        ];
    }
}
