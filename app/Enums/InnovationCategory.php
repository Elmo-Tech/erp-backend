<?php

namespace App\Enums;

enum InnovationCategory: string
{
    case AI_TOOL = 'ai_tool';
    case AUTOMATION = 'automation';
    case NEW_TECH = 'new_tech';
    case PROCESS_IMPROVEMENT = 'process_improvement';

    public function label(): string
    {
        return match ($this) {
            self::AI_TOOL => 'AI Tool',
            self::AUTOMATION => 'Automation',
            self::NEW_TECH => 'New Technology',
            self::PROCESS_IMPROVEMENT => 'Process Improvement',
        };
    }

    public function points(): int
    {
        return match ($this) {
            self::AI_TOOL => 4,
            self::AUTOMATION => 5,
            self::NEW_TECH => 4,
            self::PROCESS_IMPROVEMENT => 3,
        };
    }
}
