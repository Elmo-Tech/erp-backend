<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Attendance Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the attendance system
    | including grace periods, deduction rates, and other attendance-related
    | settings.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Grace Period Days
    |--------------------------------------------------------------------------
    |
    | The number of days an employee can be late before deductions start
    | applying. This is a "grace period" where late attendance is tracked
    | but no points are deducted.
    |
    */
    'grace_period_days' => env('ATTENDANCE_GRACE_PERIOD_DAYS', 5),

    /*
    |--------------------------------------------------------------------------
    | Deduction Rate Per Minute
    |--------------------------------------------------------------------------
    |
    | The number of points deducted per minute of late attendance after
    | the grace period has been exceeded. For example, 0.1 means 0.1 points
    | are deducted for each minute late.
    |
    */
    'deduction_rate_per_minute' => env('ATTENDANCE_DEDUCTION_RATE_PER_MINUTE', 0.1),

    /*
    |--------------------------------------------------------------------------
    | Maximum Late Minutes Per Day
    |--------------------------------------------------------------------------
    |
    | The maximum number of minutes an employee can be late in a single day
    | before additional penalties apply. Set to null for no limit.
    |
    */
    'max_late_minutes_per_day' => env('ATTENDANCE_MAX_LATE_MINUTES_PER_DAY', 60),

    /*
    |--------------------------------------------------------------------------
    | Working Hours
    |--------------------------------------------------------------------------
    |
    | Standard working hours configuration for calculating idle time
    | and productivity metrics.
    |
    */
    'standard_work_hours_per_day' => env('ATTENDANCE_STANDARD_WORK_HOURS', 8),
    'work_start_time' => env('ATTENDANCE_WORK_START_TIME', '09:00'),
    'work_end_time' => env('ATTENDANCE_WORK_END_TIME', '17:00'),

    /*
    |--------------------------------------------------------------------------
    | Late Threshold Minutes
    |--------------------------------------------------------------------------
    |
    | The number of minutes after the official start time before an employee
    | is considered "late". For example, if work starts at 9:00 AM and this
    | is set to 15, employees arriving after 9:15 AM are marked as late.
    |
    */
    'late_threshold_minutes' => env('ATTENDANCE_LATE_THRESHOLD_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Automatic Point Deduction
    |--------------------------------------------------------------------------
    |
    | Whether to automatically deduct points for late attendance after
    | the grace period expires. If false, deductions must be applied manually.
    |
    */
    'auto_deduct_points' => env('ATTENDANCE_AUTO_DEDUCT_POINTS', false),

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for attendance-related notifications.
    |
    */
    'notifications' => [
        'grace_period_warning_days' => 2, // Warn when 2 days left in grace period
        'notify_on_late_arrival' => true,
        'notify_on_grace_period_exceeded' => true,
        'notify_managers_on_excessive_lateness' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Reporting Settings
    |--------------------------------------------------------------------------
    |
    | Settings for attendance reports and analytics.
    |
    */
    'reporting' => [
        'include_weekends' => false,
        'include_holidays' => false,
        'default_report_period_days' => 30,
    ],
];
