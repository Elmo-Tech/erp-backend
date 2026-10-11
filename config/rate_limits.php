<?php

return [
    'api_per_minute' => (int) env('API_RATE_LIMIT', in_array(env('APP_ENV'), ['local', 'testing'], true) ? 1200 : 300),
    'failed_logins_per_minute' => (int) env('AUTH_RATE_LIMIT', 10),
];
