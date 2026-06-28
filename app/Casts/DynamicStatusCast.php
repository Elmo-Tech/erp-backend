<?php

namespace App\Casts;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\LookupOption;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class DynamicStatusCast implements CastsAttributes
{
    protected string $type;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        // 1. Try to resolve to the static PHP Enum first for backward compatibility
        if ($this->type === 'project_status') {
            $enumCase = ProjectStatus::tryFrom($value);
            if ($enumCase !== null) {
                return $enumCase;
            }
        } elseif ($this->type === 'task_status') {
            $enumCase = TaskStatus::tryFrom($value);
            if ($enumCase !== null) {
                return $enumCase;
            }
        }

        // 2. Return a dynamic Enum-like wrapper object for custom user-added statuses
        return new class($value, $this->type) {
            public string $value;
            protected string $type;
            protected ?LookupOption $option = null;

            public function __construct(string $value, string $type)
            {
                $this->value = $value;
                $this->type = $type;
            }

            protected function getOption(): ?LookupOption
            {
                if ($this->option === null) {
                    $this->option = LookupOption::ofType($this->type)->where('value', $this->value)->first();
                }
                return $this->option;
            }

            public function label(): string
            {
                return $this->getOption()?->label ?? ucfirst(str_replace('_', ' ', $this->value));
            }

            public function isCompleted(): bool
            {
                return $this->value === 'completed';
            }

            public function allowedTransitions(): array
            {
                return LookupOption::ofType($this->type)
                    ->active()
                    ->where('value', '!=', $this->value)
                    ->get()
                    ->map(fn($opt) => $opt->value)
                    ->toArray();
            }

            public function canTransitionTo($target): bool
            {
                $targetValue = is_object($target) ? $target->value : $target;
                return $this->value !== $targetValue;
            }

            public function __get(string $name)
            {
                if ($name === 'name') {
                    return strtoupper($this->value);
                }
                if ($name === 'value') {
                    return $this->value;
                }
                return null;
            }

            public function __toString(): string
            {
                return $this->value;
            }
        };
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_object($value)) {
            return $value->value;
        }

        return $value;
    }
}
