<?php

namespace App\Enums;

enum UserSpecialization: string
{
    case FRONTEND = 'frontend';
    case BACKEND = 'backend';
    case FULLSTACK = 'fullstack';
    case MOBILE = 'mobile';
    case DEVOPS = 'devops';
    case UI_UX = 'ui_ux';
    case QA = 'qa';
    case DATA = 'data';
    case PROJECT_MANAGEMENT = 'project_management';

    public function label(): string
    {
        return match ($this) {
            self::FRONTEND => 'Frontend Developer',
            self::BACKEND => 'Backend Developer',
            self::FULLSTACK => 'Full Stack Developer',
            self::MOBILE => 'Mobile Developer',
            self::DEVOPS => 'DevOps Engineer',
            self::UI_UX => 'UI/UX Designer',
            self::QA => 'QA Engineer',
            self::DATA => 'Data Engineer',
            self::PROJECT_MANAGEMENT => 'Project Manager',
        };
    }
}
