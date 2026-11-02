<?php

namespace App\Rules;

use App\Models\LookupOption;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SelectableProjectStatus implements ValidationRule
{
    public function __construct(private readonly ?string $currentStatus = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('Select a valid project status.');

            return;
        }

        if ($value === $this->currentStatus) {
            return;
        }

        if (! LookupOption::ofType('project_status')->active()->where('value', $value)->exists()) {
            $fail('Select an active project status from settings.');
        }
    }
}
