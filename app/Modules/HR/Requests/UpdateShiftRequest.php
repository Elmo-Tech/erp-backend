<?php

namespace App\Modules\HR\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                  => ['sometimes', 'string', 'max:100'],
            'start_time'            => ['sometimes', 'date_format:H:i,H:i:s'],
            'end_time'              => ['sometimes', 'date_format:H:i,H:i:s'],
            'working_days'          => ['sometimes', 'array', 'min:1'],
            'working_days.*'        => ['required_with:working_days', 'string', 'in:Sun,Mon,Tue,Wed,Thu,Fri,Sat'],
            'grace_period_minutes'  => ['sometimes', 'integer', 'min:0', 'max:120'],
        ];
    }
}
