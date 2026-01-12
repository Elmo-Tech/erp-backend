<?php

return [
    // CRUD Messages
    'retrieved' => 'Meetings retrieved successfully',
    'created' => 'Meeting created successfully',
    'updated' => 'Meeting updated successfully',
    'deleted' => 'Meeting deleted successfully',
    'not_found' => 'Meeting not found',
    'validation_failed' => 'Validation failed for meeting',

    // Status Messages
    'completed' => 'Meeting completed successfully',
    'cancelled' => 'Meeting cancelled successfully',

    // Validation Messages
    'minutes_required' => 'Meeting minutes are required when completing a meeting',
    'minutes_max' => 'Meeting minutes cannot exceed 10000 characters',
    'cancellation_reason_required' => 'Cancellation reason is required',
    'must_be_future' => 'Meeting must be scheduled in the future',

    // Reminder Messages
    'reminder_title' => 'Meeting Reminder',
    'reminder_message' => 'You have an upcoming meeting: :title',
    'reminder_15_min' => 'Your meeting ":title" starts in 15 minutes',
    'outcome_reminder_title' => 'Record Meeting Minutes',
    'outcome_reminder_message' => 'Meeting ":title" has ended. Please record the minutes now.',

    // Notification Messages
    'notification_scheduled' => 'New meeting scheduled: :title',
    'notification_completed' => 'Meeting completed: :title',
    'notification_cancelled' => 'Meeting cancelled: :title',
    'notification_invitation' => 'You have been invited to meeting: :title',
    'notification_cancellation' => 'Meeting ":title" has been cancelled. Reason: :reason',
];
