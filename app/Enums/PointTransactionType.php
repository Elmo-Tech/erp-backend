<?php

namespace App\Enums;

enum PointTransactionType: string
{
    // Positive Points
    case TASK_ON_TIME = 'task_on_time';
    case CODE_REVIEW_APPROVED = 'code_review_approved';
    case INNOVATION_VERIFIED = 'innovation_verified';
    case ATTENDANCE_BONUS = 'attendance_bonus';
    case QUALITY_BONUS = 'quality_bonus';
    case PERFORMANCE_BONUS = 'performance_bonus';
    case MANUAL_BONUS = 'manual_bonus';
    case GENERAL_CLEANLINESS_BONUS = 'general_cleanliness_bonus';
    case DELIVERABLE_APPROVED = 'deliverable_approved';
    case TEST_SCENARIO_VERIFIED = 'test_scenario_verified';

    // Negative Points
    case BUG_FOUND = 'bug_found';
    case TASK_LATE = 'task_late';
    case CODE_REVIEW_REJECTED = 'code_review_rejected';
    case ATTENDANCE_LATE = 'attendance_late';
    case TASK_REOPENED = 'task_reopened';
    case MANUAL_DEDUCTION = 'manual_deduction';
    case GENERAL_CLEANLINESS_DEDUCTION = 'general_cleanliness_deduction';
    case DELIVERABLE_REJECTED = 'deliverable_rejected';
    case TEST_SCENARIO_FAILED = 'test_scenario_failed';

    /**
     * Get the default points for this transaction type
     */
    public function defaultPoints(): int
    {
        return match ($this) {
            // Positive - All worth 1 point
            self::TASK_ON_TIME => 1,
            self::CODE_REVIEW_APPROVED => 1,
            self::INNOVATION_VERIFIED => 1,
            self::ATTENDANCE_BONUS => 1,
            self::QUALITY_BONUS => 1,
            self::PERFORMANCE_BONUS => 1,
            self::MANUAL_BONUS => 0, // Variable
            self::GENERAL_CLEANLINESS_BONUS => 1,
            self::DELIVERABLE_APPROVED => 1,
            self::TEST_SCENARIO_VERIFIED => 1,

            // Negative - TASK_LATE is variable based on delay days, others worth -1 point
            self::BUG_FOUND => -1,
            self::TASK_LATE => -1, // Default -1, but can be overridden with actual delay days
            self::CODE_REVIEW_REJECTED => -1,
            self::ATTENDANCE_LATE => -1,
            self::TASK_REOPENED => -1,
            self::MANUAL_DEDUCTION => 0, // Variable
            self::GENERAL_CLEANLINESS_DEDUCTION => -1,
            self::DELIVERABLE_REJECTED => -1,
            self::TEST_SCENARIO_FAILED => -1,
        };
    }

    /**
     * Check if this is a positive transaction type
     */
    public function isPositive(): bool
    {
        return in_array($this, [
            self::TASK_ON_TIME,
            self::CODE_REVIEW_APPROVED,
            self::INNOVATION_VERIFIED,
            self::ATTENDANCE_BONUS,
            self::QUALITY_BONUS,
            self::PERFORMANCE_BONUS,
            self::MANUAL_BONUS,
            self::GENERAL_CLEANLINESS_BONUS,
            self::DELIVERABLE_APPROVED,
            self::TEST_SCENARIO_VERIFIED,
        ]);
    }

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::TASK_ON_TIME => 'Task Delivered On Time',
            self::CODE_REVIEW_APPROVED => 'Code Review Approved',
            self::INNOVATION_VERIFIED => 'Innovation Verified',
            self::ATTENDANCE_BONUS => 'Attendance Bonus',
            self::QUALITY_BONUS => 'Quality Bonus',
            self::PERFORMANCE_BONUS => 'Performance Bonus',
            self::MANUAL_BONUS => 'Manual Bonus',
            self::GENERAL_CLEANLINESS_BONUS => 'General Cleanliness Bonus',
            self::DELIVERABLE_APPROVED => 'Deliverable Approved',
            self::TEST_SCENARIO_VERIFIED => 'Test Scenario Verified',
            self::BUG_FOUND => 'Bug Found',
            self::TASK_LATE => 'Task Delivered Late',
            self::CODE_REVIEW_REJECTED => 'Code Review Rejected',
            self::ATTENDANCE_LATE => 'Late Attendance',
            self::TASK_REOPENED => 'Task Reopened',
            self::MANUAL_DEDUCTION => 'Manual Deduction',
            self::GENERAL_CLEANLINESS_DEDUCTION => 'General Cleanliness Deduction',
            self::DELIVERABLE_REJECTED => 'Deliverable Rejected',
            self::TEST_SCENARIO_FAILED => 'Test Scenario Failed',
        };
    }
}
