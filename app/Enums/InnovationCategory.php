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
            self::AI_TOOL => 'أداة ذكاء اصطناعي',
            self::AUTOMATION => 'أتمتة',
            self::NEW_TECH => 'تقنية جديدة',
            self::PROCESS_IMPROVEMENT => 'تحسين عملية',
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
