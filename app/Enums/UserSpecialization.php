<?php

namespace App\Enums;

enum UserSpecialization: string
{
    case BACKEND = 'backend';
    case FRONTEND = 'frontend';
    case FULLSTACK = 'fullstack';
    case UI_UX = 'ui_ux';
    case MOBILE = 'mobile';
    case DEVOPS = 'devops';
    case QA = 'qa';

    public function label(): string
    {
        return match($this) {
            self::BACKEND => 'Backend Developer',
            self::FRONTEND => 'Frontend Developer',
            self::FULLSTACK => 'Full Stack Developer',
            self::UI_UX => 'UI/UX Designer',
            self::MOBILE => 'Mobile Developer',
            self::DEVOPS => 'DevOps Engineer',
            self::QA => 'QA Engineer',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::BACKEND => 'bg-blue-100 text-blue-800',
            self::FRONTEND => 'bg-green-100 text-green-800',
            self::FULLSTACK => 'bg-purple-100 text-purple-800',
            self::UI_UX => 'bg-pink-100 text-pink-800',
            self::MOBILE => 'bg-orange-100 text-orange-800',
            self::DEVOPS => 'bg-gray-100 text-gray-800',
            self::QA => 'bg-yellow-100 text-yellow-800',
        };
    }

    /**
     * Get compatible task types for this specialization
     */
    public function getCompatibleTaskTypes(): array
    {
        return match($this) {
            self::BACKEND => ['backend', 'api', 'database', 'server'],
            self::FRONTEND => ['frontend', 'ui', 'component', 'styling'],
            self::FULLSTACK => ['backend', 'frontend', 'api', 'database', 'ui', 'component'],
            self::UI_UX => ['ui', 'design', 'prototype', 'wireframe'],
            self::MOBILE => ['mobile', 'ios', 'android', 'react_native'],
            self::DEVOPS => ['deployment', 'infrastructure', 'ci_cd', 'monitoring'],
            self::QA => ['testing', 'automation', 'bug_fix', 'quality_assurance'],
        };
    }

    public static function getAllOptions(): array
    {
        return array_map(fn($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'color' => $case->color(),
        ], self::cases());
    }
}