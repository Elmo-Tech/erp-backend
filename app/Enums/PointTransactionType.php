<?php

namespace App\Enums;

enum PointTransactionType: string
{
    // Positive Points
    case TASK_COMPLETED = 'task_completed';
    case TASK_ON_TIME = 'task_on_time';
    case CODE_REVIEW_APPROVED = 'code_review_approved';
    case INNOVATION_VERIFIED = 'innovation_verified';
    case ATTENDANCE_BONUS = 'attendance_bonus';
    case QUALITY_BONUS = 'quality_bonus';
    case PERFORMANCE_BONUS = 'performance_bonus';
    case MANUAL_BONUS = 'manual_bonus';
    case GENERAL_CLEANLINESS_BONUS = 'general_cleanliness_bonus';

    // Negative Points
    case BUG_FOUND = 'bug_found';
    case TASK_LATE = 'task_late';
    case CODE_REVIEW_REJECTED = 'code_review_rejected';
    case ATTENDANCE_LATE = 'attendance_late';
    case TASK_REOPENED = 'task_reopened';
    case MANUAL_DEDUCTION = 'manual_deduction';
    case GENERAL_CLEANLINESS_DEDUCTION = 'general_cleanliness_deduction';

    /**
     * Get the default points for this transaction type
     */
    public function defaultPoints(): int
    {
        return match ($this) {
            // Positive
            self::TASK_COMPLETED => 10,
            self::TASK_ON_TIME => 5,
            self::CODE_REVIEW_APPROVED => 5,
            self::INNOVATION_VERIFIED => 15,
            self::ATTENDANCE_BONUS => 5,
            self::QUALITY_BONUS => 10,
            self::PERFORMANCE_BONUS => 20,
            self::MANUAL_BONUS => 0, // Variable
            self::GENERAL_CLEANLINESS_BONUS => 5,

            // Negative
            self::BUG_FOUND => -5,
            self::TASK_LATE => -3,
            self::CODE_REVIEW_REJECTED => -2,
            self::ATTENDANCE_LATE => -2,
            self::TASK_REOPENED => -5,
            self::MANUAL_DEDUCTION => 0, // Variable
            self::GENERAL_CLEANLINESS_DEDUCTION => -5,
        };
    }

    /**
     * Check if this is a positive transaction type
     */
    public function isPositive(): bool
    {
        return in_array($this, [
            self::TASK_COMPLETED,
            self::TASK_ON_TIME,
            self::CODE_REVIEW_APPROVED,
            self::INNOVATION_VERIFIED,
            self::ATTENDANCE_BONUS,
            self::QUALITY_BONUS,
            self::PERFORMANCE_BONUS,
            self::MANUAL_BONUS,
            self::GENERAL_CLEANLINESS_BONUS,
        ]);
    }

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::TASK_COMPLETED => 'Task Completed',
            self::TASK_ON_TIME => 'Task Delivered On Time',
            self::CODE_REVIEW_APPROVED => 'Code Review Approved',
            self::INNOVATION_VERIFIED => 'Innovation Verified',
            self::ATTENDANCE_BONUS => 'Attendance Bonus',
            self::QUALITY_BONUS => 'Quality Bonus',
            self::PERFORMANCE_BONUS => 'Performance Bonus',
            self::MANUAL_BONUS => 'Manual Bonus',
            self::GENERAL_CLEANLINESS_BONUS => 'General Cleanliness Bonus',
            self::BUG_FOUND => 'Bug Found',
            self::TASK_LATE => 'Task Delivered Late',
            self::CODE_REVIEW_REJECTED => 'Code Review Rejected',
            self::ATTENDANCE_LATE => 'Late Attendance',
            self::TASK_REOPENED => 'Task Reopened',
            self::MANUAL_DEDUCTION => 'Manual Deduction',
            self::GENERAL_CLEANLINESS_DEDUCTION => 'General Cleanliness Deduction',
        };
    }
}
