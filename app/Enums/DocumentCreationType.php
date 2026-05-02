<?php

namespace App\Enums;

enum DocumentCreationType: string
{
    case TASK          = 'task';
    case DIRECT_UPLOAD = 'direct_upload';
    case SKIPPED       = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::TASK          => 'Task',
            self::DIRECT_UPLOAD => 'Direct Upload',
            self::SKIPPED       => 'Skipped',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
