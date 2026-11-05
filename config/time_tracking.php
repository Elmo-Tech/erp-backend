<?php

return [
    'heartbeat_interval_seconds' => 45,
    'stale_after_seconds' => 180,
    'expire_on_disconnect' => env('TIMER_EXPIRE_ON_DISCONNECT', false),
    'after_hours_interval_minutes' => (int) env('TIMER_AFTER_HOURS_INTERVAL_MINUTES', 60),
    'after_hours_grace_minutes' => (int) env('TIMER_AFTER_HOURS_GRACE_MINUTES', 10),
    'browser_reminder_minutes' => 45,
];
