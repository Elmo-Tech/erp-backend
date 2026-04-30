<?php

namespace App\Enums;

enum TaskCategory: string
{
    case BACKEND = 'backend';
    case FRONTEND = 'frontend';
    case API = 'api';
    case DATABASE = 'database';
    case UI = 'ui';
    case UX = 'ux';
    case UI_UX = 'ui_ux';
    case COMPONENT = 'component';
    case STYLING = 'styling';
    case DESIGN = 'design';
    case PROTOTYPE = 'prototype';
    case WIREFRAME = 'wireframe';
    case MOBILE = 'mobile';
    case IOS = 'ios';
    case ANDROID = 'android';
    case REACT_NATIVE = 'react_native';
    case DEPLOYMENT = 'deployment';
    case INFRASTRUCTURE = 'infrastructure';
    case CI_CD = 'ci_cd';
    case MONITORING = 'monitoring';
    case TESTING = 'testing';
    case AUTOMATION = 'automation';
    case BUG_FIX = 'bug_fix';
    case QUALITY_ASSURANCE = 'quality_assurance';
    case SERVER = 'server';
    case GENERAL = 'general';

    public function label(): string
    {
        return match ($this) {
            self::BACKEND => 'Backend Development',
            self::FRONTEND => 'Frontend Development',
            self::API => 'API Development',
            self::DATABASE => 'Database Work',
            self::UI => 'User Interface',
            self::UX => 'User Experience',
            self::UI_UX => 'UI/UX Design',
            self::COMPONENT => 'Component Development',
            self::STYLING => 'Styling & CSS',
            self::DESIGN => 'Design Work',
            self::PROTOTYPE => 'Prototyping',
            self::WIREFRAME => 'Wireframing',
            self::MOBILE => 'Mobile Development',
            self::IOS => 'iOS Development',
            self::ANDROID => 'Android Development',
            self::REACT_NATIVE => 'React Native',
            self::DEPLOYMENT => 'Deployment',
            self::INFRASTRUCTURE => 'Infrastructure',
            self::CI_CD => 'CI/CD Pipeline',
            self::MONITORING => 'Monitoring & Logging',
            self::TESTING => 'Testing',
            self::AUTOMATION => 'Test Automation',
            self::BUG_FIX => 'Bug Fix',
            self::QUALITY_ASSURANCE => 'Quality Assurance',
            self::SERVER => 'Server Management',
            self::GENERAL => 'General Task',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::BACKEND, self::API, self::DATABASE, self::SERVER => 'bg-blue-100 text-blue-800',
            self::FRONTEND, self::UI, self::COMPONENT, self::STYLING => 'bg-green-100 text-green-800',
            self::DESIGN, self::PROTOTYPE, self::WIREFRAME, self::UX, self::UI_UX => 'bg-pink-100 text-pink-800',
            self::MOBILE, self::IOS, self::ANDROID, self::REACT_NATIVE => 'bg-orange-100 text-orange-800',
            self::DEPLOYMENT, self::INFRASTRUCTURE, self::CI_CD, self::MONITORING => 'bg-gray-100 text-gray-800',
            self::TESTING, self::AUTOMATION, self::BUG_FIX, self::QUALITY_ASSURANCE => 'bg-yellow-100 text-yellow-800',
            self::GENERAL => 'bg-purple-100 text-purple-800',
        };
    }

    /**
     * Check if this task category is compatible with a user specialization
     */
    public function isCompatibleWith(UserSpecialization $specialization): bool
    {
        $compatibleTypes = $specialization->getCompatibleTaskTypes();

        return in_array($this->value, $compatibleTypes);
    }

    public static function getAllOptions(): array
    {
        return array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'color' => $case->color(),
        ], self::cases());
    }

    /**
     * Get categories compatible with a user specialization
     */
    public static function getCompatibleCategories(UserSpecialization $specialization): array
    {
        return array_filter(self::cases(), fn ($category) => $category->isCompatibleWith($specialization));
    }
}
