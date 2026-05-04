<?php

namespace App\Enums;

enum ProjectRole: string
{
    case MANAGER   = 'manager';
    case TECH_LEAD = 'tech_lead';
    case DEVELOPER = 'developer';
    case QA        = 'qa';
    case DESIGNER  = 'designer';

    public function label(): string
    {
        return match ($this) {
            self::MANAGER   => 'Manager',
            self::TECH_LEAD => 'Tech Lead',
            self::DEVELOPER => 'Developer',
            self::QA        => 'QA',
            self::DESIGNER  => 'Designer',
        };
    }

    public static function getAllRoles(): array
    {
        return [
            self::MANAGER,
            self::TECH_LEAD,
            self::DEVELOPER,
            self::QA,
            self::DESIGNER,
        ];
    }
}
