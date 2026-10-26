<?php

return [
    'heartbeat_interval_seconds' => 45,
    'stale_after_seconds' => 180,
    'expire_on_disconnect' => env('TIMER_EXPIRE_ON_DISCONNECT', false),
    'browser_reminder_minutes' => 45,
];
