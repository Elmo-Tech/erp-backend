<?php

return [
    'notifications' => [
        // Comma-separated list of internal user emails that should receive task update notifications.
        'extra_user_emails' => array_values(array_filter(array_map(
            static fn (string $email) => trim($email),
            explode(',', (string) env('TASK_NOTIFICATION_EXTRA_USER_EMAILS', ''))
        ))),

        // Include the task creator in comment/status/assignment notifications by default.
        'include_creator' => env('TASK_NOTIFICATION_INCLUDE_CREATOR', true),
    ],
];
