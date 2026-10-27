<?php

namespace App\Http\Requests\V1;

use App\Support\PermissionCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LookupOptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $type = (string) $this->input('type', $this->route('lookupOption')?->type ?? '');
        $action = $this->isMethod('POST') ? 'create' : 'edit';
        $permission = PermissionCatalog::lookupPermission($type);
        $can = $this->user()?->hasAnyPermission(['manage_lookup_options', $permission, $action.substr($permission, strlen('manage'))]);
        $existing = $this->route('lookupOption');
        $existingPermission = $existing ? PermissionCatalog::lookupPermission($existing->type) : $permission;

        return $can && (! $existing || $this->user()?->hasAnyPermission(['manage_lookup_options', $existingPermission, $action.substr($existingPermission, strlen('manage'))]));
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'type' => ['required', 'string', 'in:specialization,task_category,project_type,project_status,task_status,contract_duration'],
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
        // System-reserved lookup options can now be fully edited/deleted by authorized users.
    }
}
