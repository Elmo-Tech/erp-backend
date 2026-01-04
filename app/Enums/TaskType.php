<?php

namespace App\Enums;

enum TaskType: string
{
    case MAIN = 'main';
    case WEEKLY = 'weekly';
    case SUBTASK = 'subtask';

    public function label(): string
    {
        return match ($this) {
            self::MAIN => 'Main Task',
            self::WEEKLY => 'Weekly Task',
            self::SUBTASK => 'Subtask',
        };
    }

    public function allowedChildTypes(): array
    {
        return match ($this) {
            self::MAIN => [self::WEEKLY],
            self::WEEKLY => [self::SUBTASK],
            self::SUBTASK => [],
        };
    }

    public function canHaveChildren(): bool
    {
        return $this !== self::SUBTASK;
    }
}
