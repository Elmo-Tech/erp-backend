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
}
