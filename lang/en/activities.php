<?php

return [
    // CRUD Messages
    'retrieved' => 'Activities retrieved successfully',
    'created' => 'Activity created successfully',
    'updated' => 'Activity updated successfully',
    'deleted' => 'Activity deleted successfully',
    'not_found' => 'Activity not found',
    'validation_failed' => 'Validation failed for activity',

    // Status Messages
    'completed' => 'Activity completed successfully',
    'cancelled' => 'Activity cancelled successfully',
    'schedule_retrieved' => 'Schedule retrieved successfully',
    'upcoming_retrieved' => 'Upcoming events retrieved successfully',

    // Validation Messages
    'outcome_required' => 'Outcome is required when completing an activity',
    'outcome_max' => 'Outcome cannot exceed 5000 characters',
    'cancellation_reason_required' => 'Cancellation reason is required',

    // Reminder Messages
    'reminder_title' => 'Activity Reminder',
    'reminder_message' => 'You have an upcoming activity: :title',
    'reminder_15_min' => 'Your activity ":title" starts in 15 minutes',
    'outcome_reminder_title' => 'Record Activity Outcome',
    'outcome_reminder_message' => 'Activity ":title" has ended. Please record the outcome now.',

    // Notification Messages
    'notification_scheduled' => 'New activity scheduled: :title',
    'notification_completed' => 'Activity completed: :title',
    'notification_cancelled' => 'Activity cancelled: :title',
];
