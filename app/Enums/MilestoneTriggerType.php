<?php

namespace App\Enums;

enum MilestoneTriggerType: string
{
    case IMMEDIATE = 'immediate';
    case PROJECT_STATUS = 'project_status';
    case DELIVERABLE = 'deliverable';
    case DATE = 'date';

    public function label(): string
    {
        return match($this) {
            self::IMMEDIATE => __('enums.milestone_trigger.immediate'),
            self::PROJECT_STATUS => __('enums.milestone_trigger.project_status'),
            self::DELIVERABLE => __('enums.milestone_trigger.deliverable'),
            self::DATE => __('enums.milestone_trigger.date'),
        };
    }
}
