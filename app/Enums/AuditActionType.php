<?php

namespace App\Enums;

enum AuditActionType: string
{
    case STATUS_CHANGE = 'status_change';
    case DATA_EDIT = 'data_edit';
    case UPLOAD = 'upload';
    case ACTIVITY_LOG = 'activity_log';
    case CREATED = 'created';
    case DELETED = 'deleted';
    case CONVERTED = 'converted';
    case SENT = 'sent';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::STATUS_CHANGE => __('enums.audit_action.status_change'),
            self::DATA_EDIT => __('enums.audit_action.data_edit'),
            self::UPLOAD => __('enums.audit_action.upload'),
            self::ACTIVITY_LOG => __('enums.audit_action.activity_log'),
            self::CREATED => __('enums.audit_action.created'),
            self::DELETED => __('enums.audit_action.deleted'),
            self::CONVERTED => __('enums.audit_action.converted'),
            self::SENT => __('enums.audit_action.sent'),
            self::APPROVED => __('enums.audit_action.approved'),
            self::REJECTED => __('enums.audit_action.rejected'),
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::STATUS_CHANGE => 'refresh-cw',
            self::DATA_EDIT => 'edit',
            self::UPLOAD => 'upload',
            self::ACTIVITY_LOG => 'activity',
            self::CREATED => 'plus-circle',
            self::DELETED => 'trash-2',
            self::CONVERTED => 'git-merge',
            self::SENT => 'send',
            self::APPROVED => 'check-circle',
            self::REJECTED => 'x-circle',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::STATUS_CHANGE => 'blue',
            self::DATA_EDIT => 'yellow',
            self::UPLOAD => 'purple',
            self::ACTIVITY_LOG => 'gray',
            self::CREATED => 'green',
            self::DELETED => 'red',
            self::CONVERTED => 'indigo',
            self::SENT => 'cyan',
            self::APPROVED => 'emerald',
            self::REJECTED => 'rose',
        };
    }
}
