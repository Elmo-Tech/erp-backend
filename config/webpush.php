<?php

return [
    'public_key' => env('WEB_PUSH_PUBLIC_KEY'),
    'private_key' => env('WEB_PUSH_PRIVATE_KEY'),
    'subject' => env('WEB_PUSH_SUBJECT', 'mailto:admin@example.com'),
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
    'reminder_intervals' => [5, 10, 15, 30, 45, 60],
    'allowed_hosts' => ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com', 'notify.windows.com'],
];
