<?php

namespace App\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LookupOptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'type' => ['required', 'string', 'in:specialization,task_category,project_status,task_status'],
            'label' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:50'],
            'metadata' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($this->isMethod('POST')) {
            $rules['value'] = [
                'required',
                'string',
                'max:50',
                Rule::unique('lookup_options', 'value')->where(function ($query) {
                    return $query->where('type', $this->input('type'));
                }),
            ];
        } else {
            // Value is optional on update, but must be unique if provided
            $rules['value'] = [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('lookup_options', 'value')
                    ->where(function ($query) {
                        return $query->where('type', $this->input('type', $this->lookupOption->type));
                    })
                    ->ignore($this->lookupOption->id),
            ];
        }

        return $rules;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
                $lookupOption = $this->route('lookupOption');
                if ($lookupOption && ($lookupOption->metadata['is_system'] ?? false)) {
                    if ($this->has('value') && $this->input('value') !== $lookupOption->value) {
                        $validator->errors()->add('value', 'The value/slug of a system-reserved lookup option cannot be modified.');
                    }
                }
            }
        });
    }
}
